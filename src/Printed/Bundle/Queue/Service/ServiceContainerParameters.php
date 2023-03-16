<?php

declare(strict_types=1);

namespace Printed\Bundle\Queue\Service;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;

/**
 * A rough backport of https://symfony.com/blog/new-in-symfony-4-1-getting-container-parameters-as-a-service
 */
class ServiceContainerParameters extends FrozenParameterBag
{
    public function __construct(Container $container)
    {
        parent::__construct($container->getParameterBag()->all());
    }
}
