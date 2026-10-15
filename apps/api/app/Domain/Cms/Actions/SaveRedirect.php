<?php

namespace App\Domain\Cms\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cms\Enums\PageStatus;
use App\Domain\Cms\Events\PageContentChanged;
use App\Domain\Cms\Models\Page;
use App\Domain\Cms\Models\Redirect;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or edits a redirect from an old address to a page path or an https:// address.
 * Refuses addresses the website owns, addresses of live pages (the page would always win), loops
 * and chains, so every redirect is a single, predictable hop.
 */
class SaveRedirect
{
    /** Website routes that are not CMS pages and so can never be redirected. */
    private const WEBSITE_ROUTES = ['page-not-found', 'internal', '_next'];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{from_path?: mixed, to_path?: mixed, status_code?: mixed}  $data
     */
    public function handle(?Redirect $redirect, array $data, ?User $actor): Redirect
    {
        $from = self::normalisePath((string) ($data['from_path'] ?? ''));
        $to = trim((string) ($data['to_path'] ?? ''));
        $status = (int) ($data['status_code'] ?? 301);

        $this->validate($redirect, $from, $to, $status);

        $previousFrom = $redirect?->from_path;

        $saved = DB::transaction(function () use ($redirect, $from, $to, $status, $actor) {
            $redirect ??= new Redirect(['created_by' => $actor?->getKey()]);
            $redirect->fill(['from_path' => $from, 'to_path' => $to, 'status_code' => $status, 'is_automatic' => false])->save();

            // Redirects that pointed at this old address now go straight to the destination.
            Redirect::query()->where('to_path', $from)->whereKeyNot($redirect->getKey())->update(['to_path' => $to]);

            return $redirect;
        });

        $this->audit->record($previousFrom === null ? 'redirect.created' : 'redirect.updated', $saved, [
            'from' => $from, 'to' => $to, 'status' => $status, 'previous_from' => $previousFrom,
        ], $actor);

        PageContentChanged::dispatch(array_values(array_unique(array_filter([$from, $previousFrom]))));

        return $saved;
    }

    /** "About-Us/" → "/about-us". */
    public static function normalisePath(string $path): string
    {
        $path = strtolower(trim($path));
        $path = preg_replace('#^https?://[^/]+#', '', $path) ?? $path;

        return '/'.trim($path, '/');
    }

    private function validate(?Redirect $redirect, string $from, string $to, int $status): void
    {
        $ownId = $redirect?->getKey();

        if ($from === '/' || strlen($from) > 151 || ! preg_match('#^/[a-z0-9]+(?:[a-z0-9\-/]*[a-z0-9])?$#', $from) || str_contains($from, '//')) {
            $this->fail('from_path', 'Use an address on this site such as /old-page (letters, numbers, hyphens and slashes).');
        }

        $segment = explode('/', ltrim($from, '/'))[0];
        if (in_array($segment, [...config('cms.reserved_slugs', []), ...self::WEBSITE_ROUTES], true)) {
            $this->fail('from_path', 'This address belongs to the website itself and cannot be redirected.');
        }

        $taken = Redirect::query()->where('from_path', $from)
            ->when($ownId !== null, fn ($q) => $q->whereKeyNot($ownId))->exists();
        if ($taken) {
            $this->fail('from_path', 'There is already a redirect from this address. Edit that one instead.');
        }

        $livePage = Page::query()->where('status', PageStatus::Published)
            ->whereHas('publishedRevision', fn ($q) => $q->where('slug', ltrim($from, '/')))->exists();
        if ($livePage) {
            $this->fail('from_path', 'A published page lives at this address, so a redirect would never be used. Unpublish or move the page first.');
        }

        if (! in_array($status, [301, 302], true)) {
            $this->fail('status_code', 'Choose permanent (301) or temporary (302).');
        }

        $isPath = str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\');
        $isHttps = str_starts_with(strtolower($to), 'https://') && filter_var($to, FILTER_VALIDATE_URL) !== false;

        if ($to === '' || strlen($to) > 255 || preg_match('/[\x00-\x20\x7F]/', $to) || (! $isPath && ! $isHttps)) {
            $this->fail('to_path', 'Use a page path such as /about, or a full https:// address.');
        }

        if ($isPath) {
            $target = self::normalisePath((string) strtok($to, '?#'));

            if ($target === $from) {
                $this->fail('to_path', 'An address cannot redirect to itself.');
            }

            $chained = Redirect::query()->where('from_path', $target)
                ->when($ownId !== null, fn ($q) => $q->whereKeyNot($ownId))->first();
            if ($chained !== null) {
                $this->fail('to_path', "That address already redirects to {$chained->to_path}. Point straight there instead.");
            }
        }
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
