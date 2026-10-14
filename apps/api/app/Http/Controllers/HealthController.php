<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Enums\Permission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Health check. Anonymous callers get only an overall status; staff with the
 * system_health.view permission also see individual checks and queue metrics.
 */
class HealthController
{
    public const SCHEDULER_HEARTBEAT_KEY = 'scheduler:heartbeat';

    public function __invoke(Request $request): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                $key = 'health:'.Str::random(8);
                Cache::put($key, 'ok', 10);
                $ok = Cache::pull($key) === 'ok';
                throw_unless($ok, new \RuntimeException('Cache round-trip failed.'));
            }),
        ];

        $healthy = ! in_array(false, array_column($checks, 'ok'), true);
        $body = ['status' => $healthy ? 'ok' : 'degraded'];

        if ($request->user()?->can(Permission::SystemHealthView->value)) {
            $heartbeat = Cache::get(self::SCHEDULER_HEARTBEAT_KEY);

            $body['checks'] = $checks;
            $body['queue'] = [
                'pending_jobs' => DB::table('jobs')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ];
            $body['scheduler'] = [
                'last_heartbeat_at' => $heartbeat,
                'healthy' => $heartbeat !== null && Carbon::parse($heartbeat)->gt(now()->subMinutes(5)),
            ];
        }

        return response()->json($body, $healthy ? 200 : 503);
    }

    /**
     * @return array{ok: bool, error?: string}
     */
    private function check(callable $probe): array
    {
        try {
            $probe();

            return ['ok' => true];
        } catch (Throwable $e) {
            report($e);

            return ['ok' => false, 'error' => class_basename($e)];
        }
    }
}
