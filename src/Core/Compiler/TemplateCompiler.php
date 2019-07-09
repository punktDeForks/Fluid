<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Core\Compiler;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\ParsedTemplateInterface;
use TYPO3Fluid\Fluid\Core\Parser\ParsingState;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\NodeInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\RootNode;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Class TemplateCompiler
 */
class TemplateCompiler
{

    const SHOULD_GENERATE_VIEWHELPER_INVOCATION = '##should_gen_viewhelper##';
    const MODE_NORMAL = 'normal';
    const MODE_WARMUP = 'warmup';

    /**
     * @var array
     */
    protected $syntaxTreeInstanceCache = [];

    /**
     * @var NodeConverter
     */
    protected $nodeConverter;

    /**
     * @var RenderingContextInterface
     */
    protected $renderingContext;

    /**
     * @var string
     */
    protected $mode = self::MODE_NORMAL;

    /**
     * @var ParsedTemplateInterface
     */
    protected $currentlyProcessingState;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->nodeConverter = new NodeConverter($this);
    }

    /**
     * Instruct the TemplateCompiler to enter warmup mode, assigning
     * additional context allowing cache-related implementations to
     * subsequently check the mode.
     *
     * Cannot be reversed once done - should only be used from within
     * FluidCacheWarmerInterface implementations!
     */
    public function enterWarmupMode(): void
    {
        $this->mode = static::MODE_WARMUP;
    }

    /**
     * Returns TRUE only if the TemplateCompiler is in warmup mode.
     */
    public function isWarmupMode(): bool
    {
        return $this->mode === static::MODE_WARMUP;
    }

    /**
     * @return ParsedTemplateInterface|null
     */
    public function getCurrentlyProcessingState(): ?ParsedTemplateInterface
    {
        return $this->currentlyProcessingState;
    }

    /**
     * @param RenderingContextInterface $renderingContext
     * @return void
     */
    public function setRenderingContext(RenderingContextInterface $renderingContext): void
    {
        $this->renderingContext = $renderingContext;
    }

    /**
     * @return RenderingContextInterface
     */
    public function getRenderingContext(): RenderingContextInterface
    {
        return $this->renderingContext;
    }

    /**
     * @param NodeConverter $nodeConverter
     * @return void
     */
    public function setNodeConverter(NodeConverter $nodeConverter): void
    {
        $this->nodeConverter = $nodeConverter;
    }

    /**
     * @return NodeConverter
     */
    public function getNodeConverter(): NodeConverter
    {
        return $this->nodeConverter;
    }

    /**
     * @return void
     */
    public function disable(): void
    {
        throw new StopCompilingException('Compiling stopped');
    }

    /**
     * @return boolean
     */
    public function isDisabled(): bool
    {
        return !$this->renderingContext->isCacheEnabled();
    }

    /**
     * @param string $identifier
     * @return boolean
     */
    public function has(string $identifier): bool
    {
        $identifier = $this->sanitizeIdentifier($identifier);
        if (!$identifier) {
            return false;
        }
        if (isset($this->syntaxTreeInstanceCache[$identifier]) || class_exists($identifier, false)) {
            return true;
        }
        if (!$this->renderingContext->isCacheEnabled()) {
            return false;
        }
        return (boolean) $this->renderingContext->getCache()->get($identifier);
    }

    /**
     * @param string $identifier
     * @return ParsedTemplateInterface
     */
    public function get(string $identifier): ParsedTemplateInterface
    {
        $identifier = $this->sanitizeIdentifier($identifier);

        if (!isset($this->syntaxTreeInstanceCache[$identifier])) {
            if (!class_exists($identifier, false)) {
                $this->renderingContext->getCache()->get($identifier);
            }
            if (!is_a($identifier, UncompilableTemplateInterface::class, true)) {
                $this->syntaxTreeInstanceCache[$identifier] = new $identifier();
            } else {
                return new $identifier();
            }
        }


        return $this->syntaxTreeInstanceCache[$identifier];
    }

    /**
     * Resets the currently processing state
     *
     * @return void
     */
    public function reset(): void
    {
        $this->currentlyProcessingState = null;
    }

    /**
     * @param string $identifier
     * @param ParsingState $parsingState
     * @return string|null
     */
    public function store(string $identifier, ParsingState $parsingState): ?string
    {
        if ($this->isDisabled()) {
            $parsingState->setCompilable(false);
            return null;
        }

        $identifier = $this->sanitizeIdentifier($identifier);
        $cache = $this->renderingContext->getCache();
        if (!$parsingState->isCompilable()) {
            $templateCode = '<?php' . PHP_EOL . 'class ' . $identifier .
                ' extends \TYPO3Fluid\Fluid\Core\Compiler\AbstractCompiledTemplate' . PHP_EOL .
                ' implements \TYPO3Fluid\Fluid\Core\Compiler\UncompilableTemplateInterface' . PHP_EOL .
                '{' . PHP_EOL . '}';
            $cache->set($identifier, $templateCode);
            return $templateCode;
        }

        $this->currentlyProcessingState = $parsingState;
        $this->nodeConverter->setVariableCounter(0);
        $generatedRenderFunctions = $this->generateSectionCodeFromParsingState($parsingState);

        $generatedRenderFunctions .= $this->generateCodeForSection(
            $this->nodeConverter->convertListOfSubNodes($parsingState->getRootNode()),
            'render',
            'Main Render function'
        );

        $classDefinition = 'class ' . $identifier . ' extends \TYPO3Fluid\Fluid\Core\Compiler\AbstractCompiledTemplate';

        $templateCode = <<<EOD
<?php

%s {

public function getLayoutName(\TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface \$renderingContext): string {
\$self = \$this;
%s;
}
public function hasLayout(): bool {
return %s;
}
public function addCompiledNamespaces(\TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface \$renderingContext): void {
\$renderingContext->getViewHelperResolver()->addNamespaces(%s);
}

%s

}
EOD;
        $storedLayoutName = $parsingState->getVariableContainer()->get('layoutName');
        $templateCode = sprintf(
            $templateCode,
            $classDefinition,
            $this->generateCodeForLayoutName($storedLayoutName),
            ($parsingState->hasLayout() ? 'TRUE' : 'FALSE'),
            var_export($this->renderingContext->getViewHelperResolver()->getNamespaces(), true),
            $generatedRenderFunctions
        );
        $this->renderingContext->getCache()->set($identifier, $templateCode);
        return $templateCode;
    }

    /**
     * @param RootNode|string $storedLayoutNameArgument
     * @return string
     */
    protected function generateCodeForLayoutName($storedLayoutNameArgument): string
    {
        if ($storedLayoutNameArgument instanceof NodeInterface) {
            $converted = $this->nodeConverter->convert($storedLayoutNameArgument);
            return $converted['initialization'] . PHP_EOL . 'return ' . $converted['execution'];
        } else {
            return 'return (string) \'' . $storedLayoutNameArgument . '\'';
        }
    }

    /**
     * @param ParsingState $parsingState
     * @return string
     */
    protected function generateSectionCodeFromParsingState(ParsingState $parsingState): string
    {
        $generatedRenderFunctions = '';
        if ($parsingState->getVariableContainer()->exists('1457379500_sections')) {
            $sections = $parsingState->getVariableContainer()->get('1457379500_sections'); // TODO: refactor to $parsedTemplate->getSections()
            foreach ($sections as $sectionName => $sectionRootNode) {
                $generatedRenderFunctions .= $this->generateCodeForSection(
                    $this->nodeConverter->convertListOfSubNodes($sectionRootNode),
                    'section_' . sha1((string)$sectionName),
                    'section ' . $sectionName
                );
            }
        }
        return $generatedRenderFunctions;
    }

    /**
     * Replaces special characters by underscores
     * @see http://www.php.net/manual/en/language.variables.basics.php
     *
     * @param string $identifier
     * @return string the sanitized identifier
     */
    protected function sanitizeIdentifier(string $identifier): ?string
    {
        return preg_replace('([^a-zA-Z0-9_\x7f-\xff])', '_', $identifier);
    }

    /**
     * @param array $converted
     * @param string $expectedFunctionName
     * @param string $comment
     * @return string
     */
    protected function generateCodeForSection(array $converted, string $expectedFunctionName, string $comment): string
    {
        $templateCode = <<<EOD
/**
 * %s
 */
public function %s(\TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface \$renderingContext) {
\$self = \$this;
%s
return %s;
}

EOD;
        return sprintf($templateCode, $comment, $expectedFunctionName, $converted['initialization'], $converted['execution']);
    }

    /**
     * Returns a unique variable name by appending a global index to the given prefix
     *
     * @param string $prefix
     * @return string
     */
    public function variableName(string $prefix): string
    {
        return $this->nodeConverter->variableName($prefix);
    }

    /**
     * @param NodeInterface $node
     * @return string
     */
    public function wrapChildNodesInClosure(NodeInterface $node): string
    {
        $closure = '';
        $closure .= 'function() use ($renderingContext, $self) {' . chr(10);
        $convertedSubNodes = $this->nodeConverter->convertListOfSubNodes($node);
        $closure .= $convertedSubNodes['initialization'];
        $closure .= sprintf('return %s;', $convertedSubNodes['execution']) . chr(10);
        $closure .= '}';
        return $closure;
    }

    /**
     * Wraps one ViewHelper argument evaluation in a closure that can be
     * rendered by passing a rendering context.
     *
     * @param NodeInterface $node
     * @param string $argumentName
     * @return string
     */
    public function wrapViewHelperNodeArgumentEvaluationInClosure(NodeInterface $node, string $argumentName)
    {
        $arguments = $node->getParsedArguments();
        $argument = $arguments[$argumentName] ?? null;

        $closure = 'function() use ($renderingContext, $self) {' . chr(10);
        if (!$argument instanceof NodeInterface) {
            $closure .= 'return ' . var_export($argument, true) . ';';
        } elseif ($argument instanceof NodeInterface) {
            $compiled = $this->nodeConverter->convert($argument);
            $closure .= $compiled['initialization'] . chr(10);
            $closure .= 'return ' . $compiled['execution'] . ';' . chr(10);
        }
        $closure .= '}';
        return $closure;
    }
}
