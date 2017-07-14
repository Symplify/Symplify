<?php declare(strict_types=1);

namespace Symplify\CodingStandard\Fixer\Php;

use Nette\Utils\Strings;
use PhpCsFixer\Fixer\DefinedFixerInterface;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\CT;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

final class ClassStringToClassConstantFixer implements DefinedFixerInterface
{
    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            '"SomeClass::class" references should be used over string.',
            [
                new CodeSample(
'<?php      

$className = "DateTime";  
                '),
                new CodeSample(
'<?php      

$interfaceName = "DateTimeInterface";  
                '),
                new CodeSample(
'<?php      

$interfaceName = "Nette\Utils\DateTime";  
                '),
            ]
        );
    }

    public function isCandidate(Tokens $tokens): bool
    {
        return $tokens->isTokenKindFound(T_CONSTANT_ENCAPSED_STRING);
    }

    public function fix(SplFileInfo $file, Tokens $tokens): void
    {
        foreach (array_reverse($tokens->toArray(), true) as $index => $token) {
            /** @var Token $token */
            if (! $token->isGivenKind(T_CONSTANT_ENCAPSED_STRING)) {
                continue;
            }

            $potentialClassOrInterfaceName = trim($token->getContent(), "'");
            if (class_exists($potentialClassOrInterfaceName) || interface_exists($potentialClassOrInterfaceName)) {
                $token->clear(); // overrideAt() fails on "Illegal offset type"

                $classOrInterfaceTokens = $this->convertClassOrInterfaceNameToTokens($potentialClassOrInterfaceName);
                $tokens->insertAt($index, array_merge($classOrInterfaceTokens, [
                    new Token([T_DOUBLE_COLON, '::']),
                    new Token([CT::T_CLASS_CONSTANT, 'class']),
                ]));
            }
        }
    }

    public function isRisky(): bool
    {
        return false;
    }

    public function getName(): string
    {
        return self::class;
    }

    public function getPriority(): int
    {
        // @todo combine with namespace import fixer/sniff
        return 0;
    }

    public function supports(SplFileInfo $file): bool
    {
        return true;
    }

    /**
     * @return Token[]
     */
    private function convertClassOrInterfaceNameToTokens(string $potentialClassOrInterfaceName): array
    {
        $tokens = [];
        $nameParts = explode('\\', $potentialClassOrInterfaceName);

        foreach ($nameParts as $namePart) {
            $tokens[] = new Token([T_NS_SEPARATOR, '\\']);
            $tokens[] = new Token([T_STRING, $namePart]);
        }

        return $tokens;
    }
}
