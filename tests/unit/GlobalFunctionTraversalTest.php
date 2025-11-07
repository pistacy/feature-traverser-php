<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests;

use Codeviastudio\FeatureTraverser\Analyzer\AstAnalyzer;
use Codeviastudio\FeatureTraverser\Config\EntryPoint;
use Codeviastudio\FeatureTraverser\Config\TraversalConfig;
use Codeviastudio\FeatureTraverser\FeatureTraverser;
use Codeviastudio\FeatureTraverser\Resolver\ClassResolver;
use Codeviastudio\FeatureTraverser\Tests\Resolver\FakeClassLoader;
use PHPUnit\Framework\TestCase;

final class GlobalFunctionTraversalTest extends TestCase
{
    public function testAnalyzerDetectsFunctionCalls(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/ClassUsingGlobalFunctions.php';
        $analyzer = new AstAnalyzer();

        $result = $analyzer->analyzeMethod(
            $filePath,
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions',
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
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions',
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
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions' => $classFilePath,
        ]);

        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint(
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions',
            'methodUsingFunctions'
        );
        $config = new TraversalConfig(entryPoint: $entryPoint);

        $result = $traverser->traverse($config);

        self::assertFalse($result->isEmpty());
        self::assertCount(1, $result->all());

        $rootReference = $result->all()[0];
        self::assertSame(
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions::methodUsingFunctions',
            $rootReference->getFullyQualifiedName()
        );
    }

    public function testMethodsUsedPerFileIncludesOnlyClassMethods(): void
    {
        $classFilePath = (string) realpath(__DIR__ . '/../resources/fixtures/ClassUsingGlobalFunctions.php');

        $classLoader = new FakeClassLoader([
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions' => $classFilePath,
        ]);

        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint(
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\ClassUsingGlobalFunctions',
            'methodUsingFunctions'
        );
        $config = new TraversalConfig(entryPoint: $entryPoint);

        $result = $traverser->traverse($config);
        $methodsPerFile = $result->getMethodsUsedPerFile();

        self::assertArrayHasKey($classFilePath, $methodsPerFile);
        self::assertContains('methodUsingFunctions', $methodsPerFile[$classFilePath]);
    }
}
