<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser;

use Codeviastudio\FeatureTraverser\Analyzer\AstAnalyzer;
use Codeviastudio\FeatureTraverser\Config\TraversalConfig;
use Codeviastudio\FeatureTraverser\Resolver\ClassResolver;
use Codeviastudio\FeatureTraverser\Result\Reference;
use Codeviastudio\FeatureTraverser\Result\ReferenceCollection;
use Codeviastudio\FeatureTraverser\Result\ReferenceType;

final readonly class FeatureTraverser
{
    public function __construct(
        private ClassResolver $classResolver,
        private AstAnalyzer $astAnalyzer,
    ) {
    }

    public function traverse(TraversalConfig $config): ReferenceCollection
    {
        $collection = new ReferenceCollection();
        $visited = []; // Track visited classes/methods to avoid infinite loops

        $entryPoint = $config->getEntryPoint();
        $className = $entryPoint->getClassName();
        $methodName = $entryPoint->getMethodName();

        // Resolve entry point file
        $filePath = $this->classResolver->resolve($className);
        if ($filePath === null) {
            return $collection;
        }

        // Check if entry point is excluded
        if ($config->isPathExcluded($filePath)) {
            return $collection;
        }

        // Create root reference
        $rootReference = new Reference(
            fullyQualifiedName: $entryPoint->getFullyQualifiedName(),
            type: ReferenceType::METHOD_CALL,
            filePath: $filePath,
            depth: 0,
            parent: null,
            children: [],
            className: $className,
            methodName: $methodName,
        );

        $collection->add($rootReference);

        // Start traversal
        $this->traverseRecursively(
            reference: $rootReference,
            className: $className,
            methodName: $methodName,
            filePath: $filePath,
            config: $config,
            visited: $visited,
            depth: 0,
        );

        return $collection;
    }

    /**
     * @param array<string, bool> $visited
     */
    private function traverseRecursively(
        Reference $reference,
        string $className,
        string $methodName,
        string $filePath,
        TraversalConfig $config,
        array &$visited,
        int $depth,
    ): void {
        // Check max depth
        if ($config->getMaxDepth() > 0 && $depth >= $config->getMaxDepth()) {
            return;
        }

        // Mark as visited
        $visitedKey = $className . '::' . $methodName;
        if (isset($visited[$visitedKey])) {
            return;
        }
        $visited[$visitedKey] = true;

        // Analyze the file
        $calls = $this->astAnalyzer->analyzeMethod($filePath, $className, $methodName);

        foreach ($calls as $call) {
            $callClass = $call['class'];
            $callMethod = $call['method'];

            // Skip if no class information (can't resolve further)
            if ($callClass === null) {
                continue;
            }

            // Resolve the called class file
            $callFilePath = $this->classResolver->resolve($callClass);
            if ($callFilePath === null) {
                continue;
            }

            // Check if excluded
            if ($config->isPathExcluded($callFilePath)) {
                continue;
            }

            // Determine reference type
            $referenceType = match ($call['type']) {
                'method_call' => ReferenceType::METHOD_CALL,
                'static_call' => ReferenceType::STATIC_CALL,
                'function_call' => ReferenceType::FUNCTION_CALL,
                'class_instantiation' => ReferenceType::CLASS_INSTANTIATION,
                'parameter_type' => ReferenceType::PARAMETER_TYPE,
                default => ReferenceType::METHOD_CALL,
            };

            // For parameter types, we don't need to recurse (no method to analyze)
            // Just add the class to the collection
            if ($call['type'] === 'parameter_type') {
                $childReference = new Reference(
                    fullyQualifiedName: $callClass,
                    type: $referenceType,
                    filePath: $callFilePath,
                    depth: $depth + 1,
                    parent: $reference,
                    children: [],
                    className: $callClass,
                    methodName: null,
                );

                $reference->addChild($childReference);
                // Don't recurse - parameter types are just class definitions without method calls
                continue;
            }

            // At this point, $callMethod must be string (parameter_type was handled above)
            assert($callMethod !== null);

            // Create child reference for method/static calls/instantiations
            $childReference = new Reference(
                fullyQualifiedName: $callClass . '::' . $callMethod,
                type: $referenceType,
                filePath: $callFilePath,
                depth: $depth + 1,
                parent: $reference,
                children: [],
                className: $callClass,
                methodName: $callMethod,
            );

            $reference->addChild($childReference);

            // Recurse into child
            $this->traverseRecursively(
                reference: $childReference,
                className: $callClass,
                methodName: $callMethod,
                filePath: $callFilePath,
                config: $config,
                visited: $visited,
                depth: $depth + 1,
            );
        }
    }
}
