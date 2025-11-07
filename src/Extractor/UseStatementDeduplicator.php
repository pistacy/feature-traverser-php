<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Extractor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Deduplicates and sorts use statements within each namespace
 *
 * Ensures that each namespace has a clean, sorted list of unique use statements.
 */
final class UseStatementDeduplicator extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): ?Node
    {
        // Only process namespace nodes
        if (!$node instanceof Node\Stmt\Namespace_) {
            return null;
        }

        $useStatements = [];
        $otherStatements = [];

        // Separate use statements from other statements
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                foreach ($stmt->uses as $use) {
                    $fqcn = $use->name->toString();
                    $aliasNode = $use->getAlias();
                    $key = $aliasNode !== null
                        ? $fqcn . ' as ' . $aliasNode->toString()
                        : $fqcn;

                    // Keep only unique use statements
                    $useStatements[$key] = $use;
                }
            } else {
                $otherStatements[] = $stmt;
            }
        }

        // Sort use statements alphabetically
        ksort($useStatements);

        // Rebuild namespace with sorted, deduplicated use statements
        $newStmts = [];

        foreach ($useStatements as $use) {
            $newStmts[] = new Node\Stmt\Use_([$use]);
        }

        foreach ($otherStatements as $stmt) {
            $newStmts[] = $stmt;
        }

        $node->stmts = $newStmts;

        return $node;
    }
}
