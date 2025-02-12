<?php

namespace mindtwo\TwoTility\Tests\Mock\Job;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use mindtwo\TwoTility\Cache\Queue\SerializesModelsWithCache;
use mindtwo\TwoTility\Tests\Mock\CachedAttributesModel;

class TestSerializationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModelsWithCache;

    public function __construct(
        public CachedAttributesModel $model
    ) {}

    public function handle() {}
}
