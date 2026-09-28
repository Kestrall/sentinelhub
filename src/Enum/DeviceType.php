<?php

namespace App\Enum;

enum DeviceType: string
{
    case Sensor = 'sensor';
    case Camera = 'camera';
    case Gateway = 'gateway';
    case Siren = 'siren';

    public function label(): string
    {
        return match ($this) {
            self::Sensor => 'Capteur',
            self::Camera => 'Caméra',
            self::Gateway => 'Passerelle',
            self::Siren => 'Sirène',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Sensor => 'thermo',
            self::Camera => 'camera',
            self::Gateway => 'router',
            self::Siren => 'siren',
        };
    }
}
