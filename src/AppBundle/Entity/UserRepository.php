<?php

namespace AppBundle\Entity;

use AppBundle\Exception\RuntimeException;
use Doctrine\ORM\EntityRepository;

/**
 * UserRepository
 */
class UserRepository extends EntityRepository
{
    /**
     * @param string $fullname
     * @return User
     */
    public function findByUsername($fullname)
    {
        $fullname = (string) $fullname;

        return $this->findOneBy([
            'fullname' => $fullname,
        ]);
    }

    public function getByUsername($fullname)
    {
        $user = $this->findByUsername($fullname);
        if (!$user instanceof User) {
            throw RuntimeException::notFound('User');
        }
        return $user;
    }

    public function findByClientId($clientId)
    {
        return $this->findOneBy([
            'clientId' => $clientId,
        ]);
    }

    public function getByClientId($clientId)
    {
        $user = $this->findOneBy([
            'clientId' => $clientId,
        ]);
        if (!$user instanceof User) {
            throw RuntimeException::notFound('User');
        }
        return $user;
    }

    public function findByLikePattern($like)
    {
        $dql = 'SELECT u FROM AppBundle\Entity\User u WHERE u.fullname LIKE :like';

        return $this->_em->createQuery($dql)->setParameter('like', '%'.$like.'%')->getResult();
    }
    public function generateClientId()
    {
        $dql = 'SELECT MAX(u.clientId) FROM AppBundle\Entity\User u';
        $number = $this->_em->createQuery($dql)->getSingleScalarResult();

        return !$number ? 1000 : ++$number;
    }
}
