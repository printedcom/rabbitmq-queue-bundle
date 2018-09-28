<?php

namespace Printed\Bundle\Queue\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * {@inheritdoc}
 */
class QueueExtension extends Extension
{

    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(sprintf('%s/../Resources/config', __DIR__)));
        $loader->load('services.yml');
        $loader->load('services/commands.yml');
        $loader->load('services/helpers.yml');
        $loader->load('services/listeners.yml');
        $loader->load('services/repositories.yml');

    }

}
