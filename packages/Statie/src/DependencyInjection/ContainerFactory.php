<?php declare(strict_types=1);

namespace Symplify\Statie\DependencyInjection;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Container;

final class ContainerFactory
{
    /**
     * @return ContainerInterface|Container
     */
    public function create(): ContainerInterface
    {
        $appKernel = new AppKernel;
        $appKernel->boot();

        return $appKernel->getContainer();
    }

    /**
     * @return ContainerInterface|Container
     */
    public function createWithConfig(string $config): ContainerInterface
    {
        $appKernel = new AppKernel($config);
        $appKernel->boot();

        return $appKernel->getContainer();
    }
}
