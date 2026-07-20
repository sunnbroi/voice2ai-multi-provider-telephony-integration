<?php

namespace App\Services\OpenAi;

use App\Models\Call;
use App\Models\Integration;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

use function mb_strtolower;
use function str_starts_with;

/**
 * Серввис для работы с OpenAI
 */
class OpenAIService extends BaseService
{
    /**
     * Конфликтный ли звонок
     * @param string $text
     * @return bool
     */
    public function isConflictCallByAI(string $text): bool
    {
        $openAiKey = config('services.openai.api_key');
        if (!$openAiKey) {
            Log::error('OPENAI_API_KEY не установлен');
            return false;
        }


        $client = (new \OpenAI\Factory())->withApiKey($openAiKey)->make();

        try {
            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник, который анализирует текст разговора и определяет, является ли звонок конфликтным. Ответь "да" или "нет". Если клиент недоволен чем-то, то конфликтный да'],
                    ['role' => 'user', 'content' => "Этот разговор конфликтный? Ответь только 'да' или 'нет':\n{$text}"],
                ],
            ]);

            $answer = mb_strtolower(trim($response->choices[0]->message->content));
            return str_starts_with($answer, 'да');
        } catch (Throwable $e) {
            Log::error("Ошибка при определении конфликта ИИ: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Логика обработки звонка (транскрибация, чат, телега)
     *
     * @param Call $call
     * @return void
     */
    public function processCallSummary(Call $call)
    {
        Log::info("Обрабатываем звонок {$call->id} в processCallSummary");

        if (!$call->recording_url || !$call->integration || !$call->integration->telegram_chat_id) {
            Log::warning("Call {$call->id} не имеет записи или Telegram ID");
            return;
        }

        $openAiKey = env('OPENAI_API_KEY');
        if (!$openAiKey) {
            Log::error('OPENAI_API_KEY не установлен');
            return;
        }

        $client = (new \OpenAI\Factory())->withApiKey($openAiKey)->make();

        $localPath = storage_path('app/public/recordings/' . basename($call->recording_url));

        try {
            $transcription = $client->audio()->transcribe([
                'model' => 'whisper-1',
                'file' => fopen($localPath, 'r'),
                'response_format' => 'text',
            ]);

            $text = $transcription->text ?? (string)$transcription;

            // Определяем конфликтность
            $isConflict = $this->isConflictCallByAI($text);

            Log::info("Конфликтность {$isConflict}");

            if ($isConflict && !$call->is_conflict) {
                $call->is_conflict = true;
                $call->save();
            }

            $response = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник, делающий краткое резюме текста.'],
                    ['role' => 'user', 'content' => "Убирай очевидные вещи в формате Клиент позвонил, Выразил намерение, Клиент попрощался с менеджером. Максимально краткую суть оставь. Если интересовался, то чем, Озвучили такую-то цену, Озвучили сроки, клиента устроило/не устроило. Делай резюме на языке оригинала! Ни в коем случае ничего не выдумай, делай как есть.  Сделай краткое резюме (не более 200 символов) следующего разговора:\n{$text}"],
                ],
            ]);

            $summary = trim($response->choices[0]->message->content);

            /**
             * @var $integration Integration
             */
            $integration = $call->integration;
            $token = config('services.telegram.bot_token');

            $audioUrl = '';
            $duration = '00:00';
            $recordDomain = config('services.recordings.domain');
            if ($call->duration > 0 && $call->recording_url) {
                $audioUrl = "https://{$recordDomain}/listen/{$call->id}/" . basename($call->recording_url);
                $duration = gmdate('i:s', $call->duration);
            }

            dump($integration->active_tg_notify_client);

            if ($integration->active_tg_notify_client) {
                $command = escapeshellcmd('python3 ' . base_path('app/Jobs/send_telegram.py')) . " " .
                    "--token " . escapeshellarg($token) . " " .
                    "--chat_id " . escapeshellarg($integration->telegram_chat_id) . " " .
                    "--direction " . escapeshellarg($call->direction) . " " .
                    "--from_number " . escapeshellarg($call->from_phone) . " " .
                    "--to_number " . escapeshellarg($call->to_phone) . " " .
                    "--status " . escapeshellarg($call->status) . " " .
                    "--operator " . escapeshellarg($call->operator_name ?? '') . " " .
                    "--audio_url " . escapeshellarg($audioUrl) . " " .
                    "--duration " . escapeshellarg($duration) . " " .
                    "--comment " . escapeshellarg($summary ?? '') . " " .
                    "--is_conflict " . escapeshellarg($isConflict ? 'true' : 'false');  // Передача переменной isConflict
                exec($command, $output, $exitCode);

                if ($exitCode !== 0) {
                    logger()->error('Ошибка при вызове Python скрипта', ['output' => $output]);
                }
            }

            if ($integration->active_tg_notify_admin) {
                $command = escapeshellcmd('python3 ' . base_path('app/Jobs/send_telegram.py')) . " " .
                    "--token " . escapeshellarg($token) . " " .
                    "--chat_id " . escapeshellarg(config('services.telegram.admin_chat_id')) . " " .
                    "--direction " . escapeshellarg($call->direction) . " " .
                    "--from_number " . escapeshellarg($call->from_phone) . " " .
                    "--to_number " . escapeshellarg($call->to_phone) . " " .
                    "--status " . escapeshellarg($call->status) . " " .
                    "--operator " . escapeshellarg($call->operator_name ?? '') . " " .
                    "--audio_url " . escapeshellarg($audioUrl) . " " .
                    "--duration " . escapeshellarg($duration) . " " .
                    "--comment " . escapeshellarg($summary ?? '') . " " .
                    "--is_conflict " . escapeshellarg($isConflict ? 'true' : 'false');  // Передача переменной isConflict
                exec($command, $output, $exitCode);

                if ($exitCode !== 0) {
                    logger()->error('Ошибка при вызове Python скрипта', ['output' => $output]);
                }
            }

            Log::info("Уведомления отправлены для звонка {$call->id} | active_tg_notify_client = $integration->active_tg_notify_client | active_tg_notify_admin = $integration->active_tg_notify_admin");
        } catch (\Throwable $e) {
            Log::error("Ошибка processCallSummary для call_id {$call->id}: {$e->getMessage()}");
        }

        $clientInterestResponse = $client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                ['role' => 'system', 'content' => 'Ты помощник, который определяет, заинтересован ли клиент в покупке, и новый ли он.'],
                ['role' => 'user', 'content' => "Определи по тексту звонка, является ли клиент заинтересованным в покупке (лид) или нет (прочие). Если клиенту не подходит, он не лид. Если такого товара нет в наличии, он не лид.  Обязательным параметром лида есть интерес к товару или услуге. Или же при исходящем звонке - предложение купить твоар или оказать услугу. Важно, что если клиент говорит, что это дорого или долго, это означает, что он не лид, если у него есть интерес, то он однозначно лид. Если клиенту ответили нет в наличии, то клиент точно лид. Также определи, новый ли клиент или повторный. Если нельзя определить, по умолчанию новый.\nТекст разговора:\n{$text}\nОтвечай в формате JSON: {\"lead\":true|false, \"new_client\":true|false}"],
            ],
        ]);
        $leadInfoJson = $clientInterestResponse->choices[0]->message->content ?? '{}';
        Log::info("AI raw leadInfoJson: " . $leadInfoJson);

        $leadInfoJson = trim($leadInfoJson);
        $leadInfoJson = preg_replace('/^```json\s*|```$/', '', $leadInfoJson); // убираем возможный markdown

        $leadInfo = json_decode($leadInfoJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("Ошибка JSON: " . json_last_error_msg());
            $leadInfo = ['lead' => false, 'new_client' => true];
        }

        Log::info("Чат ответ {$leadInfoJson}");

        $isLead = $leadInfo['lead'] ?? false;
        $isNewClient = $leadInfo['new_client'] ?? true;

        $clientPhone = $call->direction === 'in'
            ? $call->from_phone   // входящий звонок
            : $call->to_phone;    // исходящий звонок

        // Проверяем, был ли успешный звонок с этим номером раньше
        $hadSuccessfulCall = Call::where(function ($q) use ($call, $clientPhone) {
            if ($call->direction === 'in') {
                $q->where('from_phone', $clientPhone);
            } else {
                $q->where('to_phone', $clientPhone);
            }
        })
            ->where('status', 'success') // твой статус успешного звонка
            ->where('id', '!=', $call->id) // исключаем текущий звонок
            ->exists();

        if ($hadSuccessfulCall) {
            $isNewClient = false;
        }

        // Обновляем модель звонка
        $call->lead = $isLead;
        $call->new_client = $isNewClient;
        $call->save();

        Log::info("лид иновы lead " . ($isLead ? 'true' : 'false') . " new " . ($isNewClient ? 'true' : 'false'));


        // Если лид и новый клиент - формируем теги и отправляем в спец канал
        if ($isLead && $isNewClient) {
            Log::info("Клиент новый и лид");

            // === 1. Подготовка промпта для тегов ===
            $tagsPrompt = "У компании есть следующие теги: " . $integration->tag . ".
По тексту звонка выбери релевантные теги (может быть несколько).
Нельзя выдумывать — теги должны быть строго из списка на русском языке.
Верни только список тегов через запятую с отступом, без лишних комментариев.
Текст звонка:\n{$text}";

            $tagsResponse = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник для выбора тегов из списка. Тебе нельзя добавлять ничего кроме тегов.'],
                    ['role' => 'user', 'content' => $tagsPrompt],
                ],
            ]);

            $tagsText = trim($tagsResponse->choices[0]->message->content ?? '', " \n\r\t,.");

