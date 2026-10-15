<?php

/**
 * The staff two-factor requirement defaults to OFF outside production and ON in production
 * (ADR-026), with REQUIRE_STAFF_TWO_FACTOR as an explicit override.
 */
function staffTwoFactorDefault(string $appEnv, ?string $override): bool
{
    $saved = [getenv('APP_ENV'), getenv('REQUIRE_STAFF_TWO_FACTOR')];
    $set = function (string $name, string|false|null $value): void {
        if ($value === null || $value === false) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        } else {
            putenv("{$name}={$value}");
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
    };

    try {
        $set('APP_ENV', $appEnv);
        $set('REQUIRE_STAFF_TWO_FACTOR', $override);
        $config = require dirname(__DIR__, 2).'/config/platform.php';

        return $config['defaults']['security.require_staff_two_factor'];
    } finally {
        $set('APP_ENV', $saved[0]);
        $set('REQUIRE_STAFF_TWO_FACTOR', $saved[1]);
    }
}

it('is off while developing and on in production', function (string $env, bool $expected) {
    expect(staffTwoFactorDefault($env, null))->toBe($expected);
})->with([
    'local' => ['local', false],
    'testing' => ['testing', false],
    'staging' => ['staging', false],
    'production' => ['production', true],
]);

it('treats an empty override as unset', function () {
    expect(staffTwoFactorDefault('production', ''))->toBeTrue()
        ->and(staffTwoFactorDefault('local', ''))->toBeFalse();
});

it('honours an explicit override', function () {
    expect(staffTwoFactorDefault('local', 'true'))->toBeTrue()
        ->and(staffTwoFactorDefault('production', 'false'))->toBeFalse();
});
