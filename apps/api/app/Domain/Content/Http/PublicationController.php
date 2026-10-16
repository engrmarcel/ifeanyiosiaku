<?php

namespace App\Domain\Content\Http;

use App\Domain\Content\Models\Publication;
use App\Domain\Content\PublicationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicationController
{
    public function __construct(private readonly PublicationPresenter $presenter) {}

    /** Published publications, optionally of one ?type=. */
    public function index(Request $request): JsonResponse
    {
        $type = is_string($request->query('type')) && array_key_exists($request->query('type'), Publication::TYPES)
            ? $request->query('type')
            : null;

        $items = Publication::query()->published()
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->with(['cover.variants', 'versions'])
            ->orderBy('sort_order')->latest('published_at')
            ->limit(100)
            ->get();

        $types = Publication::query()->published()->distinct()->pluck('type')
            ->filter(fn ($t) => isset(Publication::TYPES[$t]))
            ->map(fn ($t) => ['type' => $t, 'label' => Publication::TYPES[$t]])->values()->all();

        return response()->json([
            'data' => $items->map(fn (Publication $p) => $this->presenter->card($p))->values()->all(),
            'filters' => ['types' => $types, 'active' => ['type' => $type]],
        ])->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }

    public function show(string $slug): JsonResponse
    {
        $publication = Publication::query()->published()->where('slug', $slug)->first();

        if ($publication === null) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'Publication not found.']], 404);
        }

        return response()->json(['data' => $this->presenter->full($publication)])
            ->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
    }
}
