<?php

namespace Printed\Bundle\Queue\Exception;

/**
 * Thrown, when queue task repository couldn't find all requested tasks in
 * the database.
 */
class CouldNotFindAllRequestedQueueTasksException extends \RuntimeException
{
    /** @var string[] */
    private $missingTaskIds;

    public function __construct(string $message, array $missingTaskIds)
    {
        parent::__construct($message);

        $this->missingTaskIds = $missingTaskIds;
    }

    /**
     * @return string[]
     */
    public function getMissingTaskIds(): array
    {
        return $this->missingTaskIds;
    }
}
