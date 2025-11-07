<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Extractor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Filters class methods to keep only specified ones
 */
final class MethodFilterVisitor extends NodeVisitorAbstract
{
    /**
     * @param array<string> $methodNames Method names to keep
     */
    public function __construct(
        private readonly array $methodNames,
    ) {
    }

    public function leaveNode(Node $node): Node
    {
        // Filter methods in classes
        if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_ || $node instanceof Node\Stmt\Trait_) {
            $filteredStmts = [];

            foreach ($node->stmts as $stmt) {
                // Keep non-method statements (properties, constants, etc.)
                if (!$stmt instanceof Node\Stmt\ClassMethod) {
                    $filteredStmts[] = $stmt;
                    continue;
                }

                $methodName = $stmt->name->toString();

                // Always keep constructor (needed to understand dependencies)
                if ($methodName === '__construct') {
                    $filteredStmts[] = $stmt;
                    continue;
                }

                // Keep method if it's in the list
                if (in_array($methodName, $this->methodNames, true)) {
                    $filteredStmts[] = $stmt;
                }
            }

            $node->stmts = $filteredStmts;
        }

        return $node;
    }
}
