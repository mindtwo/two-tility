<?php

namespace mindtwo\TwoTility\Cache\Queue;

use Illuminate\Contracts\Database\ModelIdentifier;

class CacheModelIdentifier extends ModelIdentifier
{
    /**
     * The serialized caches.
     *
     * @var array<string, SerializedCache>
     */
    public $serializedCaches;

    /**
     * Create a new model identifier.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $class
     * @param  mixed  $id
     * @param  mixed  $connection
     * @param  array<mixed>  $relations
     * @param  array<string, SerializedCache>  $serializedCaches
     * @return void
     */
    public function __construct(
        $class, $id, array $relations, $connection, $serializedCaches
    ) {
        parent::__construct($class, $id, $relations, $connection);

        $this->serializedCaches = $serializedCaches;
    }
}
