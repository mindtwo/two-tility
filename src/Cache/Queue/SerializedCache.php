<?php

namespace mindtwo\TwoTility\Cache\Queue;

class SerializedCache
{
    /**
     * Undocumented function
     *
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $clz,
        public array $data,
        public int|string $model_id,
        public string $model_class,
    ) {}

    // TODO unserialize build func
}
