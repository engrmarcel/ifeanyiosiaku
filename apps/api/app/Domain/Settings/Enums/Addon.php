<?php

namespace App\Domain\Settings\Enums;

/**
 * Optional modules that administrators can switch on or off (ADR-025). The core platform
 * (portfolio, CMS, accounts, events, bookings, administration) is always available.
 */
enum Addon: string
{
    case Commerce = 'commerce';
    case Learning = 'learning';
    case Community = 'community';
    case Rewards = 'rewards';

    public function settingKey(): string
    {
        return "addons.{$this->value}.enabled";
    }

    public function label(): string
    {
        return match ($this) {
            self::Commerce => 'Digital store & payments',
            self::Learning => 'Courses & learning',
            self::Community => 'Community',
            self::Rewards => 'Rewards, achievements & leaderboard',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Commerce => 'Product catalogue, cart, checkout, orders and secure downloads. Paid courses, tickets and bookings also need this.',
            self::Learning => 'Course catalogue, lessons, progress tracking, quizzes and certificates.',
            self::Community => 'Member discussions, questions and answers, comments and moderation.',
            self::Rewards => 'Osiaku Coins, badges and the public top-20 leaderboard. Spending coins at checkout also needs the store.',
        };
    }
}
