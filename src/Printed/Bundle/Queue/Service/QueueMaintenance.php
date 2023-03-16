<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Service\QueueMaintenance\QueueMaintenanceStrategyInterface;

class QueueMaintenance
{
    public function __construct(
        private readonly QueueMaintenanceStrategyInterface $queueMaintenanceStrategy
    ) {
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
