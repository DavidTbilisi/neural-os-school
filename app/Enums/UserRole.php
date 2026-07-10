<?php

namespace App\Enums;

enum UserRole: string
{
    case Learner = 'learner';
    case Editor = 'editor';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Learner => 'Learner',
            self::Editor => 'Editor',
            self::Admin => 'Admin',
        };
    }

    /** Roles allowed into the Filament admin panel. */
    public function isStaff(): bool
    {
        return $this === self::Admin || $this === self::Editor;
    }

    public function color(): string
    {
        return match ($this) {
            self::Admin => 'danger',
            self::Editor => 'warning',
            self::Learner => 'gray',
        };
    }

    /** @return array<string,string> value => label, for Filament selects */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
