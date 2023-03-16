<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Service;

use Printed\Bundle\Queue\Service\NewDeploymentsDetector\NewDeploymentsDetectorStrategyInterface;

class NewDeploymentsDetector
{
    public function __construct(
        private readonly NewDeploymentsDetectorStrategyInterface $newDeploymentsDetectorStrategy
    ) {
    }

    public function getCurrentDeploymentStamp(): string
    {
        return $this->newDeploymentsDetectorStrategy->getCurrentDeploymentStamp();
    }

    public function setCurrentDeploymentStamp(string $deploymentStamp):  void
    {
        $this->newDeploymentsDetectorStrategy->setCurrentDeploymentStamp($deploymentStamp);
    }

    public function isDeploymentStampTheCurrentOne(string $otherDeploymentStamp): bool
    {
        return $this->getCurrentDeploymentStamp() === $otherDeploymentStamp;
    }
}
