<?php

namespace App\Domain\Settings\Http;

use App\Domain\Settings\SettingsRepository;
use Illuminate\Http\JsonResponse;

class PublicSettingsController
{
    public function __invoke(SettingsRepository $settings): JsonResponse
    {
        return response()->json(['data' => $settings->public()]);
    }
}
