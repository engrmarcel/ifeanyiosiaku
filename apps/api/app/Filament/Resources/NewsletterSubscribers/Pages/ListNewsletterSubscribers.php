<?php

namespace App\Filament\Resources\NewsletterSubscribers\Pages;

use App\Domain\Audit\AuditLogger;
use App\Domain\Newsletter\Models\NewsletterSubscriber;
use App\Filament\Resources\NewsletterSubscribers\NewsletterSubscriberResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListNewsletterSubscribers extends ListRecords
{
    protected static string $resource = NewsletterSubscriberResource::class;

    public function getSubheading(): ?string
    {
        $count = NewsletterSubscriber::query()->where('status', NewsletterSubscriber::SUBSCRIBED)->count();

        return "{$count} confirmed ".($count === 1 ? 'subscriber' : 'subscribers').'.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export confirmed subscribers (CSV)')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(fn (): StreamedResponse => self::export()),
        ];
    }

    /** CSV of confirmed subscribers with their consent record, for importing into an email tool. */
    public static function export(): StreamedResponse
    {
        $actor = Auth::user();
        app(AuditLogger::class)->record('newsletter.exported', context: [
            'count' => NewsletterSubscriber::query()->where('status', NewsletterSubscriber::SUBSCRIBED)->count(),
        ], actor: $actor instanceof User ? $actor : null);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['email', 'confirmed_at', 'consent_at', 'consent_source', 'consent_text']);

            NewsletterSubscriber::query()->where('status', NewsletterSubscriber::SUBSCRIBED)->orderBy('id')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        // A leading = + - @ would be run as a formula by spreadsheet apps.
                        fputcsv($out, array_map(fn ($v) => preg_match('/^[=+\-@]/', (string) $v) ? "'".$v : (string) $v, [
                            $row->email,
                            $row->confirmed_at?->toIso8601String(),
                            $row->consent_at?->toIso8601String(),
                            $row->consent_source,
                            $row->consent_text,
                        ]));
                    }
                });

            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
