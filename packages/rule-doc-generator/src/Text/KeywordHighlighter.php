<?php

declare(strict_types=1);

namespace Symplify\RuleDocGenerator\Text;

use Nette\Utils\Strings;
use Symplify\PackageBuilder\Reflection\ClassLikeExistenceChecker;
use Throwable;

/**
 * @see \Symplify\RuleDocGenerator\Tests\Text\KeywordHighlighterTest
 */
final class KeywordHighlighter
{
    /**
     * @var string[]
     */
    private const TEXT_WORDS = [
        'Rename',
        'EventDispatcher',
        'current',
        'defined',
        'rename',
        'next',
        'file',
        'constant',
    ];

    /**
     * @var string
     * @see https://regex101.com/r/uxtJDA/3
     */
    private const VARIABLE_CALL_OR_VARIABLE_REGEX = '#^\$([A-Za-z\-\>]+)[^\]](\(\))?#';

    /**
     * @var string
     * @see https://regex101.com/r/uxtJDA/1
     */
    private const STATIC_CALL_REGEX = '#([A-Za-z::\-\>]+)(\(\))$#';

    /**
     * @var string
     * @see https://regex101.com/r/9vnLcf/1
     */
    private const ANNOTATION_REGEX = '#(\@\w+)$#';

    /**
     * @var string
     * @see https://regex101.com/r/bwUIKb/1
     */
    private const METHOD_NAME_REGEX = '#\w+\(\)#';

    /**
     * @var string
     * @see https://regex101.com/r/18wjck/2
     */
    private const COMMA_SPLIT_REGEX = '#(?<call>\w+\(.*\))(\s{0,})(?<comma>,)(?<quote>\`)#';

    public function __construct(
        private ClassLikeExistenceChecker $classLikeExistenceChecker
    ) {
    }

    public function highlight(string $content): string
    {
        $words = Strings::split($content, '# #');
        foreach ($words as $key => $word) {
            if (! $this->isKeywordToHighlight($word)) {
                continue;
            }

            $words[$key] = Strings::replace(
                '`' . $word . '`',
                self::COMMA_SPLIT_REGEX,
                fn (array $match): string => $match['call'] . $match['quote'] . $match['comma']
            );
        }

        return implode(' ', $words);
    }

    private function isKeywordToHighlight(string $word): bool
    {
        if (Strings::match($word, self::ANNOTATION_REGEX)) {
            return true;
        }

        // already in code quotes
        if (\str_starts_with($word, '`')) {
            return false;
        }

        if (\str_ends_with($word, '`')) {
            return false;
        }

        // part of normal text
        if (in_array($word, self::TEXT_WORDS, true)) {
            return false;
        }

        if ($this->isFunctionOrClass($word)) {
            return true;
        }

        if ($word === 'composer.json') {
            return true;
        }

        if ((bool) Strings::match($word, self::VARIABLE_CALL_OR_VARIABLE_REGEX)) {
            return true;
        }

        return (bool) Strings::match($word, self::STATIC_CALL_REGEX);
    }

    private function isFunctionOrClass(string $word): bool
    {
        if (Strings::match($word, self::METHOD_NAME_REGEX)) {
            return true;
        }

        if ($this->classLikeExistenceChecker->doesClassLikeExist($word)) {
            // not a class
            if (! \str_contains($word, '\\')) {
                return in_array($word, [Throwable::class, 'Exception'], true);
            }

            return true;
        }

        return false;
    }
}
