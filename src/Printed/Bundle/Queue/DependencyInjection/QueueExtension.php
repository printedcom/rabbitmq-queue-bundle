<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * {@inheritdoc}
 */
class QueueExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration($this->getAlias());
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(sprintf('%s/../Resources/config', __DIR__)));
        $loader->load('services.yml');

        $this->defineQueueBundleOptions($config, $container);

        $this->configureQueueServicesWithDynamicDependencies($config, $container);
    }

    public function getAlias(): string
    {
        return 'printedcom_rabbitmq_queue_bundle';
    }

    private function defineQueueBundleOptions(array $bundleConfig, ContainerBuilder $container): void
    {
        $queueBundleOptionsDefinition = $container->getDefinition('printed.bundle.queue.service.queue_bundle_options');
        $queueBundleOptionsDefinition->setArgument(0, $bundleConfig['options']);
    }

    private function configureQueueServicesWithDynamicDependencies(array $bundleConfig, ContainerBuilder $container): void
    {
        /*
         * QueueMaintenance.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.queue_maintenance');
        $serviceDefinition->setArgument(0, new Reference($bundleConfig['options']['queue_maintenance_strategy__service_name']));

        /*
         * Case when QueueMaintenance uses the cache strategy: require that the cache service name was provided.
         */
        if (
            'printed.bundle.queue.service.queue_maintenance.cache_queue_maintenance_strategy' === $bundleConfig['options']['queue_maintenance_strategy__service_name']
            && !isset($bundleConfig['options']['cache_queue_maintenance_strategy__cache_service_name'])
        ) {
            throw new \InvalidArgumentException('The "cache_queue_maintenance_strategy" requires the "cache_queue_maintenance_strategy__cache_service_name" option to be provided.');
        }

        /*
         * CacheQueueMaintenanceStrategy.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.queue_maintenance.cache_queue_maintenance_strategy');
        $serviceDefinition->setArgument(0, new Reference($bundleConfig['options']['cache_queue_maintenance_strategy__cache_service_name']));

        /*
         * NewDeploymentsDetector.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.new_deployments_detector');
        $serviceDefinition->setArgument(0, new Reference($bundleConfig['options']['new_deployments_detector_strategy__service_name']));

        /*
         * Case when NewDeploymentsDetector uses the cache strategy: require that the cache service name was provided.
         */
        if (
            'printed.bundle.queue.service.new_deployments_detector.cache_strategy' === $bundleConfig['options']['new_deployments_detector_strategy__service_name']
            && !isset($bundleConfig['options']['new_deployments_detector_strategy__cache_service_name'])
        ) {
            throw new \InvalidArgumentException('The "new_deployments_detector.cache_strategy" requires the "new_deployments_detector_strategy__cache_service_name" option to be provided.');
        }

        /*
         * CacheNewDeploymentsDetectorStrategy.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.new_deployments_detector.cache_strategy');
        $serviceDefinition->setArgument(0, new Reference($bundleConfig['options']['new_deployments_detector_strategy__cache_service_name']));

        /*
         * QueueTaskDispatcher.php
         */
        $serviceDefinition = $container->getDefinition('printed.bundle.queue.service.queue_task_dispatcher');
        $serviceDefinition->setArgument(3, new Reference($bundleConfig['options']['default_rabbitmq_producer_name']));
    }
}
