<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests;

use Pistacy\FeatureTraverser\Analyzer\AstAnalyzer;
use Pistacy\FeatureTraverser\Config\EntryPoint;
use Pistacy\FeatureTraverser\Config\TraversalConfig;
use Pistacy\FeatureTraverser\FeatureTraverser;
use Pistacy\FeatureTraverser\Resolver\ClassResolver;
use Pistacy\FeatureTraverser\Tests\Resolver\FakeClassLoader;
use PHPUnit\Framework\TestCase;

final class GlobalFunctionTraversalTest extends TestCase
{
    public function testAnalyzerDetectsFunctionCalls(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/ClassUsingGlobalFunctions.php';
        $analyzer = new AstAnalyzer();

        $result = $analyzer->analyzeMethod(
            $filePath,
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions',
            'methodUsingFunctions'
        );

        $functionCalls = array_filter($result, fn($call) => $call['type'] === 'function_call');
        self::assertCount(2, $functionCalls);

        $functionNames = array_column($functionCalls, 'method');
        self::assertContains('myGlobalFunction', $functionNames);
        self::assertContains('anotherGlobalFunction', $functionNames);
    }

    public function testFunctionCallsHaveNullClass(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/ClassUsingGlobalFunctions.php';
        $analyzer = new AstAnalyzer();

        $result = $analyzer->analyzeMethod(
            $filePath,
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions',
            'methodUsingFunctions'
        );

        $functionCalls = array_filter($result, fn($call) => $call['type'] === 'function_call');

        foreach ($functionCalls as $call) {
            self::assertNull($call['class']);
        }
    }

    public function testTraverserHandlesFunctionCallsGracefully(): void
    {
        $classFilePath = (string) realpath(__DIR__ . '/../resources/fixtures/ClassUsingGlobalFunctions.php');

        $classLoader = new FakeClassLoader([
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions' => $classFilePath,
        ]);

        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint(
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions',
            'methodUsingFunctions'
        );
        $config = new TraversalConfig(entryPoint: $entryPoint);

        $result = $traverser->traverse($config);

        self::assertFalse($result->isEmpty());
        self::assertCount(1, $result->all());

        $rootReference = $result->all()[0];
        self::assertSame(
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions::methodUsingFunctions',
            $rootReference->getFullyQualifiedName()
        );
    }

    public function testMethodsUsedPerFileIncludesOnlyClassMethods(): void
    {
        $classFilePath = (string) realpath(__DIR__ . '/../resources/fixtures/ClassUsingGlobalFunctions.php');

        $classLoader = new FakeClassLoader([
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions' => $classFilePath,
        ]);

        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint(
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions',
            'methodUsingFunctions'
        );
        $config = new TraversalConfig(entryPoint: $entryPoint);

        $result = $traverser->traverse($config);
        $methodsPerFile = $result->getMethodsUsedPerFile();

        self::assertArrayHasKey($classFilePath, $methodsPerFile);
        self::assertContains('methodUsingFunctions', $methodsPerFile[$classFilePath]);
    }
}
