<?php

namespace App\Domain\Portfolio\Blocks;

use Filament\Forms\Components\TextInput;

/** Shared "eyebrow / heading / intro" fields for data-driven sections. */
trait SectionHeader
{
    /** @return list<TextInput> */
    protected static function headerFields(string $defaultHeading): array
    {
        return [
            TextInput::make('eyebrow')->maxLength(80),
            TextInput::make('heading')->default($defaultHeading)->required()->maxLength(120),
            TextInput::make('intro')->maxLength(300),
        ];
    }

    /** @return array<string, list<string>> */
    protected static function headerRules(): array
    {
        return [
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'heading' => ['required', 'string', 'max:120'],
            'intro' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{eyebrow: string|null, heading: string, intro: string|null}
     */
    protected static function header(array $data): array
    {
        return [
            'eyebrow' => filled($data['eyebrow'] ?? null) ? (string) $data['eyebrow'] : null,
            'heading' => (string) ($data['heading'] ?? ''),
            'intro' => filled($data['intro'] ?? null) ? (string) $data['intro'] : null,
        ];
    }
}
