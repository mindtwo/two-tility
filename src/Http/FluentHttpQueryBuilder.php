<?php

namespace mindtwo\TwoTility\Http;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Conditionable;
use Stringable;

// TODO - write test
class FluentHttpQueryBuilder implements Stringable, Arrayable
{
    use Conditionable;

    private array $params = [];

    private array $defaults = [];

    private function __construct(
        private Closure $request,
    ){
    }

    public static function make(Closure $request): self
    {
        return new self($request);
    }

    /**
     * Get query results
     */
    public function get(): mixed
    {
        return ($this->request)($this->__toString());
    }

    /**
     * @param  \Closure|string|int|array<array-key, string>|null  $value
     * @param ?string $key
     * @return array
     */
    public function pluck($value, ?string $key = null): array
    {
        $response = $this->get();

        if (! is_array($response) && ! $response instanceof Collection) {
            return [];
        }

        return collect($response)->pluck($value, $key)->toArray();
    }

    /**
     * Add param to HttpQuery
     *
     * @param string $param
     * @param mixed $value
     * @return static
     */
    public function add(string $param, mixed $value): static
    {
        $this->params[$param] = $value;

        return $this;
    }

    /**
     * Set default params used if not overriden.
     *
     * @param array $defaultParams
     * @return static
     */
    public function defaults(array $defaultParams): static
    {
        $this->defaults = $defaultParams;

        return $this;
    }

    /**
     * Remove param again.
     *
     * @param string $param
     * @return static
     */
    public function remove(string $param): static
    {
        $this->params[$param] = null;

        return $this;
    }

    /**
     * Convert to string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return http_build_query($this->toArray());
    }

    /**
     * Get the instance as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        return collect(array_merge($this->defaults, $this->params))
            ->filter(fn ($value) => !empty($value) || is_bool($value))
            ->mapWithKeys(function ($value, string $key) {

                if ($value instanceof QueryParameterable) {
                    return [$key => $value->toQueryParam()];
                }

                if (is_array($value)) {
                    return [$key => $value];
                }

                if (is_bool($value)) {
                    $stringValue = $value ? '1' : '0';
                } else {
                    $stringValue = (string) $value;
                }

                return [$key => $stringValue];

            })
            ->toArray();
    }
}
