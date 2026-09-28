<?php

namespace App\DataFixtures;

use App\Entity\Alert;
use App\Entity\AlertRule;
use App\Entity\Device;
use App\Entity\Site;
use App\Entity\User;
use App\Enum\DeviceType;
use App\Enum\Metric;
use App\Enum\Operator;
use App\Enum\Severity;
use App\Security\DeviceTokenManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    /** Token connu, documenté dans le README pour tester l'API avec curl. */
    public const DEMO_TOKEN = 'shd_00000000000000000000000000000000000000000000000000000000c0ffee42';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly DeviceTokenManager $tokens,
        private readonly DocumentManager $dm,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Les fixtures ORM purgent MySQL ; on repart aussi d'un MongoDB propre.
        $this->dm->getSchemaManager()->dropCollections();
        $this->dm->getSchemaManager()->ensureIndexes();

        $user = (new User())->setEmail('demo@sentinelhub.dev')->setFullName('Thomas Martin');
        $user->setPassword($this->hasher->hashPassword($user, 'sentinel2026'));
        $manager->persist($user);

        $home = (new Site($user))->setName('Maison')->setLocation('Lyon (69)');
        $lab = (new Site($user))->setName('Atelier / Homelab')->setLocation('Villeurbanne (69)');
        $manager->persist($home);
        $manager->persist($lab);

        $fleet = [
            // [site, nom, type, matériel, firmware, armé]
            [$home, 'Salon · climat', DeviceType::Sensor, 'ESP32-S3', '1.4.2', false],
            [$home, 'Entrée · caméra', DeviceType::Camera, 'ESP32-CAM', '1.4.2', true],
            [$home, 'Porte du garage', DeviceType::Sensor, 'ESP32-C3', '1.3.9', true],
            [$home, 'Sirène RDC', DeviceType::Siren, 'ESP32-WROOM-32', '1.4.2', true],
            [$home, 'Écran de contrôle', DeviceType::Gateway, 'ESP32-2432S028R', '1.4.2', false],
            [$lab, 'Rack serveur · temp', DeviceType::Sensor, 'ESP32-S3', '1.4.1', false],
            [$lab, 'Établi · PIR', DeviceType::Sensor, 'ESP32-C3', '1.4.2', false],
            [$lab, 'Caméra atelier', DeviceType::Camera, 'ESP32-CAM', '1.4.0', false],
        ];

        $devices = [];
        foreach ($fleet as $i => [$site, $name, $type, $hw, $fw, $armed]) {
            $device = (new Device($site))
                ->setName($name)
                ->setType($type)
                ->setHardware($hw)
                ->setFirmwareVersion($fw)
                ->setArmed($armed)
                ->setMacAddress(sprintf('24:6F:28:%02X:%02X:%02X', 0xA0 + $i, 0x10 * $i, 0x42 + $i));
            $this->tokens->issue($device);
            $manager->persist($device);
            $devices[$name] = $device;
        }

        $demo = $devices['Salon · climat'];
        $demo->setTokenHash(DeviceTokenManager::hash(self::DEMO_TOKEN), substr(self::DEMO_TOKEN, 0, 12));

        $rules = [
            ['Salon · climat', Metric::Temperature, Operator::GreaterThan, 28, Severity::Warning],
            ['Salon · climat', Metric::Humidity, Operator::GreaterThan, 70, Severity::Info],
            ['Rack serveur · temp', Metric::Temperature, Operator::GreaterThan, 38, Severity::Critical],
            ['Porte du garage', Metric::Battery, Operator::LessThan, 20, Severity::Warning],
            ['Caméra atelier', Metric::Rssi, Operator::LessThan, -85, Severity::Warning],
        ];
        foreach ($rules as [$deviceName, $metric, $op, $threshold, $severity]) {
            $devices[$deviceName]->addRule(new AlertRule($devices[$deviceName], $metric, $op, $threshold, $severity));
        }

        $manager->persist(new Alert($devices['Rack serveur · temp'], Severity::Critical, 'Rack serveur · temp : Température = 39.2 °C (seuil > 38)', value: 39.2, createdAt: new \DateTimeImmutable('-3 hours')));
        $manager->persist(new Alert($devices['Entrée · caméra'], Severity::Critical, 'Intrusion possible : Mouvement détecté (Entrée · caméra)', createdAt: new \DateTimeImmutable('-47 minutes')));
        $manager->persist(new Alert($devices['Porte du garage'], Severity::Warning, 'Porte du garage : Batterie = 18 % (seuil < 20)', value: 18, createdAt: new \DateTimeImmutable('-1 day')));

        $manager->flush();
    }
}
