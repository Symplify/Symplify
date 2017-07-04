<?php declare(strict_types=1);

namespace Symplify\Statie\Configuration;

use SplFileInfo;
use Symplify\PackageBuilder\Adapter\Symfony\Parameter\ParameterProvider;
use Symplify\Statie\Configuration\Parser\NeonParser;

final class Configuration
{
    /**
     * @var string
     */
    public const OPTION_POST_ROUTE = 'postRoute';

    /**
     * @var string
     */
    public const OPTION_GITHUB_REPOSITORY_SLUG = 'githubRepositorySlug';

    /**
     * @var string
     */
    public const OPTION_MARKDOWN_HEADLINE_ANCHORS = 'markdownHeadlineAnchors';

    /**
     * @var bool
     */
    private const DEFAULT_MARKDOWN_HEADLINE_ANCHORS = false;

    /**
     * @var string
     */
    private const DEFAULT_POST_ROUTE = 'blog/:year/:month/:day/:title';

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var NeonParser
     */
    private $neonParser;

    /**
     * @var string
     */
    private $sourceDirectory;

    /**
     * @var string
     */
    private $outputDirectory;

    /**
     * @var ParameterProvider
     */
    private $parameterProvider;

    public function __construct(NeonParser $neonParser, ParameterProvider $parameterProvider)
    {
        $this->neonParser = $neonParser;
        $this->parameterProvider = $parameterProvider;
    }

    /**
     * @param mixed|mixed[] $value
     */
    public function addGlobalVarialbe(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    public function setSourceDirectory(string $sourceDirectory): void
    {
        $this->sourceDirectory = $sourceDirectory;
    }

    public function setOutputDirectory(string $outputDirectory): void
    {
        $this->outputDirectory = $outputDirectory;
    }

    public function getOutputDirectory(): string
    {
        return $this->outputDirectory;
    }

    public function getSourceDirectory(): string
    {
        if ($this->sourceDirectory) {
            return $this->sourceDirectory;
        }

        return getcwd() . DIRECTORY_SEPARATOR . 'source';
    }

    public function getPostRoute(): string
    {
        return $this->options['configuration'][self::OPTION_POST_ROUTE]
            ?? self::DEFAULT_POST_ROUTE;
    }

    public function getGithubRepositorySlug(): string
    {
        return $this->options['configuration'][self::OPTION_GITHUB_REPOSITORY_SLUG] ?? '';
    }

    public function isMarkdownHeadlineAnchors(): bool
    {
        return $this->options['configuration'][self::OPTION_MARKDOWN_HEADLINE_ANCHORS]
            ?? self::DEFAULT_MARKDOWN_HEADLINE_ANCHORS;
    }

    public function getOptions(): array
    {
        $this->options += $this->parameterProvider->provide();

        return $this->options;
    }

    public function setPostRoute(string $postRoute): void
    {
        $this->options['configuration'][self::OPTION_POST_ROUTE] = $postRoute;
    }

    public function enableMarkdownHeadlineAnchors(): void
    {
        $this->options['configuration'][self::OPTION_MARKDOWN_HEADLINE_ANCHORS] = true;
    }

    public function disableMarkdownHeadlineAnchors(): void
    {
        $this->options['configuration'][self::OPTION_MARKDOWN_HEADLINE_ANCHORS] = false;
    }
}
