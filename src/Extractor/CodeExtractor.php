<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Extractor;

use Pistacy\FeatureTraverser\Parser\ParserCache;
use Pistacy\FeatureTraverser\Result\ReferenceCollection;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

final readonly class CodeExtractor
{
    public function __construct(
        private ?ParserCache $parserCache = null,
    ) {
    }
    /**
     * Extract code from files based on traversal results
     *
     * This method takes a ReferenceCollection and extracts only the classes and methods
     * that were found during traversal, creating a single PHP string containing all
     * the relevant code with namespace and use statements preserved.
     *
     * @param ReferenceCollection $collection The collection of references from traversal
     * @param string $projectRoot Project root directory for relative paths in comments
     * @return string A single PHP file containing all extracted code
     */
    public function extract(ReferenceCollection $collection, string $projectRoot = ''): string
    {
        // Create parser for re-parsing during minimization
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);

        $methodsPerFile = $collection->getMethodsUsedPerFile();
        $allStatements = [];
        $filePathMap = []; // Map of class FQCN to file path

        foreach ($methodsPerFile as $filePath => $methods) {
            $fileStatements = $this->extractFromFile($filePath, $methods);
            if ($fileStatements !== null) {
                // Build file path map for comments
                $this->collectClassesFromStatements($fileStatements, $filePath, $filePathMap);

                // Each file's statements should be kept separate to preserve namespace boundaries
                foreach ($fileStatements as $stmt) {
                    $allStatements[] = $stmt;
                }
            }
        }

        // Remove all declare statements (they will be added at the top)
        $allStatements = $this->removeDeclareStatements($allStatements);

        // Split namespaces so each class gets its own namespace block with use statements
        $splitterTraverser = new NodeTraverser();
        $splitterTraverser->addVisitor(new NamespaceSplitterVisitor());
        /** @var array<Node\Stmt> $allStatements */
        $allStatements = $splitterTraverser->traverse($allStatements);

        // Shorten fully qualified names to use short names with use statements
        $shortenerTraverser = new NodeTraverser();
        $shortenerTraverser->addVisitor(new NameShortenerVisitor());
        /** @var array<Node\Stmt> $allStatements */
        $allStatements = $shortenerTraverser->traverse($allStatements);

        // Remove unused use statements from each namespace
        $cleanupTraverser = new NodeTraverser();
        $cleanupTraverser->addVisitor(new UnusedUseRemover());
        /** @var array<Node\Stmt> $allStatements */
        $allStatements = $cleanupTraverser->traverse($allStatements);

        // Deduplicate and sort use statements within each namespace
        $deduplicateTraverser = new NodeTraverser();
        $deduplicateTraverser->addVisitor(new UseStatementDeduplicator());
        /** @var array<Node\Stmt> $allStatements */
        $allStatements = $deduplicateTraverser->traverse($allStatements);

        // Add file path comments before each class
        $commentTraverser = new NodeTraverser();
        $commentTraverser->addVisitor(new FilePathCommentAdder($filePathMap, $projectRoot));
        /** @var array<Node\Stmt> $allStatements */
        $allStatements = $commentTraverser->traverse($allStatements);

        // Generate code from AST
        $printer = new Standard([
            'shortArraySyntax' => true,
        ]);
        $code = "<?php\n\ndeclare(strict_types=1);\n\n" . $printer->prettyPrint($allStatements);

        // Minimize code for LLM: remove empty lines and leading whitespace
        return $this->minimizeCodeForLlm($code, $parser);
    }

    /**
     * Minimize code for LLM by removing empty lines, leading whitespace, and unnecessary syntax
     *
     * This reduces token count significantly while keeping code semantically complete.
     */
    private function minimizeCodeForLlm(string $code, Parser $parser): string
    {
        // Remove declare(strict_types=1); from the beginning
        $result = preg_replace('/declare\(strict_types=1\);\s*/', '', $code);
        assert(is_string($result));
        $code = $result;

        // Shorten file path comments
        // Remove /var/www/current/src/ prefix
        $result = preg_replace('|// File: /var/www/current/src/|', '// ', $code);
        assert(is_string($result));
        $code = $result;

        // Shorten UserInterface to UI
        $code = str_replace('UserInterface', 'UI', $code);

        // Remove 'final' and/or 'readonly' keywords from classes
        // Handles: 'final class', 'readonly class', 'final readonly class', 'readonly final class'
        $result = preg_replace('/\b(?:final\s+)?(?:readonly\s+)?class\b/', 'class', $code);
        assert(is_string($result));
        $code = $result;

        $result = preg_replace('/\b(?:readonly\s+)?(?:final\s+)?class\b/', 'class', $code);
        assert(is_string($result));
        $code = $result;

        // Remove type declarations from properties but keep visibility modifiers
        // Also remove 'readonly' from properties
        // Pattern: (private|protected|public) [readonly] Type $property; -> (private|protected|public) $property;
        $result = preg_replace('/(private|protected|public)\s+(?:readonly\s+)?[A-Za-z0-9_\\\\|?]+\s+(\$[a-zA-Z0-9_]+)\s*;/', '$1 $2;', $code);
        assert(is_string($result));
        $code = $result;

        // Remove 'readonly' from constructor promoted properties
        // Pattern: public readonly Type $property -> public Type $property
        $result = preg_replace('/(private|protected|public)\s+readonly\s+/', '$1 ', $code);
        assert(is_string($result));
        $code = $result;

        // Now re-parse the code to remove unused use statements after type removal
        $ast = $parser->parse($code);
        assert(is_array($ast));

        // Run UnusedUseRemover twice to ensure all unused imports are caught
        // First pass collects usage, second pass removes unused
        $traverser = new NodeTraverser();
        $unusedUseRemover = new UnusedUseRemover();
        $traverser->addVisitor($unusedUseRemover);
        /** @var array<Node\Stmt> $ast */
        $ast = $traverser->traverse($ast);

        // Second pass with fresh remover
        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor(new UnusedUseRemover());
        /** @var array<Node\Stmt> $ast */
        $ast = $traverser2->traverse($ast);

        // Re-generate code
        $printer = new Standard([
            'shortArraySyntax' => true,
        ]);
        $code = "<?php\n\n" . $printer->prettyPrint($ast);

        // Remove spaces around colons and commas in signatures
        $result = preg_replace('/ *: */', ':', $code);
        assert(is_string($result));
        $code = $result;

        $result = preg_replace('/ *, */', ',', $code);
        assert(is_string($result));
        $code = $result;

        // Split into lines
        $lines = explode("\n", $code);
        $minimizedLines = [];

        foreach ($lines as $line) {
            // Skip completely empty lines
            if (trim($line) === '') {
                continue;
            }

            // Remove leading whitespace (indentation)
            $minimizedLines[] = ltrim($line);
        }

        // Join with newlines
        return implode("\n", $minimizedLines);
    }

    /**
     * Extract specific methods from a file
     *
     * @param string $filePath Path to the file
     * @param array<string> $methodNames Methods to extract
     * @return array<Node\Stmt>|null Array of statements or null on error
     */
    private function extractFromFile(string $filePath, array $methodNames): ?array
    {
        // Get AST from cache or parse directly if no cache
        $stmts = $this->getAst($filePath);
        if ($stmts === null) {
            return null;
        }

        // Expand method list to include internal method calls (helper methods)
        $expandedMethodNames = $this->expandMethodListWithInternalCalls($stmts, $methodNames);

        // Filter statements to keep only relevant methods
        $filterTraverser = new NodeTraverser();
        $filterTraverser->addVisitor(new MethodFilterVisitor($expandedMethodNames));
        $filterTraverser->addVisitor(new CodeCleanerVisitor());
        /** @var array<Node\Stmt> $filteredStmts */
        $filteredStmts = $filterTraverser->traverse($stmts);

        return $filteredStmts;
    }

    /**
     * Expand method list to include all internal method calls (helper methods)
     *
     * This ensures that private/protected methods called by public methods are also extracted.
     *
     * @param array<Node\Stmt> $stmts
     * @param array<string> $methodNames
     * @return array<string>
     */
    private function expandMethodListWithInternalCalls(array $stmts, array $methodNames): array
    {
        $allMethods = $methodNames;
        $processedMethods = [];

        // Keep iterating until no new methods are found
        while (count($allMethods) > count($processedMethods)) {
            $newMethods = array_diff($allMethods, $processedMethods);

            foreach ($newMethods as $methodName) {
                // Mark as processed
                $processedMethods[] = $methodName;

                // Find internal calls in this method
                $internalCalls = $this->findInternalCallsInMethod($stmts, $methodName);

                // Add any new internal calls to the list
                foreach ($internalCalls as $internalCall) {
                    if (!in_array($internalCall, $allMethods, true)) {
                        $allMethods[] = $internalCall;
                    }
                }
            }
        }

        return $allMethods;
    }

    /**
     * Find all internal method calls ($this->method()) in a specific method
     *
     * @param array<Node\Stmt> $stmts
     * @param string $methodName
     * @return array<string>
     */
    private function findInternalCallsInMethod(array $stmts, string $methodName): array
    {
        $collector = new InternalMethodCallCollector();
        $targetMethodCollector = new class ($methodName, $collector) extends NodeVisitorAbstract {
            private bool $insideTargetMethod = false;

            public function __construct(
                private readonly string $targetMethodName,
                private readonly InternalMethodCallCollector $collector
            ) {
            }

            public function enterNode(Node $node): ?Node
            {
                // Check if we're entering the target method
                if ($node instanceof Node\Stmt\ClassMethod) {
                    if ($node->name->toString() === $this->targetMethodName) {
                        $this->insideTargetMethod = true;
                    }
                }

                // Forward to collector if we're inside target method
                if ($this->insideTargetMethod) {
                    $this->collector->enterNode($node);
                }

                return null;
            }

            public function leaveNode(Node $node): ?Node
            {
                // Forward to collector if we're inside target method
                if ($this->insideTargetMethod) {
                    $this->collector->leaveNode($node);
                }

                // Check if we're leaving the target method
                if ($node instanceof Node\Stmt\ClassMethod) {
                    if ($node->name->toString() === $this->targetMethodName) {
                        $this->insideTargetMethod = false;
                    }
                }

                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($targetMethodCollector);
        $traverser->traverse($stmts);

        return $collector->getInternalCalls();
    }

    /**
     * Get AST for a file (from cache or by parsing)
     *
     * @return array<Node\Stmt>|null
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

    /**
     * Collect classes from statements and build a map of class FQCN to file path
     *
     * @param array<Node\Stmt> $statements
     * @param string $filePath
     * @param array<string, string> $filePathMap
     */
    private function collectClassesFromStatements(array $statements, string $filePath, array &$filePathMap): void
    {
        foreach ($statements as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                $namespace = $stmt->name?->toString() ?? '';

                foreach ($stmt->stmts as $namespaceStmt) {
                    if (
                        $namespaceStmt instanceof Node\Stmt\Class_
                        || $namespaceStmt instanceof Node\Stmt\Interface_
                        || $namespaceStmt instanceof Node\Stmt\Trait_
                    ) {
                        $className = $namespaceStmt->name?->toString();
                        if ($className !== null) {
                            $fqcn = $namespace !== '' ? $namespace . '\\' . $className : $className;
                            $filePathMap[$fqcn] = $filePath;
                        }
                    }
                }
            }
        }
    }

    /**
     * Remove declare statements from AST
     *
     * @param array<Node\Stmt> $statements
     * @return array<Node\Stmt>
     */
    private function removeDeclareStatements(array $statements): array
    {
        return array_filter(
            $statements,
            static fn (Node\Stmt $stmt): bool => !$stmt instanceof Node\Stmt\Declare_
        );
    }
}
