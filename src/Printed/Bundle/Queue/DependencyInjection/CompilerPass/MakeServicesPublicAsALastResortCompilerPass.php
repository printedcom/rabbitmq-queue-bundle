<?php

namespace Printed\Bundle\Queue\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Makes selected private container services public.
 *
 * In version 5.0, this bundle made all its services private. But in case you've tried everything and you concluded
 * that you need to be able to retrieve some of these services directly from the container, then use this compiler pass.
 * Pass the list of service you need to be public and then add the compiler pass in your kernel:
 *
 * <code>
 * final class AppKernel extends Kernel
 * {
 *      protected function build(ContainerBuilder $containerBuilder): void
 *      {
 *          $containerBuilder->addCompilerPass(new MakeServicesPublicAsALastResortCompilerPass(['service_name_I_need_public']));
 *      }
 * }
 * </code>
 *
 * Example: you have to use an old version of a Symfony bundle that doesn't offer any way of retrieving services but
 * through implementing the ContainerAwareInterface.
 */
final class MakeServicesPublicAsALastResortCompilerPass implements CompilerPassInterface
{
    /** @var string[] Service names of services to make public */
    private $serviceNames;

    public function __construct(array $serviceNames)
    {
        $this->serviceNames = $serviceNames;
    }

    public function process(ContainerBuilder $container): void
    {
        foreach ($this->serviceNames as $serviceName) {
            $container->getDefinition($serviceName)->setPublic(true);
        }
    }
}
