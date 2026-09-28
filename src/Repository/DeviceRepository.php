<?php

namespace App\Repository;

use App\Entity\Device;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Device>
 */
class DeviceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Device::class);
    }

    public function findOneByTokenHash(string $hash): ?Device
    {
        return $this->createQueryBuilder('d')
            ->addSelect('s', 'r')
            ->join('d.site', 's')
            ->leftJoin('d.rules', 'r')
            ->where('d.tokenHash = :hash')
            ->setParameter('hash', $hash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Device>
     */
    public function findForOwner(User $owner, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->addSelect('s')
            ->join('d.site', 's')
            ->where('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('s.name', 'ASC')
            ->addOrderBy('d.name', 'ASC');

        if ($search) {
            $qb->andWhere('d.name LIKE :q OR d.macAddress LIKE :q OR d.hardware LIKE :q')
                ->setParameter('q', '%'.addcslashes($search, '%_').'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return list<int>
     */
    public function findIdsForOwner(User $owner): array
    {
        return array_map('intval', $this->createQueryBuilder('d')
            ->select('d.id')
            ->join('d.site', 's')
            ->where('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->getQuery()
            ->getSingleColumnResult());
    }
}
