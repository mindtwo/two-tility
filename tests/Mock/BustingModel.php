<?php

namespace mindtwo\TwoTility\Tests\Mock;

use Illuminate\Database\Eloquent\Model;

class BustingModel extends Model
{
    use \mindtwo\TwoTility\Cache\Models\BustsCacheKeys;

    public $timestamps = false;

    protected $guarded = [];

    public $cacheKeysToBust = [
        'updated' => [
            'test-key-1',
        ],
    ];

    public function getCacheKeysToBust(string $event): array
    {
        return $this->cacheKeysToBust[$event];
    }
}
