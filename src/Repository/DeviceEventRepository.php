<?php

namespace App\Repository;

use App\Document\DeviceEvent;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepository;

/**
 * @extends ServiceDocumentRepository<DeviceEvent>
 */
class DeviceEventRepository extends ServiceDocumentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeviceEvent::class);
    }

    /**
     * @param list<int> $deviceIds
     *
     * @return list<DeviceEvent>
     */
    public function findRecent(array $deviceIds, int $limit = 20, ?string $type = null): array
    {
        if (!$deviceIds) {
            return [];
        }

        $qb = $this->createQueryBuilder()
            ->field('deviceId')->in($deviceIds)
            ->sort('occurredAt', 'desc')
            ->limit($limit);

        if ($type) {
            $qb->field('type')->equals($type);
        }

        return array_values($qb->getQuery()->execute()->toArray());
    }

    /**
     * Histogramme des événements par heure sur les dernières 24 h, ventilé par sévérité.
     *
     * @param list<int> $deviceIds
     *
     * @return list<array{hour: string, info: int, warning: int, critical: int}>
     */
    public function hourlyHistogram(array $deviceIds, int $hours = 24): array
    {
        $start = (new \DateTimeImmutable())->setTime((int) date('H'), 0)->modify(sprintf('-%d hours', $hours - 1));

        $buckets = [];
        for ($i = 0; $i < $hours; ++$i) {
            $key = $start->modify("+$i hours")->format('Y-m-d\TH');
            $buckets[$key] = ['hour' => $key, 'info' => 0, 'warning' => 0, 'critical' => 0];
        }

        if (!$deviceIds) {
            return array_values($buckets);
        }

        $rows = $this->createAggregationBuilder()
            ->match()
                ->field('deviceId')->in($deviceIds)
                ->field('occurredAt')->gte($start)
            ->group()
                ->field('id')->expression([
                    'hour' => ['$dateToString' => ['format' => '%Y-%m-%dT%H', 'date' => '$occurredAt', 'timezone' => date_default_timezone_get()]],
                    'severity' => '$severity',
                ])
                ->field('count')->sum(1)
            ->getAggregation()
            ->getIterator();

        foreach ($rows as $row) {
            $hour = $row['_id']['hour'];
            $severity = $row['_id']['severity'];
            if (isset($buckets[$hour][$severity])) {
                $buckets[$hour][$severity] = (int) $row['count'];
            }
        }

        return array_values($buckets);
    }

    /**
     * @param list<int> $deviceIds
     *
     * @return array<string, int> type => nombre, trié par fréquence
     */
    public function countByType(array $deviceIds, \DateTimeImmutable $since): array
    {
        if (!$deviceIds) {
            return [];
        }

        $rows = $this->createAggregationBuilder()
            ->match()
                ->field('deviceId')->in($deviceIds)
                ->field('occurredAt')->gte($since)
            ->group()
                ->field('id')->expression('$type')
                ->field('count')->sum(1)
            ->sort('count', 'desc')
            ->getAggregation()
            ->getIterator();

        $out = [];
        foreach ($rows as $row) {
            $out[$row['_id']] = (int) $row['count'];
        }

        return $out;
    }
}
