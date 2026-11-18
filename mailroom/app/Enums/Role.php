<?php

declare(strict_types=1);

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Author = 'author';
    case Viewer = 'viewer';

    public function level(): int
    {
        return match ($this) {
            self::Owner => 5,
            self::Admin => 4,
            self::Manager => 3,
            self::Author => 2,
            self::Viewer => 1,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->level() >= $role->level();
    }

    public function description(): string
    {
        return match ($this) {
            self::Owner => 'Full access, including billing-level settings and other admins.',
            self::Admin => 'Everything, including users and sending settings.',
            self::Manager => 'Audiences, campaigns, automations and sending; no users or settings.',
            self::Author => 'Create and edit campaigns and templates; cannot send or touch contacts.',
            self::Viewer => 'Read-only access to campaigns, reports and audiences.',
        };
    }
}
