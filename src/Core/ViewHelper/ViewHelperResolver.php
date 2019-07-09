<?php
declare(strict_types=1);
namespace TYPO3Fluid\Fluid\Core\ViewHelper;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Parser\Exception;
use TYPO3Fluid\Fluid\Core\Parser\Patterns;

/**
 * Class ViewHelperResolver
 *
 * Responsible for resolving instances of ViewHelpers and for
 * interacting with ViewHelpers; to translate ViewHelper names
 * into actual class names and resolve their ArgumentDefinitions.
 *
 * Replacing this class in for example a framework allows that
 * framework to be responsible for creating ViewHelper instances
 * and detecting possible arguments.
 */
class ViewHelperResolver
{

    /**
     * @var array
     */
    protected $resolvedViewHelperClassNames = [];

    /**
     * Namespaces requested by the template being rendered,
     * in [shortname => phpnamespace] format.
     *
     * @var array
     */
    protected $namespaces = [
        'f' => ['TYPO3Fluid\\Fluid\\ViewHelpers']
    ];

    /**
     * @var array
     */
    protected $aliases = [];

    /**
     * @return array
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Adds an alias of a ViewHelper, allowing you to call for example
     *
     * @param string $alias
     * @param string $namespace
     * @param string $identifier
     */
    public function addViewHelperAlias(string $alias, string $namespace, string $identifier)
    {
        $this->aliases[$alias] = [$namespace, $identifier];
    }

    public function isAliasRegistered(string $alias): bool
    {
        return isset($this->aliases[$alias]);
    }

    /**
     * Add a PHP namespace where ViewHelpers can be found and give
     * it an alias/identifier.
     *
     * The provided namespace can be either a single namespace or
     * an array of namespaces, as strings. The identifier/alias is
     * always a single, alpha-numeric ASCII string.
     *
     * Calling this method multiple times with different PHP namespaces
     * for the same alias causes that namespace to be *extended*,
     * meaning that the PHP namespace you provide second, third etc.
     * are also used in lookups and are used *first*, so that if any
     * of the namespaces you add contains a class placed and named the
     * same way as one that exists in an earlier namespace, then your
     * class gets used instead of the earlier one.
     *
     * Example:
     *
     * $resolver->addNamespace('my', 'My\Package\ViewHelpers');
     * // Any ViewHelpers under this namespace can now be accessed using for example {my:example()}
     * // Now, assuming you also have an ExampleViewHelper class in a different
     * // namespace and wish to make that ExampleViewHelper override the other:
     * $resolver->addNamespace('my', 'My\OtherPackage\ViewHelpers');
     * // Now, since ExampleViewHelper exists in both places but the
     * // My\OtherPackage\ViewHelpers namespace was added *last*, Fluid
     * // will find and use My\OtherPackage\ViewHelpers\ExampleViewHelper.
     *
     * Alternatively, setNamespaces() can be used to reset and redefine
     * all previously added namespaces - which is great for cases where
     * you need to remove or replace previously added namespaces. Be aware
     * that setNamespaces() also removes the default "f" namespace, so
     * when you use this method you should always include the "f" namespace.
     *
     * @param string $identifier
     * @param string|array $phpNamespace
     * @return void
     */
    public function addNamespace(string $identifier, $phpNamespace): void
    {
        if (!array_key_exists($identifier, $this->namespaces) || $this->namespaces[$identifier] === null) {
            $this->namespaces[$identifier] = $phpNamespace === null ? null : (array) $phpNamespace;
        } elseif (is_array($phpNamespace)) {
            $this->namespaces[$identifier] = array_unique(array_merge($this->namespaces[$identifier], $phpNamespace));
        } elseif (isset($this->namespaces[$identifier]) && !in_array($phpNamespace, $this->namespaces[$identifier])) {
            $this->namespaces[$identifier][] = $phpNamespace;
        }
    }

    /**
     * Wrapper to allow adding namespaces in bulk *without* first
     * clearing the already added namespaces. Utility method mainly
     * used in compiled templates, where some namespaces can be added
     * from outside and some can be added from compiled values.
     *
     * @param array $namespaces
     * @return void
     */
    public function addNamespaces(array $namespaces): void
    {
        foreach ($namespaces as $identifier => $namespace) {
            $this->addNamespace($identifier, $namespace);
        }
    }

