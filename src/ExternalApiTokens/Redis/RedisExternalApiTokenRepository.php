<?php

namespace mindtwo\TwoTility\ExternalApiTokens\Redis;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redis;
use mindtwo\TwoTility\ExternalApiTokens\Contracts\ExternalApiTokenRepository;
use RuntimeException;

class RedisExternalApiTokenRepository implements ExternalApiTokenRepository
{
    /**
     * Cached token data.
     *
     * @var array<string, array|null>
     */
    protected array $tokenData;

    /**
     * Create a new repository instance.
     *
     * @param string $apiName The name of the external API
     * @param array<string, string> $keyMapping Mapping of standard keys to API-specific keys
     */
    public function __construct(
        protected string $apiName,
        protected array $keyMapping,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function current(Authenticatable $authenticatable): array
    {
        $data = $this->getTokenData($authenticatable);

        throw_if(! $data, new RuntimeException('Error resolving the token.'));

        return $data['token_data'] ?? [];
    }

    /**
     * {@inheritDoc}
     *
     * @return mixed
     */
    public function save(Authenticatable $authenticatable, array $token)
    {
        // Invalidate existing token if it exists
        $this->invalidate($authenticatable);

        $key = $this->buildRedisKey($authenticatable);
        $validUntil = $this->parseValidUntil($token);

        $data = [
            'token_data' => $token,
            'valid_until' => $validUntil?->toIso8601String(),
        ];

        // Calculate TTL in seconds
        $ttl = $validUntil ? (int) $validUntil->diffInSeconds(now()) : null;

        // Encrypt data before storing
        $encrypted = Crypt::encryptString(json_encode($data));

        if ($ttl && $ttl > 0) {
            Redis::setex($key, $ttl, $encrypted);
        } else {
            Redis::set($key, $encrypted);
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function invalidate(Authenticatable $authenticatable): bool
    {
        $key = $this->buildRedisKey($authenticatable);

        return Redis::del($key) > 0;
    }

    /**
     * {@inheritDoc}
     */
    public function refresh(Authenticatable $authenticatable): bool
    {
        // This method would typically call an external API to refresh the token
        // The implementation will depend on the specific API integration
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function isCurrentTokenValid(Authenticatable $authenticatable): bool
    {
        try {
            $expiresAt = $this->expiresAt($authenticatable);

            return $expiresAt?->isFuture() ?? false;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function canRefreshCurrentToken(Authenticatable $authenticatable): bool
    {
        return !empty($this->refreshToken($authenticatable));
    }

    /**
     * {@inheritDoc}
     */
    public function accessToken(Authenticatable $authenticatable): string
    {
        $data = $this->getTokenData($authenticatable);

        throw_if(! $data, new RuntimeException('No token found for authenticatable'));

        $tokenData = $data['token_data'] ?? [];
        $accessToken = $tokenData[$this->mappedKey('access_token')] ?? null;

        throw_if(! $accessToken, new RuntimeException('No access token found in token data'));

        return $accessToken;
    }

    /**
     * {@inheritDoc}
     */
    public function expiresAt(Authenticatable $authenticatable): ?Carbon
    {
        $data = $this->getTokenData($authenticatable);

        throw_if(! $data, new RuntimeException('No token found for authenticatable'));

        $validUntil = $data['valid_until'] ?? null;

        return $validUntil ? Carbon::parse($validUntil) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshToken(Authenticatable $authenticatable): ?string
    {
        $data = $this->getTokenData($authenticatable);

        if (! $data) {
            return null;
        }

        $tokenData = $data['token_data'] ?? [];

        return $tokenData[$this->mappedKey('refresh_token')] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshTokenValidUntil(Authenticatable $authenticatable): ?Carbon
    {
        $data = $this->getTokenData($authenticatable);

        if (! $data) {
            return null;
        }

        $tokenData = $data['token_data'] ?? [];
        $validUntil = $tokenData[$this->mappedKey('refresh_token_valid_until')] ?? null;

        if ($validUntil instanceof Carbon) {
            return $validUntil;
        }

        if (is_string($validUntil)) {
            return Carbon::parse($validUntil);
        }

        return null;
    }

    /**
     * Build the Redis key for the token.
     */
    protected function buildRedisKey(Authenticatable $authenticatable): string
    {
        return cache_key('external_api_tokens')
            ->addParam('type', get_class($authenticatable))
            ->addParam('id', $authenticatable->getAuthIdentifier())
            ->addParam('api', $this->apiName)
            ->toString();
    }

    /**
     * Get token data from Redis.
     */
    protected function getTokenData(Authenticatable $authenticatable): ?array
    {
        if (isset($this->tokenData)) {
            return $this->tokenData;
        }

        $key = $this->buildRedisKey($authenticatable);
        $encrypted = Redis::get($key);

        if (! $encrypted) {
            return null;
        }

        // Decrypt data after retrieving
        $decrypted = Crypt::decryptString($encrypted);
        $this->tokenData = json_decode($decrypted, true);

        return $this->tokenData;
    }

    /**
     * Parse the valid_until timestamp from token data.
     */
    protected function parseValidUntil(array $token): ?Carbon
    {
        $expiresAtKey = $this->keyMapping['expires_at'] ?? null;
        $expiresInKey = $this->keyMapping['expires_in'] ?? null;

        throw_if(! $expiresAtKey && ! $expiresInKey, new RuntimeException('No mapping configured for expires_at or expires_in'));

        if (isset($token[$expiresAtKey])) {
            return $token[$expiresAtKey] instanceof Carbon
                ? $token[$expiresAtKey]
                : Carbon::parse($token[$expiresAtKey]);
        }

        if (isset($token[$expiresInKey])) {
            return now()->addSeconds($token[$expiresInKey]);
        }

        return null;
    }

    /**
     * Get mapped key from key mapping.
     */
    protected function mappedKey(string $key): string
    {
        throw_if(! isset($this->keyMapping[$key]), new RuntimeException("No mapping configured for $key"));

        return $this->keyMapping[$key];
    }
}
