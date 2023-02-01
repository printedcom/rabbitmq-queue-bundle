<?php

namespace Printed\Bundle\Queue;

use Printed\Bundle\Queue\DependencyInjection\QueueExtension;

use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * {@inheritdoc}
 */
class QueueBundle extends Bundle
{

    /**
     * {@inheritdoc}
     */
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new QueueExtension;
    }

}
