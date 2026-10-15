<?php

namespace App\Domain\Settings\Events;

use Illuminate\Foundation\Events\Dispatchable;

class SettingChanged
{
    use Dispatchable;

    public function __construct(public readonly string $key) {}
}
