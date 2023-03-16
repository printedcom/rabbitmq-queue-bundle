<?php

namespace Printed\Bundle\Queue\Helper;

use Printed\Bundle\Queue\EntityInterface\QueueTaskInterface;
use Printed\Bundle\Queue\Queue\AbstractQueuePayload;
use Printed\Bundle\Queue\Repository\QueueTaskRepository;

class QueueTaskHelper
{
    public function __construct(private readonly QueueTaskRepository $queueTaskRepository)
    {
    }

    /**
     * @param QueueTaskInterface $task
     * @return AbstractQueuePayload
     */
    public function getPayload(QueueTaskInterface $task): AbstractQueuePayload
    {
        $class = $task->getPayloadClass();

        return new $class($task->getPayload());
    }

    /**
     * Request tasks cancellation by their public ids and optional search criteria.
     *
     * Learn about $queueTaskPayloadCriteria in QueueTaskRepository.
     *
     * @param string[] $taskPublicIds
     * @param string|null $queueName
     * @param array $queueTaskPayloadCriteria
     */
    public function requestTasksCancellationOrThrow(
        array $taskPublicIds,
        string $queueName = null,
        array $queueTaskPayloadCriteria = []
    ): void {
        $tasks = $this->queueTaskRepository->findByPublicIdsAndQueueNameOrThrow(
            $taskPublicIds,
            $queueName,
            $queueTaskPayloadCriteria
        );

        foreach ($tasks as $task) {
            $task->setCancellationRequested(true);
        }
    }
}
