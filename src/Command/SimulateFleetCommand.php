<?php

namespace App\Command;

use App\Document\DeviceEvent;
use App\Document\TelemetryPoint;
use App\Dto\EventInput;
use App\Dto\TelemetryInput;
use App\Entity\Device;
use App\Enum\Severity;
use App\Repository\DeviceRepository;
use App\Service\FleetSimulator;
use App\Service\IngestionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:simulate',
    description: 'Simule une flotte d\'ESP32 : historique (--backfill) et/ou flux en direct (--live)',
)]
final class SimulateFleetCommand
{
    public function __construct(
        private readonly DeviceRepository $devices,
        private readonly IngestionService $ingestion,
        private readonly FleetSimulator $simulator,
        private readonly EntityManagerInterface $em,
        private readonly DocumentManager $dm,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Option('Génère N heures d\'historique (1 point toutes les 5 min)')] int $backfill = 0,
        #[Option('Envoie des mesures en continu, comme de vrais appareils')] bool $live = false,
        #[Option('Intervalle entre deux envois en mode live (secondes)')] int $interval = 5,
    ): int {
        if (!$this->devices->count()) {
            $io->error('Aucun appareil en base. Lance d\'abord : bin/console doctrine:fixtures:load');

            return 1;
        }

        if ($backfill > 0) {
            $this->backfill($io, $backfill);
        }

        if ($live) {
            $this->live($io, max(1, $interval));
        }

        if (!$backfill && !$live) {
            $io->note('Rien à faire : utilise --backfill=48 et/ou --live');
        }

        return 0;
    }

    /**
     * Écriture en masse directement dans MongoDB (sans passer par le moteur d'alertes).
     */
    private function backfill(SymfonyStyle $io, int $hours): void
    {
        $fleet = $this->devices->findAll();
        $steps = $hours * 12;
        $now = new \DateTimeImmutable();
        $io->section(sprintf('Backfill de %d h pour %d appareils', $hours, count($fleet)));
        $io->progressStart($steps);

        for ($i = $steps; $i > 0; --$i) {
            $at = $now->modify(sprintf('-%d minutes', $i * 5));
            foreach ($fleet as $device) {
                $this->dm->persist(new TelemetryPoint($device->getId(), $device->getSite()->getId(), $this->simulator->metricsFor($device, $at), $at));

                if ($event = $this->simulator->maybeEvent($device, 0.02)) {
                    $this->dm->persist(new DeviceEvent(
                        $device->getId(), $device->getSite()->getId(), $device->getName(),
                        $event['type'], $event['severity'], $event['message'], $event['payload'], $at,
                    ));
                }
            }

            if (0 === $i % 50) {
                $this->dm->flush();
                $this->dm->clear();
            }
            $io->progressAdvance();
        }

        $this->dm->flush();
        $this->dm->clear();

        foreach ($fleet as $device) {
            $device->markSeen($now->modify('-1 minute'));
        }
        $this->em->flush();

        $io->progressFinish();
        $io->success(sprintf('%d mesures générées.', $steps * count($fleet)));
    }

    private function live(SymfonyStyle $io, int $interval): void
    {
        $io->section(sprintf('Mode live : envoi toutes les %d s (Ctrl+C pour arrêter)', $interval));

        while (true) {
            /** @var list<Device> $fleet */
            $fleet = $this->devices->findAll();
            $now = new \DateTimeImmutable();
            $alerts = 0;

            foreach ($fleet as $device) {
                $metrics = $this->simulator->metricsFor($device, $now);
                // pic de chaleur occasionnel pour déclencher les règles d'alerte
                if (0 === mt_rand(0, 40) && isset($metrics['temperature'])) {
                    $metrics['temperature'] += 12;
                }
                $alerts += $this->ingestion->ingestTelemetry($device, new TelemetryInput($metrics, firmware: $device->getFirmwareVersion()))['alerts'];

                if ($event = $this->simulator->maybeEvent($device, 0.015)) {
                    $this->ingestion->ingestEvent($device, new EventInput(
                        $event['type'], Severity::from($event['severity']), $event['message'], $event['payload'],
                    ));
                    $io->writeln(sprintf('  <comment>⚡ %s</comment> — %s', $device->getName(), $event['message']));
                }
            }

            $io->writeln(sprintf('[%s] %d appareils → OK%s', $now->format('H:i:s'), count($fleet), $alerts ? sprintf(' <error> %d alerte(s) </error>', $alerts) : ''));

            // évite que l'identity map grossisse indéfiniment
            $this->em->clear();
            $this->dm->clear();
            sleep($interval);
        }
    }
}
