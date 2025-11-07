<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Caches parsed AST trees to avoid re-parsing the same files
 */
final class ParserCache
{
    /**
     * @var array<string, array<Node\Stmt>>
     */
    private array $cache = [];

    /**
     * Get parsed and resolved AST for a file
     *
     * @param string $filePath Path to the file
     * @return array<Node\Stmt>|null Parsed statements or null on error
     */
    public function getAst(string $filePath): ?array
    {
        // Return from cache if available
        if (isset($this->cache[$filePath])) {
            return $this->cache[$filePath];
        }

        // Parse and cache
        $ast = $this->parseFile($filePath);
        if ($ast !== null) {
            $this->cache[$filePath] = $ast;
        }

        return $ast;
    }

    /**
     * Parse a file and apply NameResolver
     *
     * @param string $filePath Path to the file
     * @return array<Node\Stmt>|null Parsed statements or null on error
     */
    private function parseFile(string $filePath): ?array
    {
        try {
            $code = file_get_contents($filePath);
            if ($code === false) {
                return null;
            }

            $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
            $stmts = $parser->parse($code);
            if ($stmts === null) {
                return null;
            }

            // Apply NameResolver to resolve all class names to FQCN
            $traverser = new NodeTraverser();
            $traverser->addVisitor(new NameResolver());
            /** @var array<Node\Stmt> $resolvedStmts */
            $resolvedStmts = $traverser->traverse($stmts);

            return $resolvedStmts;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Clear the cache
     */
    public function clear(): void
    {
        $this->cache = [];
    }

    /**
     * Get number of cached files
     */
    public function count(): int
    {
        return count($this->cache);
    }
}
