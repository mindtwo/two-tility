<?php

use Illuminate\Support\Carbon;
use mindtwo\TwoTility\Testing\Api\DefinitionFaker;

enum GenderEnum: string
{
    case MALE = 'male';
    case FEMALE = 'female';
    case OTHER = 'other';
}

it('creates an array based on passed definition', function () {
    $faker = new DefinitionFaker;

    $data = $faker->make([
        'firstname' => 'firstName',
        'familyname' => 'lastName',
        'birthday' => 'date',
        'place' => 'city',
        'gender' => GenderEnum::class,
        'hobbies' => 'randomElement(["reading", "coding", "gaming"])',
        'resolved' => fn () => strtoupper('custom value'),
    ]);

    expect($data)->toBeArray()
        ->toHaveKeys(['firstname', 'familyname', 'birthday', 'place', 'gender', 'hobbies', 'resolved'])
        ->and($data['firstname'])->toBeString()
        ->and($data['familyname'])->toBeString()
        ->and($data['birthday'])->toBeString()
        ->and(Carbon::parse($data['birthday']))->toBeInstanceOf(Carbon::class)
        ->and($data['place'])->toBeString()
        ->and($data['gender'])->toBeInstanceOf(GenderEnum::class)
        ->and($data['hobbies'])->toBeString()
        ->and($data['hobbies'])->toBeIn(['reading', 'coding', 'gaming'])
        ->and($data['resolved'])->toBe('CUSTOM VALUE');
});
