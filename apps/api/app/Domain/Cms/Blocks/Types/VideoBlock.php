<?php

namespace App\Domain\Cms\Blocks\Types;

use App\Domain\Cms\Blocks\Block;
use App\Domain\Cms\Blocks\BlockContext;
use Closure;
use Filament\Forms\Components\TextInput;

/**
 * Embedded video from approved providers only (YouTube, Vimeo). The website loads a privacy-enhanced
 * player only after the visitor clicks play, so no third-party tracking happens on page load.
 */
class VideoBlock extends Block
{
    public static function type(): string
    {
        return 'video';
    }

    public static function label(): string
    {
        return 'Video (YouTube / Vimeo)';
    }

    public static function icon(): string
    {
        return 'heroicon-o-play-circle';
    }

    public static function fields(): array
    {
        return [
            TextInput::make('url')
                ->label('Video address')
                ->required()
                ->url()
                ->maxLength(300)
                ->placeholder('https://www.youtube.com/watch?v=…')
                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                    if (self::parse(is_string($value) ? $value : null) === null) {
                        $fail('Only YouTube and Vimeo addresses are supported.');
                    }
                }),
            TextInput::make('title')->label('Video title (for screen readers)')->required()->maxLength(160),
            TextInput::make('caption')->maxLength(300),
        ];
    }

    public static function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:300', function (string $attribute, mixed $value, Closure $fail) {
                if (self::parse(is_string($value) ? $value : null) === null) {
                    $fail('Only YouTube and Vimeo addresses are supported.');
                }
            }],
            'title' => ['required', 'string', 'max:160'],
            'caption' => ['nullable', 'string', 'max:300'],
        ];
    }

    public static function toPublic(array $data, BlockContext $context): array
    {
        $video = self::parse($data['url'] ?? null);

        return [
            'provider' => $video['provider'] ?? null,
            'videoId' => $video['id'] ?? null,
            'title' => $data['title'] ?? 'Video',
            'caption' => $data['caption'] ?? null,
        ];
    }

    /** @return array{provider: string, id: string}|null */
    public static function parse(?string $url): ?array
    {
        if ($url === null) {
            return null;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^(www\.|m\.)/', '', $host);
        $path = (string) parse_url($url, PHP_URL_PATH);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            $id = is_string($query['v'] ?? null) ? $query['v'] : (preg_match('#^/(?:embed|shorts|live)/([^/?]+)#', $path, $m) ? $m[1] : null);
        } elseif ($host === 'youtu.be') {
            $id = trim($path, '/');
        } elseif ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
            return preg_match('#/(?:video/)?(\d{6,12})#', $path, $m) ? ['provider' => 'vimeo', 'id' => $m[1]] : null;
        } else {
            return null;
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? ['provider' => 'youtube', 'id' => $id] : null;
    }
}
