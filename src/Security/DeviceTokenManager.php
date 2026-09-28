<?php

namespace App\Security;

use App\Entity\Device;

/**
 * Génère et vérifie les tokens d'API des appareils.
 *
 * Seul le SHA-256 est stocké en base : une fuite de la table "device"
 * ne permet pas d'usurper un capteur. Un hash rapide suffit ici car le token
 * contient 256 bits d'entropie (pas de risque de brute force, contrairement à un mot de passe).
 */
final class DeviceTokenManager
{
    public const PREFIX = 'shd_';

    /**
     * Attribue un nouveau token à l'appareil et le renvoie en clair (affiché une seule fois).
     */
    public function issue(Device $device): string
    {
        $token = self::PREFIX.bin2hex(random_bytes(32));
        $device->setTokenHash(self::hash($token), substr($token, 0, 12));

        return $token;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function looksValid(string $token): bool
    {
        return 1 === preg_match('/^'.self::PREFIX.'[0-9a-f]{64}$/', $token);
    }
}
