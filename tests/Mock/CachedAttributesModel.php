<?php

namespace mindtwo\TwoTility\Tests\Mock;

use mindtwo\TwoTility\Tests\Mock\Cache\CachedAttributesDataCache;

class CachedAttributesModel extends \Illuminate\Database\Eloquent\Model
{

    use \mindtwo\TwoTility\Cache\Models\HasCachedAttributes;

    protected $table = 'cached_attributes';

    public $timestamps = false;

    public function getDataCaches(): ?array
    {
        return [
            'data' => [
                'class' => CachedAttributesDataCache::class,
                'loadOnAccess' => true,
            ],
        ];
    }
}
