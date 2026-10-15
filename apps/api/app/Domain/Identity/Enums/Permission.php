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

    case CmsManage = 'cms.manage';
    case CmsPublish = 'cms.publish';
    /** Trusted HTML/CSS/JS blocks (rendered in a sandboxed frame). Super Administrator only by default. */
    case CmsRawHtml = 'cms.raw_html';
    case MediaManage = 'media.manage';

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
                self::CmsManage, self::CmsPublish, self::MediaManage,
            ],
            Role::Editor->value => [self::AdminAccess, self::CmsManage, self::CmsPublish, self::MediaManage],
            Role::Instructor->value => [self::AdminAccess, self::MediaManage],
            Role::Moderator->value => [self::AdminAccess, self::UsersView],
            Role::Finance->value => [self::AdminAccess, self::UsersView],
            Role::Member->value => [],
        ];
    }
}