// === 2. Подготовка промпта для индикатора ===
            $indicatorPrompt = "Определи уровень заинтересованности клиента по звонку.
Выбери ровно один вариант: (1), (2) или (3).
Формат ответа: обязательно верни просто цифру в скобках, без комментариев.

Правила:
(1) — клиент не заинтересован (говорит, что дорого/не подходит/не нужно).
(2) — клиент сомневается, не дал ясного ответа, не выразил чёткий интерес.
(3) — клиент заинтересован, согласился купить/встретиться/прийти.

Текст звонка:\n{$text}";

            $indicatorResponse = $client->chat()->create([
                'model' => 'gpt-4o',
                'temperature' => 0.2,
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник для определения уровня заинтересованности клиента. Отвечай только (1), (2) или (3).'],
                    ['role' => 'user', 'content' => $indicatorPrompt],
                ],
            ]);

            $indicatorText = trim($indicatorResponse->choices[0]->message->content ?? '', " \n\r\t,.");

// === Объединяем результат ===
            $finalTags = $tagsText . " " . $indicatorText;

            $newtextPrompt = " \nТекст звонка:\n{$text}";

            $newResponse = $client->chat()->create([
                'model' => 'gpt-4o',
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник по сокращению. Тебе нужно определить краткую суть звонка, буквально 2-3 словами, например, Ремонт Iphone, или Замена картриджа, чем клиент интересовался, если это ремонт телефона - то модель, если это покупка сумки - то название товара, если аренда помещения - то площадь. '],
                    ['role' => 'user', 'content' => $newtextPrompt],
                ],
            ]);


            $newResponse2 = $client->chat()->create([
                'model' => 'gpt-4o',
                'temperature' => 0.2, // <-- здесь
                'messages' => [
                    ['role' => 'system', 'content' => 'Ты помощник по поиску города в тексте. Важно, если клиент назвал свой город в разговоре, то надо его вывести к примеру так Москва. Если города в тексте нет, выведи None. Если города нет ничего не добавляй в конце, не выдумывай город, только по четкому наличию. Список допустмых городов: Черновцы Днепропетровск Донецк Ивано-Франковск Каменец-Подольский Харьков Херсон Киев Кривой Рог Луганск Львов Николаев Одесса Полтава Сумы Ужгород Запорожье Винница Черкассы Чернигов Ильичевск Луцк Малехов Микуличин Ровно Стрый Великий Лес Великая Омеляна Ахтырка Белогородка Бердянск Борисполь Бояны Бровары Буковель Бурлачья Балка Верховина Винники Жденево Житомир Жовтневое Затока Каменица Каролино-Бугаз Кировоград Коблево Козин Колоденка Коропово Кременчуг Макеевка Мукачево. Пиши город только если он имеется в заданном списке, иначе выводи None. Тебе нельзя писать название улиц, имена людей, названия компаний'],
                    ['role' => 'user', 'content' => $newtextPrompt],
                ],
            ]);


            $newText = trim($newResponse->choices[0]->message->content ?? '', " \n\r\t,.");
            $newCity = trim($newResponse2->choices[0]->message->content ?? '', " \n\r\t,.");

