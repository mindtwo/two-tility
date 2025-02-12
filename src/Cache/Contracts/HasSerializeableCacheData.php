<?php

namespace mindtwo\TwoTility\Cache\Contracts;

use mindtwo\TwoTility\Cache\Queue\SerializedCache;

interface HasSerializeableCacheData
{
    /**
     * Get all loaded data caches for the model serialized.
     *
     * @return array<string, SerializedCache>
     */
    public function getSerializedDataCaches(): array;

    /**
     * Load all data caches for the model from serialized data.
     *
     * @param  array<string, mixed>  $serializedCaches
     * @return void
     */
    public function loadCachesWithUnserializedData(array $serializedCaches);
}
