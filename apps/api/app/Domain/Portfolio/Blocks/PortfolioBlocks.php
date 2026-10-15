<?php

namespace App\Domain\Portfolio\Blocks;

use App\Domain\Cms\Blocks\BlockRegistry;

/** Registers the portfolio's data-driven blocks with the page builder. */
class PortfolioBlocks
{
    public static function register(BlockRegistry $registry): void
    {
        foreach ([
            ProfileIntroBlock::class,
            ExpertiseBlock::class,
            ProjectsBlock::class,
            ServicesBlock::class,
            ExperienceBlock::class,
            CertificationsBlock::class,
            TestimonialsBlock::class,
        ] as $block) {
            $registry->register($block);
        }
    }
}
