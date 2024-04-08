<?php

namespace mindtwo\TwoTility\Tests\Mock\Cache;

use mindtwo\TwoTility\Cache\Data\DataCache;

class CachedAttributesDataCache extends DataCache
{

    protected bool $loadOnAccess = true;

    /**
     * Get cache key.
     */
    protected function cacheKey(): string
    {
        return cache_key('data_cache', [
            'class' => class_basename($this),
        ])->toString();
    }

    public function keys(): array
    {
        return [
            'foo',
            'baz',
        ];
    }

    public function cacheData(): array
    {
        return [
            'foo' => 'bar',
            'baz' => 'qux',
        ];
    }
}
