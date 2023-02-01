<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Service\QueueMaintenance\QueueMaintenanceStrategyInterface;

class QueueMaintenance
{
    private QueueMaintenanceStrategyInterface $queueMaintenanceStrategy;

    public function __construct(
        QueueMaintenanceStrategyInterface $queueMaintenanceStrategy
    ) {
        $this->queueMaintenanceStrategy = $queueMaintenanceStrategy;
    }

    public function isEnabled(): bool
    {
        return $this->queueMaintenanceStrategy->isEnabled();
    }

    public function enable(): void
    {
        $this->queueMaintenanceStrategy->enable();
    }

    public function disable(): void
    {
        $this->queueMaintenanceStrategy->disable();
    }
}
