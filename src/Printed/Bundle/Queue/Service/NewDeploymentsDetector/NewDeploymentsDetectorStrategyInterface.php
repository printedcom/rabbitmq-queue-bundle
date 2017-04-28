<?php

namespace Printed\Bundle\Queue\Service\NewDeploymentsDetector;

/**
 * Interface NewDeploymentsDetectorStrategyInterface
 *
 * Common interface for different ways of finding out, whether a new deployment has occurred
 * since the worker has been started.
 */
interface NewDeploymentsDetectorStrategyInterface
{
    /**
     * Create a string, that identifies current deployment and that can later be compared
     * to see, whether the deployment's changed.
     *
     * Using a timestamp is generally a good idea.
     *
     * @return string
     */
    public function getCurrentDeploymentStamp(): string;

    /**
     * @param string $deploymentStamp
     * @return void
     */
    public function setCurrentDeploymentStamp(string $deploymentStamp);
}
