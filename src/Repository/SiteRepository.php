<?php

namespace App\Repository;

use App\Entity\Site;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Site>
 */
class SiteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Site::class);
    }

    /**
     * Sites de l'utilisateur avec leurs appareils, chargés en une seule requête.
     *
     * @return list<Site>
     */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('d')
            ->leftJoin('s.devices', 'd')
            ->where('s.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
