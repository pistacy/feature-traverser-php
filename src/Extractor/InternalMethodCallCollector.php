<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Extractor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Collects internal method calls (e.g., $this->methodName()) from class methods
 *
 * This helps identify helper methods that should be included in the extraction.
 */
final class InternalMethodCallCollector extends NodeVisitorAbstract
{
    /**
     * @var array<string, bool> Map of method names called internally
     */
    private array $internalCalls = [];

    private bool $insideMethod = false;

    public function enterNode(Node $node): ?Node
    {
        // Track when we're inside a method
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->insideMethod = true;
        }

        // Collect $this->methodName() calls
        if ($this->insideMethod && $node instanceof Node\Expr\MethodCall) {
            if ($node->var instanceof Node\Expr\Variable && $node->var->name === 'this') {
                if ($node->name instanceof Node\Identifier) {
                    $methodName = $node->name->toString();
                    $this->internalCalls[$methodName] = true;
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        // Track when we leave a method
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->insideMethod = false;
        }

        return null;
    }

    /**
     * Get all internal method calls found
     *
     * @return array<string> Array of method names
     */
    public function getInternalCalls(): array
    {
        return array_keys($this->internalCalls);
    }
}
