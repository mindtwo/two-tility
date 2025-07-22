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
