<?php

namespace Printed\Bundle\Queue\Service\NewDeploymentsDetector;

use Doctrine\Common\Cache\Cache;
use Psr\Log\LoggerInterface;

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

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Cache $cache,
        LoggerInterface $logger
    ) {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    public function getCurrentDeploymentStamp(): string
    {
        return $this->cache->fetch(static::CACHE_KEY) ?: 'unset';
    }

    public function setCurrentDeploymentStamp(string $deploymentStamp)
    {
        $result = $this->cache->save(static::CACHE_KEY, $deploymentStamp);

        if ($result) {
            return;
        }

        /*
         * This method can't throw exceptions, because this would potentially abort a build process
         * after database migrations are already run. For the same reason CacheQueueMaintenanceStrategy
         * doesn't throw in the ::disable() method.
         */
        $this->logger->error(join(' ', [
            "Couldn't set new deployment stamp, because setting it in",
            'cache server failed for unknown reason. Please check, whether the cache server is running',
            'and whether your cache configuration is correct.',
        ]));
    }
}
