<?php declare(strict_types=1);

namespace Symplify\PackageBuilder\Exception\DependencyInjection;

use Exception;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symplify\EasyCodingStandard\Configuration\Loader\NeonLoader;

final class DefinitionForTypeNotFoundException extends Exception
{
    protected function getContainerLoader(ContainerInterface $container): DelegatingLoader
    {
        /** @var DelegatingLoader $delegationLoader */
        $delegationLoader = parent::getContainerLoader($container);

        /** @var LoaderResolver $resolver */
        $resolver = $delegationLoader->getResolver();
        $resolver->addLoader(new NeonLoader($container));

        return $delegationLoader;
    }
}
