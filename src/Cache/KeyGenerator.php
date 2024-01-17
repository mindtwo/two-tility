<?php

namespace mindtwo\TwoTility\Cache;

use Illuminate\Support\Facades\Log;
use JsonSerializable;
use Stringable;

class KeyGenerator implements JsonSerializable, Stringable
{
    /**
     * Additional parameters for cache key.
     *
     * @var array<string, mixed>
     */
    protected array $additionalParams = [];

    private bool $dontHash = false;

    /**
     * Create a new KeyGenerator instance.
     *
     * @param string $name - The name of the cache key.
     * @param array<string, mixed>|null $options - Additional options for cache key. If value is either 'header', 'auth' or 'param' the param is added via respective method.
     */
    private function __construct(
        protected string $name,
        ?array $options = null
    ) {
        if (is_null($options)) {
            return;
        }

        foreach ($options as $key => $value) {
            // add via method
            if (in_array($value, ['header', 'auth', 'param'])) {
                $this->{'add'.ucfirst($value)}($key);

                continue;
            }

            $this->addParam($key, $value);
        }
    }

    /**
     * Add additional parameter to cache key.
     */
    public function addParam(string $key, ?string $value = null): self
    {
        $this->additionalParams[$key] = $value ?? $key;

        return $this;
    }

    /**
     * Remove additional parameter from cache key.
     */
    public function removeParam(string $key): self
    {
        unset($this->additionalParams[$key]);

        return $this;
    }

    /**
     * Add header value to cache key.
     */
    public function addHeader(string|array $names): self
    {
        if (is_array($names)) {
            foreach ($names as $name) {
                $this->addHeader($name);
            }

            return $this;
        }

        return $this->addParam($names, request()->header($names));
    }

    /**
     * Add auth value to cache key.
     * Adds auth_id and updated_at timestamp of user to params.
     */
    public function addAuth(): self
    {
        return $this->addParam('auth_id', auth()->user()?->id)
            ->addParam('auth_updated_at', auth()->user()?->updated_at->timestamp);
    }

    /**
     * Add param value to cache key if $condition is true.
     */
    public function addParamIf(bool $condition, string $key, ?string $value = null): self
    {
        if ($condition) {
            $this->addParam($key, $value);
        }

        return $this;
    }

    /**
     * Don't hash cache key if $condition is true.
     */
    public function debugIf(bool $condition): self
    {
        if ($condition) {
            $this->debug();
        }

        return $this;
    }

    /**
     * Cache key is not hashed for debug purposes.
     */
    public function debug(): self
    {
        $this->dontHash = true;

        return $this;
    }

    /**
     * Get filtered parameters. Remove empty values.
     */
    protected function getFilteredParams(): array
    {
        return array_filter(array_merge([
            'name' => $this->name,
        ], $this->additionalParams));
    }

    /**
     * Get cache key as string.
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->dontHash) {
            return implode(':', array_values($this->getFilteredParams()));
        }

        return md5(implode(':', array_values($this->getFilteredParams())));
    }

    /**
     * Get cache key as json.
     */
    public function jsonSerialize(): mixed
    {
        return $this->__toString();
    }

    /**
     * Get cache key as json.
     */
    public static function make(string $name, array $options = []): self
    {
        return new static($name, $options);
    }
}
