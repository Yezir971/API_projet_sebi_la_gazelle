<?php

namespace App\Repository;

use App\Entity\Scores;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Scores>
 */
class ScoresRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scores::class);
    }

    //    /**
    //     * @return Scores[] Returns an array of Scores ordoned by desc whith the name game
    //     */
       public function findAllScoreByDesc(string $nameGame): array
       {
            return $this->createQueryBuilder('s')
                ->where('s.name_game = :nameGame')
                ->setParameter('nameGame', $nameGame)
                ->orderBy('s.score', 'DESC')
                ->getQuery()
                ->getResult();
       }
    //    /**
    //     * @return Scores[] Returns an array of Scores ordoned by desc
    //     */
    //    public function findAllScoreByDesc(): array
    //    {
    //         return $this->createQueryBuilder('s')
    //             ->orderBy('s.score', 'DESC')
    //             ->getQuery()
    //             ->getResult();
    //    }

    //    public function findOneBySomeField($value): ?Scores
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
