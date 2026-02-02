<?php

namespace mindtwo\TwoTility\ExternalApiTokens\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;

interface ExternalApiTokenRepository
{
    /**
     * Get current token data for Authenticatable.
     *
     * @param Authenticatable $authenticatable
     * @return array
     */
    public function current(Authenticatable $authenticatable): array;

    /**
     * Persist token data inside storage.
     *
     * @param Authenticatable $authenticatable
     * @param array $token
     * @return mixed
     */
    public function save(Authenticatable $authenticatable, array $token);

    /**
     * Invalidate current token for Authenticatable.
     *
     * @param Authenticatable $authenticatable
     * @return boolean
     */
    public function invalidate(Authenticatable $authenticatable): bool;

    /**
     * Refresh tokens for Authenticatable.
     *
     * @param string $refreshToken
     * @return boolean
     */
    public function refresh(string $refreshToken): bool;

    /**
     * Check if current token is valid for Authenticatable.
     *
     * @param Authenticatable $authenticatable
     * @return boolean
     */
    public function isCurrentTokenValid(Authenticatable $authenticatable): bool;

    /**
     * Check if the current token can be refreshed.
     *
     * @param Authenticatable $authenticatable
     * @return boolean
     */
    public function canRefreshCurrentToken(Authenticatable $authenticatable): bool;

    /**
     * Get the access token for Authenticatable.
     *
     * @param Authenticatable $authenticatable
     * @return string
     */
    public function accessToken(Authenticatable $authenticatable): string;

    /**
     * Get the token expiration time for Authenticatable.
     *
     * @param Authenticatable $authenticatable
     * @return ?Carbon
     */
    public function expiresAt(Authenticatable $authenticatable): ?Carbon;

    /**
     * Get the refresh token for Authenticatable.
     *
     * @param Authenticatable $authenticatable
     * @return string|null
     */
    public function refreshToken(Authenticatable $authenticatable): ?string;

    /**
     * Get the refresh token expiration time for Authenticatable.
     *
     * @param Authenticatable $authenticatable
     * @return Carbon|null
     */
    public function refreshTokenValidUntil(Authenticatable $authenticatable): ?Carbon;
}
