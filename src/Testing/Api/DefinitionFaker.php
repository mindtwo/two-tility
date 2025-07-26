<?php

namespace mindtwo\TwoTility\Testing\Api;

use Closure;
use Faker\Factory as FakerFactory;
use Illuminate\Support\Collection;
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

        if (is_array($value) || $value instanceof Collection) {
            return $this->faker->randomElement($value);
        }

        if ($value instanceof Closure) {
            return $value();
        }

        if (is_string($value)) {
            try {
                return $this->faker->format($value);
            } catch (\InvalidArgumentException) {
                return $value;
            }
        }

        return $value;
    }
}
