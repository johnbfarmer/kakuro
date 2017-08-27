<?php

namespace AppBundle\Repository;

use Doctrine\ORM\EntityRepository;

class GridRepository extends EntityRepository
{
    public function getNextUniqueGridName()
    {
        $sql = '
        SELECT name FROM grids 
        WHERE name LIKE "uq%"
        ORDER BY `id` DESC';

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch();
        $name = empty($result) ? 'uq0' : $result['name'];
        $num = (int)substr($name, 2) + 1;
        return 'uq' . $num;
    }
}
