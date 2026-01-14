<?php

namespace mindtwo\TwoTility\ExternalApiTokens\Eloquent;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * External API Token Model
 *
 * Stores OAuth tokens and refresh tokens for external API integrations.
 * Tokens are encrypted at rest and associated with an authenticatable entity
 * via a polymorphic relationship.
 *
 * @property string $api_name
 * @property array $token_data
 * @property Carbon|null $valid_until
 * @property string $authenticatable_type
 * @property int $authenticatable_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static Builder forApi(string $apiName)
 * @method static Builder forAuthenticatable(Authenticatable $authenticatable)
 * @method static Builder valid()
 */
class ExternalApiToken extends Model
{
    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'api_name',
        'token_data',
        'valid_until',
    ];

    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'token_data' => 'encrypted:array',
        'valid_until' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the token data.
     */
    public function token(string $key): mixed
    {
        return data_get($this->token_data, $key);
    }

    /**
     * Get the authenticatable that owns the token.
     */
    public function authenticatable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include tokens for a specific API.
     */
    public function scopeForApi(Builder $query, string $apiName): Builder
    {
        return $query->where('api_name', $apiName);
    }

    /**
     * Scope a query to only include tokens for a specific authenticatable.
     */
    public function scopeForAuthenticatable(Builder $query, Authenticatable $authenticatable): Builder
    {
        return $query->where('authenticatable_type', get_class($authenticatable))
            ->where('authenticatable_id', $authenticatable->getAuthIdentifier());
    }

    /**
     * Scope a query to only include valid tokens.
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->whereNull('valid_until')
                ->orWhere('valid_until', '>', now());
        });
    }

}
