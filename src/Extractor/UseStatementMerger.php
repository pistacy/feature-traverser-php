<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Extractor;

use PhpParser\Node;

/**
 * Merges all use statements from multiple namespaces into a single list at the top of the file
 */
final class UseStatementMerger
{
    /**
     * Collect all use statements from all namespaces and place them at the top of the file,
     * before any namespace declarations
     *
     * @param array<Node\Stmt> $statements
     * @return array<Node\Stmt>
     */
    public function merge(array $statements): array
    {
        $allUseStatements = [];
        $allNamespaces = [];

        // Collect all use statements from all namespaces
        foreach ($statements as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                $cleanedStmts = [];

                foreach ($stmt->stmts as $namespaceStmt) {
                    if ($namespaceStmt instanceof Node\Stmt\Use_) {
                        // Collect use statements
                        foreach ($namespaceStmt->uses as $use) {
                            $fqcn = $use->name->toString();
                            $alias = $use->getAlias();
                            $aliasName = $alias !== null ? $alias->toString() : null;
                            $key = $aliasName !== null ? $fqcn . ' as ' . $aliasName : $fqcn;

                            if (!isset($allUseStatements[$key])) {
                                $allUseStatements[$key] = $use;
                            }
                        }
                    } else {
                        // Add non-use statements to the namespace
                        $cleanedStmts[] = $namespaceStmt;
                    }
                }

                // Create namespace without use statements
                $namespace = new Node\Stmt\Namespace_(
                    $stmt->name,
                    $cleanedStmts
                );
                $allNamespaces[] = $namespace;
            } else {
                // Keep non-namespace statements (should be rare in our case)
                $allNamespaces[] = $stmt;
            }
        }

        // Sort use statements alphabetically
        ksort($allUseStatements);

        // Create individual Use statements
        $useNodes = [];
        foreach ($allUseStatements as $use) {
            $useNodes[] = new Node\Stmt\Use_([$use]);
        }

        // Build result: all use statements at the top, then all namespaces
        return array_merge($useNodes, $allNamespaces);
    }
}
