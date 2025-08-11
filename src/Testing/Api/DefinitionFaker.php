<?php

namespace mindtwo\TwoTility\Testing\Api;

use Closure;
use Faker\Factory as FakerFactory;
use UnitEnum;

class DefinitionFaker
{
    protected \Faker\Generator $faker;

    public function __construct(?\Faker\Generator $faker = null)
    {
        $this->faker = $faker ?? FakerFactory::create();
    }

    /**
     * Generate a fake definition based on the provided structure.
     *
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    public function make(array $definition): array
    {
        $result = [];

        foreach ($definition as $key => $value) {
            $result[$key] = $this->resolve($value);
        }

        return $result;
    }

    /**
     * Resolve the value based on its type.
     */
    protected function resolve(mixed $value): mixed
    {
        if (is_subclass_of($value, UnitEnum::class)) {
            return $this->faker->randomElement($value::cases());
        }

        if (is_array($value)) {
            // Recurse arrays and associative maps
            return array_map(fn ($v) => $this->resolve($v), $value);
        }

        if ($value instanceof Closure) {
            return $value();
        }

        if (is_string($value)) {
            return $this->resolveExpression($value);
        }

        return $value;
    }

    /**
     * Resolve a string expression to a Faker value.
     *
     * Supports function-style expressions like 'func(arg)' or 'func()'.
     * Falls back to standard Faker formatters if the expression is not recognized.
     */
    protected function resolveExpression(string $expression): mixed
    {
        // Match function-style: func(arg) or func()
        if (preg_match('/^(?<func>\w+)\((?<args>.*)\)$/', $expression, $match)) {
            $function = $match['func'];
            $args = trim($match['args']);

            return match ($function) {
                'randomElement' => $this->faker->randomElement(json_decode($args, true)),
                'optional' => $this->faker->optional()->{$args}(),
                default => $this->faker->{$function}(...$this->parseArgs($args)),
            };
        }

        // fallback to standard formatters: 'uuid', 'email', etc.
        try {
            return $this->faker->format($expression);
        } catch (\Throwable) {
            return $expression;
        }
    }

    /**
     * Parse arguments from a string into an array.
     *
     * Handles both single and multiple arguments, trimming whitespace.
     * Supports quoted strings, numbers, booleans, and null values.
     *
     * @return array<mixed>
     */
    protected function parseArgs(string $args): array
    {
        $args = trim($args);

        // Try full JSON decode if single array
        if (str_starts_with($args, '[') && str_ends_with($args, ']')) {
            $decoded = json_decode($args, true);

            return is_array($decoded) ? $decoded : [$args];
        }

        // Try decoding a list of arguments: supports "quoted", numbers, etc.
        $result = [];
        $pattern = '/
            (?:\s*)               # optional whitespace
            (                     # capture group
                "(?:[^"\\\\]*(?:\\\\.[^"\\\\]*)*)"  # quoted string with escaping
                | \'(?:[^\']*)\'  # single-quoted string
                | [^,]+           # unquoted word or number
            )
            (?:\s*,\s*|$)         # separator or end
        /x';

        preg_match_all($pattern, $args, $matches);

        foreach ($matches[1] as $arg) {
            $arg = trim($arg, '"\' ');

            // Try cast
            if (is_numeric($arg)) {
                $result[] = strpos($arg, '.') !== false ? (float) $arg : (int) $arg;
            } elseif (strtolower($arg) === 'null') {
                $result[] = null;
            } elseif (strtolower($arg) === 'true') {
                $result[] = true;
            } elseif (strtolower($arg) === 'false') {
                $result[] = false;
            } else {
                $result[] = $arg;
            }
        }

        return $result;
    }
}
