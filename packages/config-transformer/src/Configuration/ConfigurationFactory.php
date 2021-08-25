<?php

declare(strict_types=1);

namespace Symplify\ConfigTransformer\Configuration;

use Symfony\Component\Console\Input\InputInterface;
use Symplify\ConfigTransformer\ValueObject\Configuration;
use Symplify\ConfigTransformer\ValueObject\Option;

final class ConfigurationFactory
{
    public function createFromInput(InputInterface $input): Configuration
    {
        $source = (array) $input->getArgument(Option::SOURCES);
        $targetSymfonyVersion = floatval($input->getOption(Option::TARGET_SYMFONY_VERSION));
        $isDryRun = boolval($input->getOption(Option::DRY_RUN));

        return new Configuration($source, $targetSymfonyVersion, $isDryRun);
    }
}
