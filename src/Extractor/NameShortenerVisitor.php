<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Extractor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Converts fully qualified names back to short names when use statements exist
 *
 * This visitor works with namespace-scoped use statements.
 * Each namespace maintains its own set of use statements.
 */
final class NameShortenerVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, string> Map of FQCN to alias for current namespace
     */
    private array $currentNamespaceUses = [];

    private ?string $currentNamespace = null;

    public function enterNode(Node $node): ?Node
    {
        // Entering a namespace - collect its use statements
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name?->toString() ?? '';
            $this->currentNamespaceUses = [];

            // Collect use statements from this namespace
            foreach ($node->stmts as $stmt) {
                if ($stmt instanceof Node\Stmt\Use_) {
                    foreach ($stmt->uses as $use) {
                        $fqcn = $use->name->toString();
                        $aliasNode = $use->getAlias();
                        $alias = $aliasNode !== null ? $aliasNode->toString() : $use->name->getLast();
                        $this->currentNamespaceUses[$fqcn] = $alias;
                    }
                }
            }
        }

        return null;
    }

    public function leaveNode(Node $node): ?Node
    {
        // Convert FullyQualified names to short names using current namespace's use statements
        if ($node instanceof Node\Name\FullyQualified) {
            $fqcn = $node->toString();

            // Check current namespace's use statements
            if (isset($this->currentNamespaceUses[$fqcn])) {
                return new Node\Name($this->currentNamespaceUses[$fqcn]);
            }

            // Check if it's in the current namespace
            if ($this->currentNamespace !== null && str_starts_with($fqcn, $this->currentNamespace . '\\')) {
                $shortName = substr($fqcn, strlen($this->currentNamespace) + 1);
                return new Node\Name($shortName);
            }
        }

        return null;
    }
}
