<?php

namespace App\Enum;

enum Operator: string
{
    case GreaterThan = 'gt';
    case LessThan = 'lt';

    public function symbol(): string
    {
        return match ($this) {
            self::GreaterThan => '>',
            self::LessThan => '<',
        };
    }

    public function matches(float $value, float $threshold): bool
    {
        return match ($this) {
            self::GreaterThan => $value > $threshold,
            self::LessThan => $value < $threshold,
        };
    }
}
