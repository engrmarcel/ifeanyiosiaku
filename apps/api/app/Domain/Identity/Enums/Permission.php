<?php

namespace App\Domain\Identity\Enums;

/**
 * Fine-grained permissions. New modules add their permissions here and to rolePermissions().
 * The PermissionSeeder syncs this list to the database, so it is the single source of truth.
 */
enum Permission: string
{
    case AdminAccess = 'admin.access';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case UsersSuspend = 'users.suspend';
    case RolesAssign = 'roles.assign';

    case SettingsManage = 'settings.manage';
    case IntegrationsManage = 'integrations.manage';
    case AuditLogView = 'audit_log.view';
    case SystemHealthView = 'system_health.view';

    /**
     * Default permission bundles per role. Super Administrator receives every permission.
     *
     * @return array<string, list<self>>
     */
    public static function rolePermissions(): array
    {
        return [
            Role::SuperAdmin->value => self::cases(),
            Role::Admin->value => [
                self::AdminAccess, self::UsersView, self::UsersManage, self::UsersSuspend,
                self::SettingsManage, self::AuditLogView, self::SystemHealthView,
            ],
            Role::Editor->value => [self::AdminAccess],
            Role::Instructor->value => [self::AdminAccess],
            Role::Moderator->value => [self::AdminAccess, self::UsersView],
            Role::Finance->value => [self::AdminAccess, self::UsersView],
            Role::Member->value => [],
        ];
    }
}
