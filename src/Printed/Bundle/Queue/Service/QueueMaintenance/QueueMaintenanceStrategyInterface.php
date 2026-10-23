<?php

namespace Printed\Bundle\Queue\Service\QueueMaintenance;

/**
 * Interface QueueMaintenanceStrategyInterface
 *
 * Common interface for different ways of enabling and disabling the queue maintenance mode.
 */
interface QueueMaintenanceStrategyInterface
{
    /**
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * @return void
     */
    public function enable(): void;

    /**
     * @return void
     */
    public function disable(): void;
}
