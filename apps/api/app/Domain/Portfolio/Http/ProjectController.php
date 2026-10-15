<?php

namespace App\Domain\Portfolio\Http;

use App\Domain\Cms\PageCache;
use App\Domain\Portfolio\Blocks\ProjectPresenter;
use App\Domain\Portfolio\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ProjectController
{
    /** A published case study, e.g. /api/v1/projects/sales-dashboard. */
    public function show(string $slug): JsonResponse
    {
        $payload = Cache::remember(PageCache::key('/projects/'.$slug), now()->addDay(), function () use ($slug) {
            $project = Project::query()->published()->with('cover.variants')->where('slug', $slug)->first();

            return $project ? ProjectPresenter::full($project) : null;
        });

        if ($payload === null) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Project not found.']], 404);
        }

        return response()->json(['data' => $payload])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
