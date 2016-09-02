<?php

namespace Printed\Bundle\Queue\Service;

use Symfony\Component\Filesystem\Filesystem;

class QueueMaintenance
{

    /**
     * @var string
     */
    protected $cache;

    /**
     * @var string
     */
    protected $file;

    /**
     * {@inheritdoc}
     *
     * @param string $cache
     */
    public function __construct(string $cache)
    {
        $this->cache = rtrim($cache);
        $this->file = sprintf('%s/rabbit-queue.lock', $this->cache);
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return file_exists($this->file);
    }

    public function enable()
    {
        $fs = new Filesystem();
        $fs->dumpFile($this->file, time());
    }

    public function disable()
    {
        $fs = new Filesystem();
        $fs->remove($this->file);
    }

}
