<?php

declare(strict_types=1);

/**
 * Example usage of FeatureTraverser library
 *
 * This script demonstrates how to use the library to traverse code dependencies
 * starting from a controller action.
 */

// Load the library's autoloader
require_once __DIR__ . '/vendor/autoload.php';

use Codeviastudio\FeatureTraverser\Analyzer\AstAnalyzer;
use Codeviastudio\FeatureTraverser\Config\EntryPoint;
use Codeviastudio\FeatureTraverser\Config\TraversalConfig;
use Codeviastudio\FeatureTraverser\Extractor\CodeExtractor;
use Codeviastudio\FeatureTraverser\FeatureTraverser;
use Codeviastudio\FeatureTraverser\Parser\ParserCache;
use Codeviastudio\FeatureTraverser\Resolver\ClassResolver;

// Get the Composer ClassLoader from the main project (for resolving Pistacy classes)
$classLoader = require __DIR__ . '/../../vendor/autoload.php';

// Create ParserCache to avoid parsing files multiple times
$parserCache = new ParserCache();

// Create the traverser with shared cache
$classResolver = new ClassResolver($classLoader);
$astAnalyzer = new AstAnalyzer($parserCache);
$traverser = new FeatureTraverser($classResolver, $astAnalyzer);

// Configure the traversal
$entryPoint = new EntryPoint(
    className: 'Codeviastudio\Pistacy\C4Model\UserInterface\Web\Controller\AddElementToViewController',
    methodName: '__invoke',
);

$config = new TraversalConfig(
    entryPoint: $entryPoint,
    excludedPaths: [
        'tests/',
        'var/',
        'public/',
    ],
    excludedPatterns: [
        '#UserInterface\/Web\/Dto#',
        '#C4Model\/Domain\/Result#',
    ],
    projectRoot: __DIR__ . '/../../',
    maxDepth: 10, // Limit depth to avoid too deep traversal

    // Options to implement:
    //includeExceptions: false, // Whether to include Exceptions code into result or not
    //includeInterfacesBody: false, // Whether to keep interfaces with empty bodies or not
);

// Execute traversal
echo "Starting code traversal from: {$entryPoint->getFullyQualifiedName()}\n";
echo str_repeat('=', 80) . "\n\n";

$collection = $traverser->traverse($config);

if ($collection->isEmpty()) {
    echo "No references found.\n";
    exit(0);
}

// Display results
echo "Found {$collection->count()} root reference(s)\n\n";

foreach ($collection as $reference) {
    printReference($reference, 0);
}

// Print statistics
echo "\n" . str_repeat('=', 80) . "\n";
echo "Statistics:\n";
echo "- Total unique files involved: " . count($collection->getUniqueFilePaths()) . "\n";
echo "\nUnique file paths:\n";
foreach ($collection->getUniqueFilePaths() as $filePath) {
    // Make path relative for better readability
    $relativePath = str_replace(__DIR__ . '/../../', '', $filePath);
    echo "  - $relativePath\n";
}

echo "\n" . str_repeat('=', 80) . "\n";
echo "Methods used per file:\n";
foreach ($collection->getMethodsUsedPerFile() as $filePath => $methods) {
    $relativePath = str_replace(__DIR__ . '/../../', '', $filePath);
    echo "\n$relativePath:\n";
    foreach ($methods as $method) {
        echo "  - $method\n";
    }
}

// Extract code for LLM analysis
echo "\n" . str_repeat('=', 80) . "\n";
echo "Code extraction for LLM:\n";
echo str_repeat('=', 80) . "\n\n";

$codeExtractor = new CodeExtractor($parserCache);
$extractedCode = $codeExtractor->extract($collection, $config->getProjectRoot());

// Display statistics
echo "Extracted code statistics:\n";
echo "- Parser cache hits: " . $parserCache->count() . " files\n";
echo "- Code size: " . strlen($extractedCode) . " bytes\n";
echo "- Code lines: " . substr_count($extractedCode, "\n") . " lines\n";

// Save to file for LLM analysis
$outputFile = __DIR__ . '/extracted_code.php';
file_put_contents($outputFile, $extractedCode);
echo "- Saved to: $outputFile\n";

echo "\nFirst 50 lines of extracted code:\n";
echo str_repeat('-', 80) . "\n";
$lines = explode("\n", $extractedCode);
echo implode("\n", array_slice($lines, 0, 50)) . "\n";
if (count($lines) > 50) {
    echo "\n... (" . (count($lines) - 50) . " more lines)\n";
}

/**
 * Helper function to print reference tree
 */
function printReference(\Codeviastudio\FeatureTraverser\Result\Reference $reference, int $indentLevel): void
{
    $indent = str_repeat('  ', $indentLevel);
    $icon = match ($reference->getType()->value) {
        'method_call' => '→',
        'static_call' => '⇒',
        'function_call' => 'ƒ',
        'class_instantiation' => 'new',
        default => '•',
    };

    // Make path relative for better readability
    $relativePath = str_replace(__DIR__ . '/../../', '', $reference->getFilePath());

    echo $indent . $icon . ' ' . $reference->getFullyQualifiedName() . "\n";
    echo $indent . "  ↳ " . $relativePath . "\n";

    foreach ($reference->getChildren() as $child) {
        printReference($child, $indentLevel + 1);
    }
}
