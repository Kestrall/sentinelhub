<?php

namespace App\Repository;

use App\Document\TelemetryPoint;
use App\Enum\Metric;
use Doctrine\Bundle\MongoDBBundle\ManagerRegistry;
use Doctrine\Bundle\MongoDBBundle\Repository\ServiceDocumentRepository;

/**
 * @extends ServiceDocumentRepository<TelemetryPoint>
 */
class TelemetryRepository extends ServiceDocumentRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TelemetryPoint::class);
    }

    /**
     * Série temporelle agrégée côté MongoDB : les points sont regroupés par intervalles
     * ($dateTrunc) avec min / moyenne / max, pour ne renvoyer au navigateur
     * qu'une centaine de points quel que soit le volume brut.
     *
     * @return list<array{t: string, avg: float, min: float, max: float}>
     */
    public function series(int $deviceId, Metric $metric, int $hours = 24, int $buckets = 96): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d hours', $hours));
        $binMinutes = max(1, intdiv($hours * 60, $buckets));
        $field = '$metrics.'.$metric->value;

        $rows = $this->createAggregationBuilder()
            ->match()
                ->field('deviceId')->equals($deviceId)
                ->field('recordedAt')->gte($since)
                ->field('metrics.'.$metric->value)->exists(true)
            ->group()
                ->field('id')->expression(['$dateTrunc' => ['date' => '$recordedAt', 'unit' => 'minute', 'binSize' => $binMinutes]])
                ->field('avg')->avg($field)
                ->field('min')->min($field)
                ->field('max')->max($field)
            ->sort('_id', 'asc')
            ->getAggregation()
            ->getIterator();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                't' => $row['_id']->toDateTime()->format(\DATE_ATOM),
                'avg' => round((float) $row['avg'], 2),
                'min' => round((float) $row['min'], 2),
                'max' => round((float) $row['max'], 2),
            ];
        }

        return $out;
    }

    /**
     * Dernières métriques connues de chaque appareil (un $group avec $first après tri).
     *
     * @param list<int> $deviceIds
     *
     * @return array<int, array{metrics: array<string, float|int>, at: \DateTimeImmutable}>
     */
    public function latestByDevice(array $deviceIds): array
    {
        if (!$deviceIds) {
            return [];
        }

        $rows = $this->createAggregationBuilder()
            ->match()
                ->field('deviceId')->in($deviceIds)
                ->field('recordedAt')->gte(new \DateTimeImmutable('-24 hours'))
            ->sort('recordedAt', 'desc')
            ->group()
                ->field('id')->expression('$deviceId')
                ->field('metrics')->first('$metrics')
                ->field('at')->first('$recordedAt')
            ->getAggregation()
            ->getIterator();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['_id']] = [
                'metrics' => (array) $row['metrics'],
                'at' => \DateTimeImmutable::createFromMutable($row['at']->toDateTime()),
            ];
        }

        return $out;
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return $this->createQueryBuilder()
            ->field('recordedAt')->gte($since)
            ->count()
            ->getQuery()
            ->execute();
    }
}
