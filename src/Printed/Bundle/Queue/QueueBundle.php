<?php

namespace Printed\Bundle\Queue;

use Printed\Bundle\Queue\DependencyInjection\QueueExtension;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * {@inheritdoc}
 */
class QueueBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): QueueExtension
    {
        return new QueueExtension;
    }
}
