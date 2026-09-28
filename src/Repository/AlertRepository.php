<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alert>
 */
class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    /**
     * @return list<Alert>
     */
    public function findForOwner(User $owner, bool $openOnly = false, int $limit = 50): array
    {
        $qb = $this->ownedBy($owner)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($openOnly) {
            $qb->andWhere('a.acknowledgedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Nombre d'alertes non acquittées, ventilé par sévérité.
     *
     * @return array<string, int> ['critical' => 2, 'warning' => 5, ...]
     */
    public function countOpenBySeverity(User $owner): array
    {
        $rows = $this->ownedBy($owner)
            ->select('a.severity AS severity', 'COUNT(a.id) AS total')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->groupBy('a.severity')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $out[$row['severity']->value] = (int) $row['total'];
        }

        return $out;
    }

    public function acknowledgeAllForOwner(User $owner): int
    {
        $ids = $this->ownedBy($owner)
            ->select('a.id')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->getQuery()
            ->getSingleColumnResult();

        if (!$ids) {
            return 0;
        }

        return $this->createQueryBuilder('a')
            ->update()
            ->set('a.acknowledgedAt', ':now')
            ->set('a.acknowledgedBy', ':user')
            ->where('a.id IN (:ids)')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $owner)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    private function ownedBy(User $owner): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->addSelect('d', 's')
            ->join('a.device', 'd')
            ->join('d.site', 's')
            ->where('s.owner = :owner')
            ->setParameter('owner', $owner);
    }
}
