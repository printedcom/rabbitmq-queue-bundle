<?php

namespace Printed\Bundle\Queue\Event;

use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Printed\Bundle\Queue\Service\QueueTaskDispatcher;

/**
 * Dispatch all delayed queue tasks from the queue task dispatcher, when Doctrine's postFlush
 * happens.
 */
class DispatchDelayedQueueTasksEventListener
{
    /** @var QueueTaskDispatcher */
    private $queueTaskDispatcher;

    public function __construct(QueueTaskDispatcher $queueTaskDispatcher)
    {
        $this->queueTaskDispatcher = $queueTaskDispatcher;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postFlush,
        ];
    }

    public function postFlush(PostFlushEventArgs $event)
    {
        /*
         * Make sure the flush doesn't happen in a db transaction, because this wouldn't actually
         * make the queue tasks available in the database at this point.
         */
        if ($event->getEntityManager()->getConnection()->isTransactionActive()) {
            return;
        }

        $this->queueTaskDispatcher->dispatchOnDoctrineFlushPayloads();
    }
}
