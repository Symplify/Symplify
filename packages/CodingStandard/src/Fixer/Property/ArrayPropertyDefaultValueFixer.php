<?php declare(strict_types=1);

namespace Symplify\CodingStandard\Fixer\Property;

use Nette\Utils\Strings;
use PhpCsFixer\AbstractFixer;
use PhpCsFixer\DocBlock\DocBlock;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class ArrayPropertyDefaultValueFixer extends AbstractFixer
{
    public function isCandidate(Tokens $tokens): bool
    {
        // analyze only properties with comments
        return $tokens->isAllTokenKindsFound([T_DOC_COMMENT, T_VARIABLE]);
    }

    protected function applyFix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach ($tokens as $index => $token) {
            if (! $token->isGivenKind(T_DOC_COMMENT)) {
                continue;
            }

            if (! $this->isArrayPropertyDocComment($token)) {
                continue;
            }

            $semicolonSignOrArrayOpenerPosition = $tokens->getNextMeaningfulToken($index + 4);
            $semicolonSignOrArrayOpenerToken = $tokens[$semicolonSignOrArrayOpenerPosition];
            if ($semicolonSignOrArrayOpenerToken->equals('[')) {
                // token after property equal is start of an array
                continue;
            }

            // token after property is ; - its end => so no definition
            $this->addDefaultValueForArrayProperty($tokens, $semicolonSignOrArrayOpenerPosition);
        }
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Array property should have default value, to prevent undefined array issues.',
            []
        );
    }

    private function isArrayPropertyDocComment(Token $token): bool
    {
        $docBlock = new DocBlock($token->getContent());

        if (count($docBlock->getLines()) === 1) {
            return false;
        }

        if (! $docBlock->getAnnotationsOfType('var')) {
            return false;
        }

        $varAnnotation = $docBlock->getAnnotationsOfType('var')[0];
        if (! Strings::contains($varAnnotation->getTypes()[0], '[]')) {
            return false;
        }

        return true;
    }

    private function addDefaultValueForArrayProperty(Tokens $tokens,int $semicolonPosition): void
    {
        $tokens->insertAt($semicolonPosition, new Token(']'));
        $tokens->insertAt($semicolonPosition, new Token('['));
        $tokens->insertAt($semicolonPosition, new Token(' '));
        $tokens->insertAt($semicolonPosition, new Token('='));
        $tokens->insertAt($semicolonPosition, new Token(' '));
    }
}

