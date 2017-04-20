<?php

namespace Printed\Bundle\Queue\Service\QueueMaintenance;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Class FilesystemQueueMaintenanceStrategy
 *
 * Queue maintenance strategy, that uses a lock file on the filesystem to track whether the maintenance
 * mode is on or off.
 *
 * Though simple, this strategy will silently not work in the case, when the maintenance mode is being
 * enabled in a _different_ project directory, than the current workers are running from. This happens
 * when deploying a new version of a project and trying to bring the maintenance mode up from the newly
 * deployed directory instead of the previous one. Since this is such a common case, I personally strongly
 * discourage anyone from using this strategy. But as one wise man once said: "An opinion is like
 * an ass. Everyone has their own."
 */
class FilesystemQueueMaintenanceStrategy implements QueueMaintenanceStrategyInterface
{
    /** @var Filesystem */
    private $fileSystem;

    /** @var string */
    private $lockFileFullPath;

    /**
     * @param string $cacheDirectory
     */
    public function __construct(string $cacheDirectory)
    {
        $this->fileSystem = new Filesystem();
        $this->lockFileFullPath = sprintf('%s/rabbit-queue.lock', rtrim($cacheDirectory));
    }

    /**
     * @inheritdoc
     */
    public function isEnabled(): bool
    {
        return $this->fileSystem->exists($this->lockFileFullPath);
    }

    /**
     * @inheritdoc
     */
    public function enable()
    {
        $this->fileSystem->dumpFile($this->lockFileFullPath, time());
    }

    /**
     * @inheritdoc
     */
    public function disable()
    {
        $this->fileSystem->remove($this->lockFileFullPath);
    }
}