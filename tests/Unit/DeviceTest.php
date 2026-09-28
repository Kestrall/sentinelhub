<?php

namespace App\Tests\Unit;

use App\Entity\Device;
use App\Entity\Site;
use App\Entity\User;
use App\Security\DeviceTokenManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DeviceTest extends TestCase
{
    public function testNeverSeenDeviceIsOffline(): void
    {
        self::assertFalse((new Device(new Site(new User())))->isOnline());
    }

    #[DataProvider('heartbeats')]
    public function testOnlineStatusDependsOnLastHeartbeat(int $secondsAgo, bool $expected): void
    {
        $now = new \DateTimeImmutable('2026-01-01 12:00:00');
        $device = (new Device(new Site(new User())))->markSeen($now->modify("-$secondsAgo seconds"));

        self::assertSame($expected, $device->isOnline($now));
    }

    public static function heartbeats(): iterable
    {
        yield 'à l\'instant' => [5, true];
        yield 'limite' => [Device::OFFLINE_AFTER_SECONDS, true];
        yield 'trop ancien' => [Device::OFFLINE_AFTER_SECONDS + 1, false];
    }

    public function testMacAddressIsNormalized(): void
    {
        $device = (new Device(new Site(new User())))->setMacAddress(' 24-6f-28-aa-bb-cc ');

        self::assertSame('24:6F:28:AA:BB:CC', $device->getMacAddress());
    }

    public function testTokenIsHashedAndNeverStoredInClear(): void
    {
        $device = new Device(new Site(new User()));
        $token = (new DeviceTokenManager())->issue($device);

        self::assertTrue(DeviceTokenManager::looksValid($token));
        self::assertSame(substr($token, 0, 12), $device->getTokenPrefix());

        $stored = (new \ReflectionProperty(Device::class, 'tokenHash'))->getValue($device);
        self::assertSame(hash('sha256', $token), $stored);
        self::assertStringNotContainsString(substr($token, 4), $stored);
    }
}
