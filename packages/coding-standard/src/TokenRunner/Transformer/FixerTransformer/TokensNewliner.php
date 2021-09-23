<?php

declare(strict_types=1);

namespace Symplify\CodingStandard\TokenRunner\Transformer\FixerTransformer;

use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use PhpCsFixer\WhitespacesFixerConfig;
use Symplify\CodingStandard\TokenRunner\Analyzer\FixerAnalyzer\TokenSkipper;
use Symplify\CodingStandard\TokenRunner\Exception\TokenNotFoundException;
use Symplify\CodingStandard\TokenRunner\ValueObject\BlockInfo;
use Symplify\CodingStandard\TokenRunner\ValueObject\LineKind;
use Symplify\CodingStandard\TokenRunner\Whitespace\IndentResolver;

final class TokensNewliner
{
    public function __construct(
        private LineLengthCloserTransformer $lineLengthCloserTransformer,
        private TokenSkipper $tokenSkipper,
        private LineLengthOpenerTransformer $lineLengthOpenerTransformer,
        private WhitespacesFixerConfig $whitespacesFixerConfig,
        private IndentResolver $indentResolver
    ) {
    }

    /**
     * @param Tokens<Token> $tokens
     */
    public function breakItems(BlockInfo $blockInfo, Tokens $tokens, int $kind = LineKind::CALLS): void
    {
        // from bottom top, to prevent skipping ids
        //  e.g when token is added in the middle, the end index does now point to earlier element!
        $currentNewlineIndentWhitespace = $this->indentResolver->resolveCurrentNewlineIndentWhitespace(
            $tokens,
            $blockInfo->getStart()
        );

        $newlineIndentWhitespace = $this->indentResolver->resolveNewlineIndentWhitespace(
            $tokens,
            $blockInfo->getStart()
        );

        // 1. break before arguments closing
        $this->lineLengthCloserTransformer->insertNewlineBeforeClosingIfNeeded(
            $tokens,
            $blockInfo,
            $kind,
            $currentNewlineIndentWhitespace,
            $this->indentResolver->resolveClosingBracketNewlineWhitespace($tokens, $blockInfo->getStart()),
        );

        // again, from the bottom to the top
        for ($i = $blockInfo->getEnd() - 1; $i > $blockInfo->getStart(); --$i) {
            /** @var Token $currentToken */
            $currentToken = $tokens[$i];

            $i = $this->tokenSkipper->skipBlocksReversed($tokens, $i);

            // 2. new line after each comma ",", instead of just space
            if ($currentToken->getContent() === ',') {
                if ($this->isLastItem($tokens, $i)) {
                    continue;
                }

                if ($this->isFollowedByComment($tokens, $i)) {
                    continue;
                }

                $tokens->ensureWhitespaceAtIndex($i + 1, 0, $newlineIndentWhitespace);
            }
        }

        // 3. break after arguments opening
        $this->lineLengthOpenerTransformer->insertNewlineAfterOpeningIfNeeded(
            $tokens,
            $blockInfo->getStart(),
            $newlineIndentWhitespace
        );
    }

    /**
     * Has already newline? usually the last line => skip to prevent double spacing
     *
     * @param Tokens<Token> $tokens
     */
    private function isLastItem(Tokens $tokens, int $position): bool
    {
        $nextPosition = $position + 1;
        if (! isset($tokens[$nextPosition])) {
            throw new TokenNotFoundException($nextPosition);
        }

        $tokenContent = $tokens[$nextPosition]->getContent();
        return \str_contains($tokenContent, $this->whitespacesFixerConfig->getLineEnding());
    }

    /**
     * @param Tokens<Token> $tokens
     */
    private function isFollowedByComment(Tokens $tokens, int $i): bool
    {
        $nextToken = $tokens[$i + 1];
        $nextNextToken = $tokens[$i + 2];

        if ($nextNextToken->isComment()) {
            return true;
        }

        // if next token is just space, turn it to newline
        if (! $nextToken->isWhitespace(' ')) {
            return false;
        }

        return $nextNextToken->isComment();
    }
}
