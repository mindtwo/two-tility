<?php

it('can temporarily change a scoped value', function () {
    $originalValue = 'original';
    $temporaryValue = 'temporary';

    $getter = function () use (&$originalValue) {
        return $originalValue;
    };

    $setter = function ($value) use (&$originalValue) {
        $originalValue = $value;
    };

    $result = withTemporaryScope(function () use ($getter) {
        return $getter();
    }, $getter, $setter, $temporaryValue);

    expect($result)->toEqual($temporaryValue);
    expect($getter())->toEqual($originalValue);
});

it('can be used on config', function () {
    $originalValue = config('app.name');
    $temporaryValue = 'Temporary App Name';

    $getter = function () {
        return config('app.name');
    };

    $setter = function ($value) {
        config(['app.name' => $value]);
    };

    $result = withTemporaryScope(function () use ($getter) {
        return $getter();
    }, $getter, $setter, $temporaryValue);

    expect($result)->toEqual($temporaryValue);
    expect(config('app.name'))->toEqual($originalValue);
});

it('can be used on objects', function () {
    $object = new class
    {
        private $property = 'original';

        public function getProperty()
        {
            return $this->property;
        }

        public function setProperty($value)
        {
            $this->property = $value;
        }
    };

    $temporaryValue = 'temporary';

    $getter = function () use ($object) {
        return $object->getProperty();
    };

    $result = withTemporaryScope(
        function () use ($getter) {
            return $getter();
        },
        $getter,
        function ($value) use ($object) {
            $object->setProperty($value);
        }, $temporaryValue);

    expect($result)->toEqual($temporaryValue);
    expect($object->getProperty())->toEqual('original');
});
