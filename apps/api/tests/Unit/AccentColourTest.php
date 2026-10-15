<?php

use App\Domain\Settings\Enums\AccentColour;

function relativeLuminance(string $hex): float
{
    $channels = array_map(fn (string $c) => hexdec($c) / 255, str_split(ltrim($hex, '#'), 2));
    $linear = array_map(fn (float $c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4, $channels);

    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
}

function contrastRatio(string $a, string $b): float
{
    $light = max(relativeLuminance($a), relativeLuminance($b));
    $dark = min(relativeLuminance($a), relativeLuminance($b));

    return ($light + 0.05) / ($dark + 0.05);
}

it('keeps every accent colour readable (WCAG AAA) on the site background and under button text', function (AccentColour $colour) {
    $tokens = $colour->tokens();
    $background = '#080a0d';
    $raisedSurface = '#161c25';

    expect(contrastRatio($tokens['accent'], $background))->toBeGreaterThanOrEqual(7.0)
        ->and(contrastRatio($tokens['accent'], $raisedSurface))->toBeGreaterThanOrEqual(7.0)
        ->and(contrastRatio($tokens['accentStrong'], $background))->toBeGreaterThanOrEqual(7.0)
        ->and(contrastRatio($tokens['accentInk'], $tokens['accent']))->toBeGreaterThanOrEqual(7.0)
        ->and(contrastRatio($tokens['accentInk'], $tokens['accentStrong']))->toBeGreaterThanOrEqual(7.0);
})->with(AccentColour::cases());
