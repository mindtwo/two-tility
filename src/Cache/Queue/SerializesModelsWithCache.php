<?php

namespace mindtwo\TwoTility\Cache\Queue;

use Illuminate\Contracts\Database\ModelIdentifier;
use Illuminate\Contracts\Queue\QueueableCollection;
use Illuminate\Contracts\Queue\QueueableEntity;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Queue\SerializesModels;
use mindtwo\TwoTility\Cache\Contracts\HasSerializeableCacheData;

trait SerializesModelsWithCache
{
    use SerializesModels {
        __serialize as protected __serializeModels;
        __unserialize as protected __unserializeModels;
    }

    /**
     * Get the property value prepared for serialization.
     *
     * @param  mixed  $value
     * @param  bool  $withRelations
     * @return mixed
     */
    protected function getSerializedPropertyValue($value, $withRelations = true)
    {
        if ($value instanceof QueueableCollection) {
            return $this->getSerializedEntityCollection($value, $withRelations);
        }

        if ($value instanceof QueueableEntity) {
            return $this->getSerializedEntity($value, $withRelations);
        }

        return $value;
    }

    protected function getSerializedEntityCollection($value, $withRelations = true)
    {
        // TODO collection
        return (new ModelIdentifier(
            $value->getQueueableClass(),
            $value->getQueueableIds(),
            $withRelations ? $value->getQueueableRelations() : [],
            $value->getQueueableConnection()
        ))->useCollectionClass(
            ($collectionClass = get_class($value)) !== EloquentCollection::class
                    ? $collectionClass
                    : null
        );
    }

    /**
     * Get the property value prepared for serialization.
     *
     * @param  QueueableEntity  $value
     * @param  bool  $withRelations
     * @return mixed
     */
    protected function getSerializedEntity($value, $withRelations = true)
    {
        if ($value instanceof HasSerializeableCacheData) {
            return new CacheModelIdentifier(
                get_class($value),
                $value->getQueueableId(),
                $withRelations ? $value->getQueueableRelations() : [],
                $value->getQueueableConnection(),
                $value->getSerializedDataCaches()
            );
        }

        return new ModelIdentifier(
            get_class($value),
            $value->getQueueableId(),
            $withRelations ? $value->getQueueableRelations() : [],
            $value->getQueueableConnection()
        );
    }

    protected function restoreEntity(ModelIdentifier $value)
    {
        $model = $this->restoreModel($value);

        if ($value instanceof CacheModelIdentifier && $model instanceof HasSerializeableCacheData) {
            $model->loadCachesWithUnserializedData($value->serializedCaches);
        }

        return $model;
    }

    /**
     * Get the restored property value after deserialization.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function getRestoredPropertyValue($value)
    {
        if (! $value instanceof ModelIdentifier) {
            return $value;
        }

        if (is_array($value->id)) {
            return $this->restoreCollection($value);
        }

        return $this->restoreEntity($value);
    }
}
