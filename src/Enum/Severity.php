<?php

namespace App\Enum;

enum Severity: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Info',
            self::Warning => 'Avertissement',
            self::Critical => 'Critique',
        };
    }
}
