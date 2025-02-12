<?php

namespace mindtwo\TwoTility\Tests\Mock;

use mindtwo\TwoTility\Tests\Mock\Cache\CachedAttributesDataCache;

class CachedAttributesModel extends \Illuminate\Database\Eloquent\Model implements \mindtwo\TwoTility\Cache\Contracts\HasSerializeableCacheData
{
    use \mindtwo\TwoTility\Cache\Models\HasCachedAttributes;

    protected $table = 'cached_attributes';

    public $timestamps = false;

    public $loadOnAccess = true;

    public $allowEmpty = true;

    public function getDataCaches(): ?array
    {
        return [
            'data' => CachedAttributesDataCache::class,
        ];
    }
}