// если $newCity не "None" и не пустая строка — добавляем его в скобках
            if (!empty($newCity) && strtolower($newCity) !== 'none') {
                $newText .= " ({$newCity})";
            }

            $call->tags = $finalTags;
            $call->save();

            if ($integration->active_tg_notify_client) {
                $this->sendLeadToTelegramChannel($call, $newText, $finalTags);
            }

            $integration = $call->integration;
            Log::info("Интеграция {$integration}");
            $adminNotificationsEnabled = $integration->user->notifications ?? false;
            Log::info("User notifications value: ", [
                'user_id' => $integration->user?->id,
                'notifications' => $integration->user?->notifications,
            ]);

            $user = User::first();

            $adminChannel = $user->admin_channel;
            $adminNotificationsEnabled = (bool)$user->notifications;
            Log::info("юзер {$user}");

            Log::info("adminNotificationsEnabled {$adminNotificationsEnabled}");

            if ($adminNotificationsEnabled) {
                if ($integration->active_tg_notify_admin) {
                    $adminChatId = config('services.telegram.admin_chat_id'); // ваш админский канал
                    $token = config('services.telegram.bot_token');
                    $commandAdmin = escapeshellcmd('python3 ' . base_path('app/Jobs/send_telegram.py')) . " " .
                        "--token " . escapeshellarg($token) . " " .
                        "--chat_id " . escapeshellarg($adminChatId) . " " .
                        "--direction " . escapeshellarg($call->direction) . " " .
                        "--from_number " . escapeshellarg($call->from_phone) . " " .
                        "--to_number " . escapeshellarg($call->to_phone) . " " .
                        "--status " . escapeshellarg($call->status) . " " .
                        "--operator " . escapeshellarg($call->operator_name ?? '') . " " .
                        "--audio_url " . escapeshellarg($audioUrl) . " " .
                        "--duration " . escapeshellarg($duration) . " " .
                        "--comment " . escapeshellarg($summary ?? '') . " " .
                        "--is_conflict " . escapeshellarg($isConflict ? 'true' : 'false');

                    exec($commandAdmin, $outputAdmin, $exitCodeAdmin);

                    if ($exitCodeAdmin !== 0) {
                        logger()->error('Ошибка при отправке уведомления в админский канал', ['output' => $outputAdmin]);
                    }
                }
            }
        }
    }

    /**
     * @param Call $call
     * @param string $text
     * @param string $tagsText
     * @return void
     */
    public function sendLeadToTelegramChannel(Call $call, string $text, string $tagsText): void
    {
        $integration = $call->integration;
        $isIncoming = $call->direction === 'in';
        $arrow = $isIncoming ? '➟📱' : '📱➟';
        $directionLabel = $isIncoming ? 'Входящий' : 'Исходящий';

        $country = $integration->country ?? 'Неизвестно';
        $city = $integration->city ?? 'Неизвестно';

        $from = $call->from_phone ?? 'Неизвестен номер';
        $to = $call->to_phone ?? 'Неизвестен номер';

        $msg = "{$country} -> {$city} -> {$tagsText}\n\n";
        // $msg .= "{$directionLabel} {$arrow}\n";

        if ($isIncoming) {
            $msg .= "{$from}\n\n";
        } else {
            $msg .= "{$to}\n\n";
        }

        $msg .= $text;

        $leadsChatId = config('services.telegram.leads_channel_id');
        $token = config('services.telegram.bot_token');

        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        try {
            $response = Http::post($url, [
                'chat_id' => $leadsChatId,
                'text' => $msg,
                'parse_mode' => 'HTML',
            ]);

            $response2 = Http::post($url, [
                'chat_id' => $leadsChatId,
                'text' => $msg,
                'parse_mode' => 'HTML',
            ]);

            if ($response->failed()) {
                Log::error("Ошибка при отправке сообщения в Telegram: " . $response->body());
            } else {
                Log::info("Сообщение успешно отправлено в Telegram-канал для звонка {$call->id}");
            }
        } catch (\Throwable $e) {
            Log::error("Ошибка при отправке сообщения в Telegram: {$e->getMessage()}");
        }
    }
}
