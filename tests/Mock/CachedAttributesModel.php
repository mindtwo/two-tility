<?php

namespace mindtwo\TwoTility\Tests\Mock;

use Illuminate\Database\Eloquent\Model;
use mindtwo\TwoTility\Cache\Models\HasCachedAttributes;

class CachedAttributesModel extends Model
{
    use HasCachedAttributes;

    protected $table = 'cached_attributes';

    public $timestamps = false;

    public $fillable = [
        'name',
        'parent_id',
    ];

    /**
     * The attributes accessible via this helper.
     *
     * @var list<string>
     */
    protected array $cachableAttributes = [
        'foo',
        'baz',
    ];

    /**
     * Get the parent model (recursive relationship).
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get child models (inverse of recursive relationship).
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
