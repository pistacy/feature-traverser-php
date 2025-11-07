<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Extractor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Removes unused use statements from each namespace
 *
 * This visitor works with namespace-scoped use statements.
 * Each namespace is processed independently.
 */
final class UnusedUseRemover extends NodeVisitorAbstract
{
    /**
     * @var array<string, array<string, bool>> Used class names per namespace
     */
    private array $usedNamesByNamespace = [];

    /**
     * @var array<string, array<string, Node\Stmt\UseUse>> Use statements per namespace (map: namespace => FQCN => UseUse)
     */
    private array $useStatementsByNamespace = [];

    private ?string $currentNamespace = null;

    public function enterNode(Node $node): ?Node
    {
        // Track namespace and collect use statements
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name?->toString() ?? '';

            if (!isset($this->usedNamesByNamespace[$this->currentNamespace])) {
                $this->usedNamesByNamespace[$this->currentNamespace] = [];
            }

            // Collect use statements from this namespace
            if (!isset($this->useStatementsByNamespace[$this->currentNamespace])) {
                $this->useStatementsByNamespace[$this->currentNamespace] = [];
            }

            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Use_) {
                    foreach ($stmt->uses as $use) {
                        $fqcn = $use->name->toString();
                        $this->useStatementsByNamespace[$this->currentNamespace][$fqcn] = $use;
                    }
                }
            }
        }

        // Track used names in current namespace
        if ($node instanceof Node\Name && $this->currentNamespace !== null) {
            $name = $node->toString();
            // Get the first part of the name (the class name or alias)
            $parts = explode('\\', $name);
            if (count($parts) > 0) {
                $this->usedNamesByNamespace[$this->currentNamespace][$parts[0]] = true;
            }
        }

        return null;
    }

    public function leaveNode(Node $node): Node
    {
        // Process namespace and rebuild with only used imports
        if ($node instanceof Node\Stmt\Namespace_ && $this->currentNamespace !== null) {
            $usedUses = [];
            $usesInNamespace = $this->useStatementsByNamespace[$this->currentNamespace] ?? [];
            $usedNames = $this->usedNamesByNamespace[$this->currentNamespace] ?? [];

            foreach ($usesInNamespace as $fqcn => $use) {
                $aliasNode = $use->getAlias();
                $alias = $aliasNode !== null ? $aliasNode->toString() : $use->name->getLast();

                // Check if this use statement is actually used
                if (isset($usedNames[$alias])) {
                    $usedUses[] = new Node\Stmt\Use_([$use]);
                }
            }

            // Rebuild namespace with used imports + other statements
            $newStmts = [];

            // Add used use statements first
            foreach ($usedUses as $useStmt) {
                $newStmts[] = $useStmt;
            }

            // Add non-use statements
            foreach ($node->stmts as $stmt) {
                if (!$stmt instanceof Node\Stmt\Use_) {
                    $newStmts[] = $stmt;
                }
            }

            $node->stmts = $newStmts;
            return $node;
        }

        return $node;
    }
}
