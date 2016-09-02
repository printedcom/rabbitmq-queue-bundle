<?php

namespace Printed\Bundle\Queue\Repository;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;

use Doctrine\ORM\EntityRepository;

/**
 * @method QueueTaskInterface find($id, $lockMode = null, $lockVersion = null)
 * @method QueueTaskInterface findOneBy(array $criteria, array $orderBy = null)
 *
 * @method QueueTaskInterface[] findAll()
 */
class QueueTaskRepository extends EntityRepository
{

}
