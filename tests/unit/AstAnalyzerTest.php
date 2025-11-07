<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Analyzer;

use Codeviastudio\FeatureTraverser\Analyzer\AstAnalyzer;
use PHPUnit\Framework\TestCase;

final class AstAnalyzerTest extends TestCase
{
    private AstAnalyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = new AstAnalyzer();
    }

    public function testAnalyzeFileReturnsEmptyArrayForNonExistentFile(): void
    {
        $result = $this->analyzer->analyzeFile('/non/existent/file.php');

        self::assertSame([], $result);
    }

    public function testAnalyzeFileReturnsCallsFromFile(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/SimpleClass.php';

        $result = $this->analyzer->analyzeFile($filePath);

        self::assertNotEmpty($result);
    }

    public function testAnalyzeMethodReturnsEmptyArrayForNonExistentFile(): void
    {
        $result = $this->analyzer->analyzeMethod(
            '/non/existent/file.php',
            'MyClass',
            'myMethod'
        );

        self::assertSame([], $result);
    }

    public function testAnalyzeMethodReturnsCallsFromSpecificMethod(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/SimpleClass.php';

        $result = $this->analyzer->analyzeMethod(
            $filePath,
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass',
            'myMethod'
        );

        self::assertNotEmpty($result);

        $callTypes = array_column($result, 'type');
        self::assertContains('invokable_call', $callTypes);
        self::assertContains('method_call', $callTypes);
        self::assertContains('static_call', $callTypes);
        self::assertContains('class_instantiation', $callTypes);
        self::assertContains('function_call', $callTypes);
    }

    public function testAnalyzeMethodReturnsInvokableCallWithResolvedType(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/SimpleClass.php';

        $result = $this->analyzer->analyzeMethod(
            $filePath,
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass',
            'myMethod'
        );

        $invokableCalls = array_filter($result, fn ($call) => $call['type'] === 'invokable_call');
        self::assertNotEmpty($invokableCalls);

        $invokableCall = array_values($invokableCalls)[0];
        self::assertSame('Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\Dependency\MyService', $invokableCall['class']);
        self::assertSame('__invoke', $invokableCall['method']);
    }

    public function testAnalyzeMethodReturnsMethodCallWithResolvedType(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/SimpleClass.php';

        $result = $this->analyzer->analyzeMethod(
            $filePath,
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass',
            'myMethod'
        );

        $methodCalls = array_filter($result, fn ($call) => $call['type'] === 'method_call');
        self::assertNotEmpty($methodCalls);
    }

    public function testAnalyzeMethodReturnsStaticCall(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/SimpleClass.php';

        $result = $this->analyzer->analyzeMethod(
            $filePath,
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass',
            'myMethod'
        );

        $staticCalls = array_filter($result, fn ($call) => $call['type'] === 'static_call');
        self::assertNotEmpty($staticCalls);

        $staticCall = array_values($staticCalls)[0];
        self::assertSame('staticMethod', $staticCall['method']);
    }

    public function testAnalyzeMethodReturnsClassInstantiation(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/SimpleClass.php';

        $result = $this->analyzer->analyzeMethod(
            $filePath,
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass',
            'myMethod'
        );

        $instantiations = array_filter($result, fn ($call) => $call['type'] === 'class_instantiation');
        self::assertNotEmpty($instantiations);

        $instantiation = array_values($instantiations)[0];
        self::assertSame('__construct', $instantiation['method']);
    }

    public function testAnalyzeMethodReturnsFunctionCall(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/SimpleClass.php';

        $result = $this->analyzer->analyzeMethod(
            $filePath,
            'Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass',
            'myMethod'
        );

        $functionCalls = array_filter($result, fn ($call) => $call['type'] === 'function_call');
        self::assertNotEmpty($functionCalls);

        $functionCall = array_values($functionCalls)[0];
        self::assertSame('someFunction', $functionCall['method']);
    }
}