    /**
     * Resolves the PHP namespace based on the Fluid xmlns namespace,
     * which can be either a URL matching the Patterns::NAMESPACEPREFIX
     * and Patterns::NAMESPACESUFFIX rules, or a PHP namespace. When
     * namespace is a PHP namespace it is optional to suffix it with
     * the "\ViewHelpers" segment, e.g. "My\Package" is as valid to
     * use as "My\Package\ViewHelpers" is.
     *
     * @param string $fluidNamespace
     * @return string
     */
    public function resolvePhpNamespaceFromFluidNamespace(string $fluidNamespace): string
    {
        $namespace = $fluidNamespace;
        $suffixLength = strlen(Patterns::NAMESPACESUFFIX);
        $phpNamespaceSuffix = str_replace('/', '\\', Patterns::NAMESPACESUFFIX);
        $extractedSuffix = substr($fluidNamespace, 0 - $suffixLength);
        if (strpos($fluidNamespace, Patterns::NAMESPACEPREFIX) === 0 && $extractedSuffix === Patterns::NAMESPACESUFFIX) {
            // convention assumed: URL starts with prefix and ends with suffix
            $namespace = substr($fluidNamespace, strlen(Patterns::NAMESPACEPREFIX));
        }
        $namespace = str_replace('/', '\\', $namespace);
        if (substr($namespace, 0 - strlen($phpNamespaceSuffix)) !== $phpNamespaceSuffix) {
            $namespace .= $phpNamespaceSuffix;
        }
        return $namespace;
    }

    /**
     * Set all namespaces as an array of ['identifier' => ['Php\Namespace1', 'Php\Namespace2']]
     * namespace definitions. For convenience and legacy support, a
     * format of ['identifier' => 'Only\Php\Namespace'] is allowed,
     * but will internally convert the namespace to an array and
     * allow it to be extended by addNamespace().
     *
     * Note that when using this method the default "f" namespace is
     * also removed and so must be included in $namespaces or added
     * after using addNamespace(). Or, add the PHP namespaces that
     * belonged to "f" as a new alias and use that in your templates.
     *
     * Use getNamespaces() to get an array of currently added namespaces.
     *
     * @param array $namespaces
     * @return void
     */
    public function setNamespaces(array $namespaces): void
    {
        $this->namespaces = [];
        foreach ($namespaces as $identifier => $phpNamespace) {
            $this->namespaces[$identifier] = $phpNamespace === null ? null : (array) $phpNamespace;
        }
    }

    /**
     * Validates the given namespaceIdentifier and returns FALSE
     * if the namespace is unknown, causing the tag to be rendered
     * without processing.
     *
     * @param string $namespaceIdentifier
     * @return boolean TRUE if the given namespace is valid, otherwise FALSE
     */
    public function isNamespaceValid(string $namespaceIdentifier): bool
    {
        if (!array_key_exists($namespaceIdentifier, $this->namespaces)) {
            return false;
        }

        return $this->namespaces[$namespaceIdentifier] !== null;
    }

    /**
     * Validates the given namespaceIdentifier and returns FALSE
     * if the namespace is unknown and not ignored
     *
     * @param string $namespaceIdentifier
     * @return boolean TRUE if the given namespace is valid, otherwise FALSE
     */
    public function isNamespaceValidOrIgnored(string $namespaceIdentifier): bool
    {
        if ($this->isNamespaceValid($namespaceIdentifier)) {
            return true;
        }

        if (array_key_exists($namespaceIdentifier, $this->namespaces)) {
            return true;
        }
        return $this->isNamespaceIgnored($namespaceIdentifier);
    }

