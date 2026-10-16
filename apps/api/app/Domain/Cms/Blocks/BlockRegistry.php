<?php

namespace App\Domain\Cms\Blocks;

use App\Domain\Cms\Blocks\Types\AccordionBlock;
use App\Domain\Cms\Blocks\Types\CalloutBlock;
use App\Domain\Cms\Blocks\Types\CallToActionBlock;
use App\Domain\Cms\Blocks\Types\CardsBlock;
use App\Domain\Cms\Blocks\Types\CodeBlock;
use App\Domain\Cms\Blocks\Types\DiagramBlock;
use App\Domain\Cms\Blocks\Types\DividerBlock;
use App\Domain\Cms\Blocks\Types\DownloadsBlock;
use App\Domain\Cms\Blocks\Types\GalleryBlock;
use App\Domain\Cms\Blocks\Types\HeadingBlock;
use App\Domain\Cms\Blocks\Types\HeroBlock;
use App\Domain\Cms\Blocks\Types\ImageBlock;
use App\Domain\Cms\Blocks\Types\QuoteBlock;
use App\Domain\Cms\Blocks\Types\RawHtmlBlock;
use App\Domain\Cms\Blocks\Types\RichTextBlock;
use App\Domain\Cms\Blocks\Types\StatsBlock;
use App\Domain\Cms\Blocks\Types\TableBlock;
use App\Domain\Cms\Blocks\Types\TabsBlock;
use App\Domain\Cms\Blocks\Types\TerminalBlock;
use App\Domain\Cms\Blocks\Types\TimelineBlock;
use App\Domain\Cms\Blocks\Types\TwoColumnBlock;
use App\Domain\Cms\Blocks\Types\VideoBlock;
use App\Domain\Cms\Support\RichTextSanitizer;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The set of block types pages can use (ADR-007). Later modules register their own blocks
 * (e.g. featured courses) with register().
 */
class BlockRegistry
{
    /** @var array<string, class-string<Block>> */
    private array $blocks = [];

    public function __construct(private readonly RichTextSanitizer $sanitizer)
    {
        foreach ([
            HeroBlock::class, HeadingBlock::class, RichTextBlock::class, ImageBlock::class, GalleryBlock::class,
            CardsBlock::class, TwoColumnBlock::class, StatsBlock::class, TimelineBlock::class, AccordionBlock::class,
            TabsBlock::class, CodeBlock::class, TerminalBlock::class, CalloutBlock::class, QuoteBlock::class,
            VideoBlock::class, TableBlock::class, CallToActionBlock::class, DividerBlock::class,
            DownloadsBlock::class, DiagramBlock::class, RawHtmlBlock::class,
        ] as $block) {
            $this->register($block);
        }
    }

    /** @param class-string<Block> $block */
    public function register(string $block): void
    {
        $this->blocks[$block::type()] = $block;
    }

    /** @return array<string, class-string<Block>> */
    public function all(): array
    {
        return $this->blocks;
    }

    /** @return class-string<Block>|null */
    public function get(string $type): ?string
    {
        return $this->blocks[$type] ?? null;
    }

    /** The page builder field for the admin panel. */
    public function builder(string $name = 'blocks'): Builder
    {
        return Builder::make($name)
            ->label('Page content')
            ->blocks(array_values(array_map(fn (string $block) => Builder\Block::make($block::type())
                ->label($block::label())
                ->icon($block::icon())
                ->schema([
                    ...$block::fields(),
                    Section::make('Display options')
                        ->collapsed()
                        ->compact()
                        ->schema([
                            Toggle::make('visible')->label('Show this block on the website')->default(true),
                            TextInput::make('anchor')
                                ->label('Anchor (for links like /page#contact)')
                                ->maxLength(60)
                                ->regex('/^[a-z0-9][a-z0-9-]*$/'),
                        ])
                        ->columns(2),
                ]), $this->blocks)))
            ->blockPickerColumns(3)
            ->blockNumbers()
            ->cloneable()
            ->collapsible()
            ->reorderableWithButtons()
            ->addActionLabel('Add block');
    }

    /**
     * Validates and sanitises blocks before they are stored. Throws with field-level messages.
     *
     * @param  array<int|string, mixed>|null  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function prepare(?array $blocks, string $attribute = 'blocks'): array
    {
        $clean = [];
        $errors = [];

        foreach (array_values($blocks ?? []) as $index => $block) {
            $position = $index + 1;
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            $class = is_string($type) ? $this->get($type) : null;

            if ($class === null) {
                $errors["{$attribute}.{$index}"] = ["Block {$position}: unknown block type."];

                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            $validator = Validator::make($data, [
                ...$class::rules(),
                'visible' => ['nullable', 'boolean'],
                'anchor' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/'],
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors["{$attribute}.{$index}.data.{$field}"] = array_map(
                        fn (string $m) => "Block {$position} ({$class::label()}): {$m}",
                        $messages,
                    );
                }

                continue;
            }

            foreach ($class::htmlFields() as $path) {
                $data = $this->sanitizePath($data, explode('.', $path));
            }

            $clean[] = ['type' => $type, 'data' => $data];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * Public representation for the website: hidden and unknown blocks are dropped.
     *
     * @param  array<int|string, mixed>|null  $blocks
     * @param  array{page: string, draft: bool}|null  $embedScope  the page these blocks belong to (enables sandboxed embeds)
     * @return list<array{id: string, type: string, version: int, anchor: string|null, props: array<string, mixed>}>
     */
    public function toPublic(?array $blocks, ?array $embedScope = null): array
    {
        $blocks = array_values($blocks ?? []);
        $context = new BlockContext($this->sanitizer, $this->mediaIds($blocks), $embedScope);
        $public = [];

        foreach ($blocks as $index => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;
            $class = is_string($type) ? $this->get($type) : null;
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            if ($class === null) {
                Log::warning('Skipped unknown CMS block type.', ['type' => $type]);

                continue;
            }

            if (($data['visible'] ?? true) === false) {
                continue;
            }

            $context->index = $index;
            $public[] = [
                'id' => "block-{$index}",
                'type' => $type,
                'version' => $class::VERSION,
                'anchor' => filled($data['anchor'] ?? null) ? (string) $data['anchor'] : null,
                'props' => $class::toPublic($data, $context),
            ];
        }

        return $public;
    }

    /**
     * Media library IDs referenced anywhere in the blocks.
     *
     * @param  array<int|string, mixed>|null  $blocks
     * @return list<string>
     */
    public function mediaIds(?array $blocks): array
    {
        $ids = [];

        foreach (array_values($blocks ?? []) as $block) {
            $class = is_array($block) && is_string($block['type'] ?? null) ? $this->get($block['type']) : null;

            if ($class !== null && is_array($block['data'] ?? null)) {
                array_push($ids, ...$class::mediaIds($block['data']));
            }
        }

        return array_values(array_unique(array_filter($ids, 'is_string')));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $segments
     * @return array<string, mixed>
     */
    private function sanitizePath(array $data, array $segments): array
    {
        $key = array_shift($segments);

        if ($key === null) {
            return $data;
        }

        if ($key === '*') {
            foreach ($data as $i => $item) {
                if (is_array($item)) {
                    $data[$i] = $this->sanitizePath($item, $segments);
                }
            }

            return $data;
        }

        if (! array_key_exists($key, $data)) {
            return $data;
        }

        if ($segments === []) {
            $data[$key] = is_string($data[$key]) ? $this->sanitizer->clean($data[$key]) : $data[$key];

            return $data;
        }

        if (is_array($data[$key])) {
            $data[$key] = $this->sanitizePath($data[$key], $segments);
        }

        return $data;
    }
}
