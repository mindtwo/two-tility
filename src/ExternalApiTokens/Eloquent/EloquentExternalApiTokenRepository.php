<?php

namespace mindtwo\TwoTility\ExternalApiTokens\Eloquent;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use mindtwo\TwoTility\ExternalApiTokens\Contracts\ExternalApiTokenRepository;
use RuntimeException;

class EloquentExternalApiTokenRepository implements ExternalApiTokenRepository
{

    private ExternalApiToken $current;

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
        $token = $this->getToken($authenticatable);

        throw_if(! $token?->token_data, new RuntimeException('Error resolving the token.'));

        return $token->token_data;
    }

    /**
     * {@inheritDoc}
     *
     * @return ExternalApiToken
     */
    public function save(Authenticatable $authenticatable, array $token)
    {
        // Invalidate existing token if it exists
        $this->invalidate($authenticatable);

        $validUntil = $this->parseValidUntil($token);

        return ExternalApiToken::query()
            ->create([
                'authenticatable_type' => get_class($authenticatable),
                'authenticatable_id' => $authenticatable->getAuthIdentifier(),
                'api_name' => $this->apiName,
                'token_data' => $token,
                'valid_until' => $validUntil,
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function invalidate(Authenticatable $authenticatable): bool
    {
        return ExternalApiToken::query()
            ->forAuthenticatable($authenticatable)
            ->forApi($this->apiName)
            ->update([
                'valid_until' => now(),
            ]) > 0;
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

            return $expiresAt->isFuture();
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
        $token = $this->getToken($authenticatable);

        throw_if(! $token, new RuntimeException('No token found for authenticatable'));

        return $token->token($this->mappedKey('access_token'));
    }

    /**
     * {@inheritDoc}
     */
    public function expiresAt(Authenticatable $authenticatable): ?Carbon
    {
        $token = $this->getToken($authenticatable);

        throw_if(! $token, new RuntimeException('No token found for authenticatable'));

        return $token->valid_until;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshToken(Authenticatable $authenticatable): ?string
    {
        $token = $this->getToken($authenticatable);

        return $token?->token($this->mappedKey('refresh_token'));
    }

    /**
     * {@inheritDoc}
     */
    public function refreshTokenValidUntil(Authenticatable $authenticatable): ?Carbon
    {
        $token = $this->getToken($authenticatable);
        $validUntil = $token?->token($this->mappedKey('refresh_token_valid_until'));

        if ($validUntil instanceof Carbon) {
            return $validUntil;
        }

        if (is_string($validUntil)) {
            return Carbon::parse($validUntil);
        }

        return null;
    }

    /**
     * Get the token model for the authenticatable.
     */
    protected function getToken(Authenticatable $authenticatable): ?ExternalApiToken
    {
        if (isset($this->current)) {
            return $this->current;
        }

        $this->current = ExternalApiToken::query()
            ->forAuthenticatable($authenticatable)
            ->forApi($this->apiName)
            ->latest()
            ->first();

        return $this->current;
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

    protected function mappedKey(string $key): string
    {
        throw_if(! isset($this->keyMapping[$key]), new RuntimeException("No mapping configured for $key"));

        return $this->keyMapping[$key];
    }
}
