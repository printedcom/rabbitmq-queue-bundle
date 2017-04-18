<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Service\QueueMaintenance\QueueMaintenanceStrategyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QueueMaintenance
{
    /** @var QueueMaintenanceStrategyInterface */
    private $queueMaintenanceStrategy;

    public function __construct(
        ContainerInterface $container,
        string $queueMaintenanceStrategyServiceName
    ) {
        $this->queueMaintenanceStrategy = $container->get($queueMaintenanceStrategyServiceName);
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
