<?php

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Service\NewDeploymentsDetector\NewDeploymentsDetectorStrategyInterface;

class NewDeploymentsDetector
{
    private NewDeploymentsDetectorStrategyInterface $newDeploymentsDetectorStrategy;

    public function __construct(
        NewDeploymentsDetectorStrategyInterface $newDeploymentsDetectorStrategy
    ) {
        $this->newDeploymentsDetectorStrategy = $newDeploymentsDetectorStrategy;
    }

    public function getCurrentDeploymentStamp(): string
    {
        return $this->newDeploymentsDetectorStrategy->getCurrentDeploymentStamp();
    }

    public function setCurrentDeploymentStamp(string $deploymentStamp): void
    {
        $this->newDeploymentsDetectorStrategy->setCurrentDeploymentStamp($deploymentStamp);
    }

    public function isDeploymentStampTheCurrentOne(string $otherDeploymentStamp): bool
    {
        return $this->getCurrentDeploymentStamp() === $otherDeploymentStamp;
    }
}
