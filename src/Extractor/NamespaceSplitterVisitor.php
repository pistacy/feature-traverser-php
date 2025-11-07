<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Extractor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Splits namespaces containing multiple classes/interfaces into separate namespace blocks
 *
 * This ensures each class/interface gets its own namespace block with use statements,
 * which is optimal for LLM code analysis.
 */
final class NamespaceSplitterVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): array|null
    {
        // Only process namespace nodes
        if (!$node instanceof Node\Stmt\Namespace_) {
            return null;
        }

        // Separate use statements from class/interface/trait definitions
        $useStatements = [];
        $classLikeStatements = [];

        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                $useStatements[] = $stmt;
            } elseif (
                $stmt instanceof Node\Stmt\Class_
                || $stmt instanceof Node\Stmt\Interface_
                || $stmt instanceof Node\Stmt\Trait_
            ) {
                $classLikeStatements[] = $stmt;
            }
        }

        // If there's 0 or 1 class-like statement, keep namespace as-is
        if (count($classLikeStatements) <= 1) {
            return null;
        }

        // Create separate namespace blocks for each class/interface/trait
        $newNamespaceBlocks = [];

        foreach ($classLikeStatements as $classLike) {
            $newNamespaceBlocks[] = new Node\Stmt\Namespace_(
                $node->name,
                array_merge($useStatements, [$classLike])
            );
        }

        // Return array to replace the single namespace with multiple namespace blocks
        return $newNamespaceBlocks;
    }
}
