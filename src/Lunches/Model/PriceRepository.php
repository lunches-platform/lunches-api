<?php

namespace Lunches\Model;

use Doctrine\ORM\EntityRepository;

/**
 * PriceRepository
 */
class PriceRepository extends EntityRepository
{
    public function findByDate(\DateTime $date)
    {
        $prices = $this->findBy([
            'date' => $date,
        ]);

        return new Prices($prices);
    }
    public function findByDateRange(DateRange $dateRange)
    {
        $qb = $this->_em->createQueryBuilder();
        $qb->select(['p'])
            ->from('Lunches\Model\Price', 'p')
            ->where('p.date >= :start')
            ->andWhere('p.date <= :end')
            ->setParameters([
                'start' => $dateRange->getStart()->format('Y-m-d'),
                'end' => $dateRange->getEnd()->format('Y-m-d'),
            ]);

        return new Prices($qb->getQuery()->getResult());
    }
}
