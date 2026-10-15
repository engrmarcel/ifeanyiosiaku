<?php

namespace App\Domain\Settings\Enums;

/**
 * Accent colour choices for the website. Presets rather than free colour input, so every choice
 * keeps text readable (each pair is checked for WCAG AAA contrast in AccentColourTest).
 */
enum AccentColour: string
{
    case Green = 'green';
    case Cyan = 'cyan';
    case Amber = 'amber';
    case Violet = 'violet';
    case Rose = 'rose';

    public function label(): string
    {
        return match ($this) {
            self::Green => 'Code green (signature)',
            self::Cyan => 'Terminal cyan',
            self::Amber => 'Amber',
            self::Violet => 'Soft violet',
            self::Rose => 'Rose',
        };
    }

    /**
     * Design-token values (CSS custom properties) applied on the website.
     *
     * @return array{accent: string, accentStrong: string, accentInk: string}
     */
    public function tokens(): array
    {
        [$accent, $strong, $ink] = match ($this) {
            self::Green => ['#a3f7bf', '#7fe9a3', '#062012'],
            self::Cyan => ['#8be9fa', '#5ddcf2', '#03222a'],
            self::Amber => ['#fcd57a', '#f8c14a', '#2a1c02'],
            self::Violet => ['#c9bcfd', '#ad9bfb', '#1b1036'],
            self::Rose => ['#fdb0b9', '#fb8f9e', '#33070f'],
        };

        return ['accent' => $accent, 'accentStrong' => $strong, 'accentInk' => $ink];
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
