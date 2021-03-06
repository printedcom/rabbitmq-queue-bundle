<?php

namespace Printed\Bundle\Queue\Service\QueueMaintenance;

use Doctrine\Common\Cache\Cache;
use Psr\Log\LoggerInterface;

/**
 * Class CacheQueueMaintenanceStrategy
 *
 * Queue maintenance strategy, that uses a cache key to track whether the maintenance mode is on or off.
 *
 * This strategy doesn't suffer from the issue mentioned in FilesystemQueueMaintenanceStrategy, but
 * requires a cache solution.
 *
 * Caveat 1: if many of your project's environments (e.g. test environments) share one cache solution
 * (i.e. the same cache server), then it's your job to handle cache keys clashes from these environments.
 * I personally recommend using Doctrine's cache drivers, because they offer cache keys' namespacing
 * out-of-the-box. To set a cache namespace, copy&paste&alter the following:
 *
 *   rabbitmq_queue_bundle_cache:
 *     class: Doctrine\Common\Cache\MemcachedCache
 *     calls:
 *       - [ setMemcached, ['@memcached_instance'] ]
 *       - [ setNamespace, ['my_project_%kernel.environment%'] ]
 *
 * When done, provide this service's name via this bundle's configuration. WARNING: if you normally
 * namespace your cache with deployments' build times (or any other value, that uniquely identify
 * your project's deployments), then you must NOT use the same technique in the cache driver for this
 * bundle, because you'd run into the same problem FilesystemQueueMaintenanceStrategy suffers from.
 * E.g. the following namespace is wrong and will silently fail:
 * 'my_project_%kernel.environment%_%deployment.build_timestamp%'
 *
 * It's best to namespace the cache driver with the vhost of the deployment (the same idea as Rabbitmq's
 * vhosts).
 *
 * The only reason why this strategy requires an instance of Doctrine\Common\Cache\Cache and not
 * Psr\Cache\CacheItemPoolInterface is because doctrine's cache doesn't implement the psr interface.
 * This would make my recommended way for solving caveat 1 not work.
 */
class CacheQueueMaintenanceStrategy implements QueueMaintenanceStrategyInterface
{
    const CACHE_KEY = 'printed_rabbitmq_queue_bundle_queue_maintenance_mode_enabled_marker';

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

    /**
     * @inheritdoc
     */
    public function isEnabled(): bool
    {
        return $this->cache->contains(static::CACHE_KEY);
    }

    /**
     * @inheritdoc
     */
    public function enable()
    {
        $result = $this->cache->save(static::CACHE_KEY, time());

        if ($result) {
            return;
        }

        throw new \RuntimeException(join(' ', [
            "Couldn't enable queue maintenance mode, because saving the maintenance marker in",
            'cache server failed for unknown reason. Please check, whether the cache server is running',
            'and whether your cache configuration is correct.',
        ]));
    }

    /**
     * @inheritdoc
     */
    public function disable()
    {
        $result = $this->cache->delete(static::CACHE_KEY);

        if ($result) {
            return;
        }

        /*
         * This method can't throw exceptions, because this would potentially abort a build process
         * after database migrations are already run. Logging an error message is the only safe
         * thing I can do here. At least it allows deployers to see in the build logs, that there
         * was an issue.
         */
        $this->logger->error(join(' ', [
            "Couldn't disable queue maintenance mode, because removing the maintenance marker in",
            'cache server failed for unknown reason. Please check, whether the cache server is running',
            'and whether your cache configuration is correct.',
        ]));
    }
}
