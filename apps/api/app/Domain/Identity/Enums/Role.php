<?php

namespace App\Domain\Identity\Enums;

/**
 * Role names. Roles are bundles of permissions; code must check permissions, never role names
 * (the only exception is the Super Administrator safeguards in Identity actions).
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Editor = 'editor';
    case Instructor = 'instructor';
    case Moderator = 'moderator';
    case Finance = 'finance';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::Admin => 'Administrator',
            self::Editor => 'Editor',
            self::Instructor => 'Instructor',
            self::Moderator => 'Moderator',
            self::Finance => 'Finance',
            self::Member => 'Member',
        };
    }

    /** Roles that grant access to the admin panel and therefore require two-factor authentication. */
    public function isStaff(): bool
    {
        return $this !== self::Member;
    }

    /** @return list<string> */
    public static function staffValues(): array
    {
        return array_values(array_map(
            fn (self $role) => $role->value,
            array_filter(self::cases(), fn (self $role) => $role->isStaff()),
        ));
    }
}
