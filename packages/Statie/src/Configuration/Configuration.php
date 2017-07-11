<?php declare(strict_types=1);

namespace Symplify\Statie\Configuration;

use Symplify\PackageBuilder\Adapter\Symfony\Parameter\ParameterProvider;
use Symplify\Statie\Renderable\File\PostFile;

final class Configuration
{
    /**
     * @var string
     */
    public const OPTION_POST_ROUTE = 'post_route';

    /**
     * @var string
     */
    public const OPTION_GITHUB_REPOSITORY_SLUG = 'github_repository_slug';

    /**
     * @var string
     */
    public const OPTION_MARKDOWN_HEADLINE_ANCHORS = 'markdown_headline_anchors';

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
     * @var string
     */
    private $sourceDirectory;

    /**
     * @var string
     */
    private $outputDirectory;

    public function __construct(ParameterProvider $parameterProvider)
    {
        $this->options += $parameterProvider->provide();
    }

    /**
     * @param PostFile[] $posts
     */
    public function addPosts(array $posts): void
    {
        $this->options['posts'] = $posts;
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
        return $this->options[self::OPTION_POST_ROUTE]
            ?? self::DEFAULT_POST_ROUTE;
    }

    public function getGithubRepositorySlug(): string
    {
        return $this->options[self::OPTION_GITHUB_REPOSITORY_SLUG] ?? '';
    }

    public function isMarkdownHeadlineAnchors(): bool
    {
        return $this->options[self::OPTION_MARKDOWN_HEADLINE_ANCHORS]
            ?? self::DEFAULT_MARKDOWN_HEADLINE_ANCHORS;
    }

    /**
     * @return mixed[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function setPostRoute(string $postRoute): void
    {
        $this->options[self::OPTION_POST_ROUTE] = $postRoute;
    }

    public function enableMarkdownHeadlineAnchors(): void
    {
        $this->options[self::OPTION_MARKDOWN_HEADLINE_ANCHORS] = true;
    }

    public function disableMarkdownHeadlineAnchors(): void
    {
        $this->options[self::OPTION_MARKDOWN_HEADLINE_ANCHORS] = false;
    }
}
