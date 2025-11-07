<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Extractor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Adds file path as a comment before each namespace
 *
 * This helps LLM understand the context and origin of each code block.
 */
final class FilePathCommentAdder extends NodeVisitorAbstract
{
    /**
     * @param array<string, string> $filePathMap Map of class FQCN to file path
     * @param string $projectRoot Project root to make paths relative
     */
    public function __construct(
        private readonly array $filePathMap,
        private readonly string $projectRoot = ''
    ) {
    }

    public function enterNode(Node $node): ?Node
    {
        // Add comment before namespace declarations
        if ($node instanceof Node\Stmt\Namespace_) {
            // Find a class in this namespace to get the file path
            $filePath = $this->findFilePathForNamespace($node);
            if ($filePath === null) {
                return null;
            }

            // Make path relative if project root is set
            $displayPath = $filePath;
            if ($this->projectRoot !== '' && str_starts_with($filePath, $this->projectRoot)) {
                $displayPath = substr($filePath, strlen($this->projectRoot));
                $displayPath = ltrim($displayPath, '/');
            }

            // Create single-line comment with file path
            $comment = new \PhpParser\Comment("// File: {$displayPath}\n");

            // Add comment to the node
            $comments = $node->getComments();
            $comments[] = $comment;
            $node->setAttribute('comments', $comments);
        }

        return null;
    }

    /**
     * Find file path for a namespace by checking the map for any class in that namespace
     */
    private function findFilePathForNamespace(Node\Stmt\Namespace_ $namespace): ?string
    {
        $namespaceName = $namespace->name?->toString() ?? '';

        // Find first class/interface/trait in this namespace
        foreach ($namespace->stmts as $stmt) {
            if (
                $stmt instanceof Node\Stmt\Class_
                || $stmt instanceof Node\Stmt\Interface_
                || $stmt instanceof Node\Stmt\Trait_
            ) {
                $className = $stmt->name?->toString();
                if ($className !== null) {
                    $fqcn = $namespaceName !== '' ? $namespaceName . '\\' . $className : $className;

                    // Check if we have file path for this FQCN
                    if (isset($this->filePathMap[$fqcn])) {
                        return $this->filePathMap[$fqcn];
                    }
                }
            }
        }

        return null;
    }
}
