<?php

namespace App\Services\Telegram;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Call;
use App\Models\Integration;
use App\Models\PaymentDetail;
use App\Models\Setting;
use App\Models\Tariff;
use App\Services\BaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService extends BaseService
{
    protected string $adminChanelId;
    public function __construct()
    {
        parent::__construct();

        // БЕЗОПАСНЫЙ ДОСТУП: до миграций таблиц может не быть → используем .env как фолбэк
        try {
            // Если таблица settings уже есть — читаем как раньше
            $setting = Setting::query()->find(Setting::TELEGRAM_PAYMENT_ADMIN_ID_SETTING_ID);
            $this->adminChanelId = $setting ? (int) $setting->value : (int) env('TELEGRAM_ADMIN_CHAT_ID', 0);
        } catch (\Throwable $e) {
            // Таблиц ещё нет/БД недоступна на раннем этапе boot → не валим artisan
            $this->adminChanelId = (int) env('TELEGRAM_ADMIN_CHAT_ID', 0);
        }
    }


    public function checkUpdate($update)
    {
        $botToken = config('services.telegram.bot_token');
        if (isset($update['callback_query'])) {
            $callback = $update['callback_query'];
            $data = $callback['data']; // "pay:user:5125747504"
            $parts = explode(':', $data);

            $action = $parts[0]; // "pay, change_plan"
            $id = $parts[1];     // ID Интеграции

            switch ($action) {
                case 'pay':
                    $this->handlePayForIntegration($id);
                    break;

                case 'change_tariff':
                    $this->handleChangeTariff($id);
                    break;

                case 'pay_menu':
                    $this->handlePayMenu($id);
                    break;

                case 'set_tariff':
                    $tariffId = $parts[2];
                    $this->handleSetTariff($id, $tariffId);
                    break;

                case 'paid':
                    $this->handlePaid($id);
                    break;

                case 'admin_confirm_payment':
                    $this->handleAdminConfirmPayment($id);
                    break;

                case 'admin_cancel_payment':
                    $this->handleAdminCancelPayment($id);
                    break;

                default:
                    Log::warning('Unknown callback action', ['data' => $data]);
            }

            // Обязательно ответить на callback!
            Http::post("https://api.telegram.org/bot{$botToken}/answerCallbackQuery", [
                'callback_query_id' => $callback['id'],
                'text' => 'Обработка запроса...',
                'show_alert' => false,
            ]);
        }
    }

    private function handlePayForIntegration($id)
    {
        $integration = Integration::find($id);
        $chanelId = $integration->telegram_chat_id;
        $replyMarkup = null;
        //Для интеграции с ID запрошено получение реквизитор для оплаты
        if ($integration->debt_price > 0) {
            $msg = "Реквизиты для оплаты\n\n";

            $msg .= "Стоимость: <b>{$integration->debt_price} {$integration->debt_currency}</b>\n\n";

            $paymentDetail = PaymentDetail::where('country', $integration->country)->first();
            if ($paymentDetail) {
                $msg .= $paymentDetail->description;
            } else {
                Log::error("Для интеграции $id не найдено реквизитов по стране интеграции");
            }

            $msg .= "\n\n";
            $msg .= "После совершения оплаты обязательно нажмите на кнопку \"Оплатил\"\n\n";

            $inlineKeyboard = [
                [
                    ['text' => '✅ Оплатил', 'callback_data' => "paid:$id"],
                ],
                [
                    ['text' => '⬅️ Назад', 'callback_data' => "pay_menu:$id"],
                ],
            ];

            $replyMarkup = [
                'inline_keyboard' => $inlineKeyboard
            ];
        } else {
            $msg = "Оплата не требуется. Все задолженности оплачены";
        }

        SendTelegramMessageJob::dispatch($chanelId, $msg, $replyMarkup);
    }

    private function handleChangeTariff($id)
    {
        $integration = Integration::find($id);
        //Для интеграции с ID запрошена смена тарифа

        $msg = "Выбор тарифа\n\n";

        $msg .= "Ваш текущий тариф:\n";
        $tariffData = $integration->getTariffData();
        $msg .= "{$tariffData->name} ({$tariffData->priceOfMinute} {$tariffData->currency}/мин):\n\n";

        $msg .= "Сменить тариф на:\n";

        $tariffs = Tariff::all();

        $inlineKeyboard = [];
        foreach ($tariffs as $tariff) {
            $inlineKeyboard[] = [['text' => "$tariff->name", 'callback_data' => "set_tariff:$id:$tariff->id"]];
        }

        $inlineKeyboard[] = [['text' => '⬅️ Назад', 'callback_data' => "pay_menu:$id"]];

        $replyMarkup = [
            'inline_keyboard' => $inlineKeyboard
        ];

        $chanelId = $integration->telegram_chat_id;

        SendTelegramMessageJob::dispatch($chanelId, $msg, $replyMarkup);
    }

    public function handlePayMenu($id)
    {
        $integration = Integration::find($id);

        $msg = "\u{1F4B3}  Оплата тарифа\n\n";
        $msg .= "Чтобы и дальше получать уведомления в телеграм, нужно оплатить израсходованные минуты за прошлый месяц.\n\n";
        $msg .= " Общая длительность разговоров за прошлый месяц составила {$integration->debt_minutes} минут.\n\n";
        $msg .= "Стоимость расшифровки {$integration->debt_minutes} минут в соответствии с Вашим тарифом составляет <b>{$integration->debt_price} {$integration->debt_currency}</b>";

        $inlineKeyboard = [
            [
                ['text' => '✅ Оплатить', 'callback_data' => "pay:$integration->id"],
            ],
            [
                ['text' => '⚙️ Изменить тариф', 'callback_data' => "change_tariff:$integration->id"],
            ],
        ];

        $replyMarkup = [
            'inline_keyboard' => $inlineKeyboard
        ];

        $chanelId = $integration->telegram_chat_id;
        SendTelegramMessageJob::dispatch($chanelId, $msg, $replyMarkup);
    }

    private function handleSetTariff($id, $tariffId)
    {
        //Для интеграции с ID установлен тариф $tariffId
        $integration = Integration::find($id);
        $integration->tariff_id = $tariffId;
        $integration->save();

        $tariffData = $integration->getTariffData();
        $msg = "Тариф успешно изменен на:\n\n";
        $msg .= $tariffData->name;

        $chanelId = $integration->telegram_chat_id;
        SendTelegramMessageJob::dispatch($chanelId, $msg);
    }

    private function handlePaid($id)
    {
        $integration = Integration::find($id);
        //Для интеграции с ID клиент произвел оплату

        $msg = "Подтверждение оплаты\n\n";
        $msg .= "Ожидаем подтверждения оплаты... Никаких действий от Вас не требуется, уведомления начнут приходить автоматически сразу после того, как система увидит Вашу транзакцию.";

        $chanelId = $integration->telegram_chat_id;
        SendTelegramMessageJob::dispatch($chanelId, $msg);


        //оповестить админа, чтобы он подтвердил или отменил
        $this->adminConfirmPaymentMenu($id);
    }

    private function adminConfirmPaymentMenu($id)
    {
        $integration = Integration::find($id);

        $msg = "Клиент {$integration->title} ($integration->id) произвел оплату:\n\n";

        $msg .= "Стоимость: <b>{$integration->debt_price} {$integration->debt_currency}</b>\n\n";

        $paymentDetail = PaymentDetail::where('country', $integration->country)->first();
        if ($paymentDetail) {
            $msg .= $paymentDetail->description;
        } else {
            Log::error("Для интеграции $id не найдено реквизитов по стране интеграции");
        }

        $msg .= "\n\n";
        $msg .= "Проверьте оплату и активируйте тариф.";
        $inlineKeyboard = [
            [
                ['text' => '✅ Активировать тариф', 'callback_data' => "admin_confirm_payment:$id"],
            ],
            [
                ['text' => '❌ Отклонить', 'callback_data' => "admin_cancel_payment:$id"],
            ],
        ];

        $replyMarkup = [
            'inline_keyboard' => $inlineKeyboard
        ];

        SendTelegramMessageJob::dispatch($this->adminChanelId, $msg, $replyMarkup);
    }

    private function handleAdminConfirmPayment($id)
    {
        $integration = Integration::find($id);
        $integration->active = true;
        $integration->is_paid = true;

        $integration->debt_minutes = 0;
        $integration->debt_price = 0;
        $integration->debt_currency = null;
        $integration->debt_tariff_id = null;

        $integration->save();

        $msg = "Оплата успешно подтверждена!";
        SendTelegramMessageJob::dispatch($this->adminChanelId, $msg);
    }

    private function handleAdminCancelPayment($id)
    {
        //Интеграция была отменена

        $msg = "Оплата отменена!";
        SendTelegramMessageJob::dispatch($this->adminChanelId, $msg);
    }
    public function notifyNewCall(\App\Models\Integration $integration, \App\Models\Call $call): void
    {
        if (!$integration->telegram_chat_id) {
            return;
        }

        $isIncoming = $call->direction === 'in';
        $title = $isIncoming ? "📞 Новый входящий" : "📤 Новый исходящий";
        $dur = (int) ($call->duration ?? 0);
        $duration = $dur > 0 ? gmdate('i:s', $dur) : '00:00';

        $text = "{$title}\n"
              . "От: " . ($call->from_phone ?? '—') . "\n"
              . "Кому: " . ($call->to_phone ?? '—') . "\n"
              . "Статус: " . ($call->status ?? '—') . "\n"
              . "Оператор: " . ($call->operator_name ?? '—') . "\n"
              . "Длительность: {$duration}";

        \App\Jobs\SendTelegramMessageJob::dispatch($integration->telegram_chat_id, $text);
    }

    /**
     * Уведомление об обновлении значимых полей звонка
     * @param string[] $changed
     */
    public function notifyUpdatedCall(\App\Models\Integration $integration, \App\Models\Call $call, array $changed): void
    {
        if (!$integration->telegram_chat_id) {
            return;
        }

        $text = "ℹ️ Обновление звонка\n"
              . "Номер: " . (($call->direction === 'in') ? ($call->from_phone ?? '—') : ($call->to_phone ?? '—')) . "\n"
              . "Изменено: " . implode(', ', $changed);

        \App\Jobs\SendTelegramMessageJob::dispatch($integration->telegram_chat_id, $text);
    }
}