    /**
     * @param string $namespaceIdentifier
     * @return boolean
     */
    public function isNamespaceIgnored(string $namespaceIdentifier): bool
    {
        if (array_key_exists($namespaceIdentifier, $this->namespaces) && $this->namespaces[$namespaceIdentifier] === null) {
            return true;
        }
        foreach (array_keys($this->namespaces) as $existingNamespaceIdentifier) {
            if (strpos($existingNamespaceIdentifier, '*') === false) {
                continue;
            }
            $pattern = '/' . str_replace(['.', '*'], ['\\.', '[a-zA-Z0-9\.]*'], $existingNamespaceIdentifier) . '/';
            if (preg_match($pattern, $namespaceIdentifier) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Resolves a ViewHelper class name by namespace alias and
     * Fluid-format identity, e.g. "f" and "format.htmlspecialchars".
     *
     * Looks in all PHP namespaces which have been added for the
     * provided alias, starting in the last added PHP namespace. If
     * a ViewHelper class exists in multiple PHP namespaces Fluid
     * will detect and use whichever one was added last.
     *
     * If no ViewHelper class can be detected in any of the added
     * PHP namespaces a Fluid Parser Exception is thrown.
     *
     * @param string|null $namespaceIdentifier
     * @param string $methodIdentifier
     * @return string|null
     * @throws Exception
     */
    public function resolveViewHelperClassName(?string $namespaceIdentifier, string $methodIdentifier): ?string
    {
        if (empty($namespaceIdentifier) && isset($this->aliases[$methodIdentifier])) {
            list ($namespaceIdentifier, $methodIdentifier) = $this->aliases[$methodIdentifier];
        }
        if (!isset($this->resolvedViewHelperClassNames[$namespaceIdentifier][$methodIdentifier])) {
            $resolvedViewHelperClassName = $this->resolveViewHelperName($namespaceIdentifier, $methodIdentifier);
            $actualViewHelperClassName = implode('\\', array_map('ucfirst', explode('.', $resolvedViewHelperClassName)));
            if (!class_exists($actualViewHelperClassName) || $actualViewHelperClassName === false) {
                throw new Exception(sprintf(
                    'The ViewHelper "<%s:%s>" could not be resolved.' . chr(10) .
                    'Based on your spelling, the system would load the class "%s", however this class does not exist. ' .
                    'We looked in the following namespaces: ' . implode(', ', $this->namespaces[$namespaceIdentifier] ?? ['none']) . '.',
                    $namespaceIdentifier,
                    $methodIdentifier,
                    $resolvedViewHelperClassName
                ), 1407060572);
            }
            $this->resolvedViewHelperClassNames[$namespaceIdentifier][$methodIdentifier] = $actualViewHelperClassName;
        }
        return $this->resolvedViewHelperClassNames[$namespaceIdentifier][$methodIdentifier];
    }

    /**
     * Can be overridden by custom implementations to change the way
     * classes are loaded when the class is a ViewHelper - for
     * example making it possible to use a DI-aware class loader.
     *
     * If null is passed as namespace, only registered ViewHelper
     * aliases are checked against the $viewHelperShortName.
     *
     * @param string|null $namespace
     * @param string $viewHelperShortName
     * @return ViewHelperInterface
     */
    public function createViewHelperInstance(?string $namespace, string $viewHelperShortName): ViewHelperInterface
    {
        $className = $this->resolveViewHelperClassName($namespace, $viewHelperShortName);
        return $this->createViewHelperInstanceFromClassName($className);
    }

    /**
     * Wrapper to create a ViewHelper instance by class name. This is
     * the final method called when creating ViewHelper classes -
     * overriding this method allows custom constructors, dependency
     * injections etc. to be performed on the ViewHelper instance.
     *
     * @param string $viewHelperClassName
     * @return ViewHelperInterface
     */
    public function createViewHelperInstanceFromClassName(string $viewHelperClassName): ViewHelperInterface
    {
        return new $viewHelperClassName();
    }

    /**
     * Return an array of ArgumentDefinition instances which describe
     * the arguments that the ViewHelper supports. By default, the
     * arguments are simply fetched from the ViewHelper - but custom
     * implementations can if necessary add/remove/replace arguments
     * which will be passed to the ViewHelper.
     *
     * @param ViewHelperInterface $viewHelper
     * @return ArgumentDefinition[]
     */
    public function getArgumentDefinitionsForViewHelper(ViewHelperInterface $viewHelper): array
    {
        return $viewHelper->prepareArguments();
    }

    /**
     * Resolve a viewhelper name.
     *
     * @param string $namespaceIdentifier Namespace identifier for the view helper.
     * @param string $methodIdentifier Method identifier, might be hierarchical like "link.url"
     * @return string The fully qualified class name of the viewhelper
     */
    protected function resolveViewHelperName(string $namespaceIdentifier, string $methodIdentifier): string
    {
        $explodedViewHelperName = explode('.', $methodIdentifier);
        if (count($explodedViewHelperName) > 1) {
            $className = implode('\\', array_map('ucfirst', $explodedViewHelperName));
        } else {
            $className = ucfirst($explodedViewHelperName[0]);
        }
        $className .= 'ViewHelper';

        $namespaces = (array) $this->namespaces[$namespaceIdentifier];

        do {
            $name = rtrim(array_pop($namespaces), '\\') . '\\' . $className;
        } while (!class_exists($name) && count($namespaces));

        return $name;
    }
}
