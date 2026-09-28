<?php

namespace App\Enum;

/**
 * Métriques remontées par le firmware SentinelOS.
 */
enum Metric: string
{
    case Temperature = 'temperature';
    case Humidity = 'humidity';
    case Rssi = 'rssi';
    case FreeHeap = 'heap';
    case Battery = 'battery';

    public function label(): string
    {
        return match ($this) {
            self::Temperature => 'Température',
            self::Humidity => 'Humidité',
            self::Rssi => 'Signal Wi-Fi',
            self::FreeHeap => 'Heap libre',
            self::Battery => 'Batterie',
        };
    }

    public function unit(): string
    {
        return match ($this) {
            self::Temperature => '°C',
            self::Humidity, self::Battery => '%',
            self::Rssi => 'dBm',
            self::FreeHeap => 'Ko',
        };
    }
}
