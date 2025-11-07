<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Analyzer;

use Codeviastudio\FeatureTraverser\Analyzer\NodeVisitor\CallCollectorVisitor;
use Codeviastudio\FeatureTraverser\Parser\ParserCache;
use PhpParser\NodeTraverser;

final readonly class AstAnalyzer
{
    public function __construct(
        private ?ParserCache $parserCache = null,
    ) {
    }

    /**
     * Analyze a file and return all calls found
     *
     * @return array<array{type: string, class: string|null, method: string|null, line: int}>
     */
    public function analyzeFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        // Get AST from cache or parse directly if no cache
        $ast = $this->getAst($filePath);
        if ($ast === null) {
            return [];
        }

        // Collect calls from AST
        $visitor = new CallCollectorVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getCalls();
    }

    /**
     * Analyze a file and return calls from a specific method
     *
     * @return array<array{type: string, class: string|null, method: string|null, line: int}>
     */
    public function analyzeMethod(string $filePath, string $className, string $methodName): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        // Get AST from cache or parse directly if no cache
        $ast = $this->getAst($filePath);
        if ($ast === null) {
            return [];
        }

        // Collect calls from specific method
        $visitor = new CallCollectorVisitor();
        $visitor->setTargetMethod($className, $methodName);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getCalls();
    }

    /**
     * Get AST for a file (from cache or by parsing)
     *
     * @return array<\PhpParser\Node\Stmt>|null
     */
    private function getAst(string $filePath): ?array
    {
        if ($this->parserCache !== null) {
            return $this->parserCache->getAst($filePath);
        }

        // No cache, create a temporary one for this call
        $tempCache = new ParserCache();
        return $tempCache->getAst($filePath);
    }
}
