<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Exception;

/**
 * Thrown, when queue task repository couldn't find all requested tasks in
 * the database.
 */
class CouldNotFindAllRequestedQueueTasksException extends \RuntimeException
{
    /**
     * @param string[] $missingTaskIds
     */
    public function __construct(string $message, private readonly array $missingTaskIds)
    {
        parent::__construct($message);
    }

    /**
     * @return string[]
     */
    public function getMissingTaskIds(): array
    {
        return $this->missingTaskIds;
    }
}
