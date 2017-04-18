<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Service\QueueMaintenance\QueueMaintenanceStrategyInterface;

class QueueMaintenance
{
    /** @var QueueMaintenanceStrategyInterface */
    private $queueMaintenanceStrategy;

    public function __construct(
        QueueMaintenanceStrategyInterface $queueMaintenanceStrategy
    ) {
        $this->queueMaintenanceStrategy = $queueMaintenanceStrategy;
    }

    public function isEnabled(): bool
    {
        return $this->queueMaintenanceStrategy->isEnabled();
    }

    public function enable()
    {
        $this->queueMaintenanceStrategy->enable();
    }

    public function disable()
    {
        $this->queueMaintenanceStrategy->disable();
    }
}
