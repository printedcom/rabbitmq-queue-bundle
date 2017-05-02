<?php

namespace Printed\Bundle\Queue\Service\NewDeploymentsDetector;

use Doctrine\Common\Cache\Cache;

/**
 * Class CacheNewDeploymentsDetectorStrategy
 *
 * New deployments detection that stores and reads the latest deployment stamp from a cache.
 *
 * Cache requirements are the same as outlined in CacheQueueMaintenanceStrategy
 */
class CacheNewDeploymentsDetectorStrategy implements NewDeploymentsDetectorStrategyInterface
{
    const CACHE_KEY = 'printed_rabbitmq_queue_bundle_deployment_stamp';

    /** @var Cache */
    private $cache;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    public function getCurrentDeploymentStamp(): string
    {
        return $this->cache->fetch(static::CACHE_KEY) ?: 'unset';
    }

    public function setCurrentDeploymentStamp(string $deploymentStamp)
    {
        $this->cache->save(static::CACHE_KEY, $deploymentStamp);
    }
}
