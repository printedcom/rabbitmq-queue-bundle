<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Service\NewDeploymentsDetector;

/**
 * Class NoopNewDeploymentsDetectorStrategy
 *
 * Strategy for opting out of the New Deployments Detection functionality.
 */
class NoopNewDeploymentsDetectorStrategy implements NewDeploymentsDetectorStrategyInterface
{
    public function getCurrentDeploymentStamp(): string
    {
        return 'noop';
    }

    public function setCurrentDeploymentStamp(string $deploymentStamp): void
    {
    }
}
