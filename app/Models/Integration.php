<?php

namespace App\Models;

use App\DTO\TariffData;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * App\Models\Integration
 *
 * @property int $id
 * @property string $title
 * @property string|null $email
 * @property string|null $city
 * @property string|null $country
 * @property string|null $api_key
 * @property string|null $company_id
 * @property string|null $secret
 * @property string|null $telegram_chat_id
 * @property string $notify_type
 * @property bool $active
 * @property bool $active_tg_notify_client
 * @property bool $active_tg_notify_admin
 * @property int|null $provider_id
 * @property int|null $prev_timestamp
 * @property string|null $tag
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read Provider|null $provider
 * @property-read Collection|Call[] $calls
 * @property-read int|null $calls_count
 *
 * @method static Builder|Integration active()
 * @method static Builder|Integration binotel()
 * @method static Builder|Integration zadarma()
 *
 * @method static self|null find($id, $columns = ['*'])
 * @method static self findOrFail($id, $columns = ['*'])
 * @method static self first($columns = ['*'])
 * @method static self firstOrFail($columns = ['*'])
 */
class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'email',
        'city',
        'country',
        'provider_id',
        'api_key',
        'company_id',
        'secret',
        'telegram_chat_id',
        'notify_type',
        'active',
        'active_tg_notify_client',
        'active_tg_notify_admin',
        'prev_timestamp',
        'tag',
        'comment',
    ];

    public function calls()
    {
        return $this->hasMany(Call::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function tariff()
    {
        return $this->belongsTo(Tariff::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Scope для интеграций с провайдером Binotel
     * @param $query
     * @return mixed
     */
    public function scopeBinotel($query)
    {
        return $query->whereHas('provider', function ($q) {
            $q->where('name', Provider::BINOTEL);
        });
    }

    /**
     * Scope для интеграций с провайдером Zadarma
     * @param $query
     * @return mixed
     */
    public function scopeZadarma($query)
    {
        return $query->whereHas('provider', function ($q) {
            $q->where('name', Provider::ZADARMA);
        });
    }

    /**
     * Scope для интеграций с провайдером Unitalk
     * @param $query
     * @return mixed
     */
    public function scopeUnitalk($query)
    {
        return $query->whereHas('provider', function ($q) {
            $q->where('name', Provider::UNITALK);
        });
    }

        /**
     * Scope для интеграций с провайдером Phonet
     * @param $query
     * @return mixed
     */
    public function scopePhonet($query)
    {
        return $query->whereHas('provider', function ($q) {
            $q->where('name', Provider::PHONET);
        });
    }

    public function getTariffData(): TariffData
    {
        $currency = null;
        $priceOfMinute = 0;

        switch ($this->country) {
            case 'Россия':
                $priceOfMinute = $this->tariff->price_ru;
                $currency = 'RUB';
                break;
            case 'Украина':
                $priceOfMinute = $this->tariff->price_ua;
                $currency = 'UAH';
                break;
            case 'Казахстан':
                $priceOfMinute = $this->tariff->price_kz;
                $currency = 'KZT';
                break;
            default:
                Log::error("Для страны $this->country отсутствуют цена в тарифе!");
        }

        return new TariffData(
            $this->tariff->name,
            $currency,
            $priceOfMinute
        );
    }
}
