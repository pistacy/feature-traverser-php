<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests\Extractor;

use Pistacy\FeatureTraverser\Analyzer\AstAnalyzer;
use Pistacy\FeatureTraverser\Config\EntryPoint;
use Pistacy\FeatureTraverser\Config\TraversalConfig;
use Pistacy\FeatureTraverser\Extractor\CodeExtractor;
use Pistacy\FeatureTraverser\FeatureTraverser;
use Pistacy\FeatureTraverser\Resolver\ClassResolver;
use Pistacy\FeatureTraverser\Tests\Resolver\FakeClassLoader;
use PHPUnit\Framework\TestCase;

final class CodeExtractorTest extends TestCase
{
    public function testExtractReturnsPhpCode(): void
    {
        // Given
        $filePathA = (string) realpath(__DIR__ . '/../../resources/fixtures/CircularA.php');
        $filePathB = (string) realpath(__DIR__ . '/../../resources/fixtures/CircularB.php');
        $filePathC = (string) realpath(__DIR__ . '/../../resources/fixtures/CircularC.php');

        $classLoader = new FakeClassLoader([
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\CircularA' => $filePathA,
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\CircularB' => $filePathB,
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\CircularC' => $filePathC,
        ]);

        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint(
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\CircularA',
            'method'
        );
        $config = new TraversalConfig(entryPoint: $entryPoint);

        // When
        $result = $traverser->traverse($config);
        $extractor = new CodeExtractor();
        $code = $extractor->extract($result);

        // Then
        self::assertStringStartsWith('<?php', $code);
        self::assertStringContainsString('namespace Pistacy\FeatureTraverser\Tests\Resources\Fixtures', $code);
    }

    public function testExtractIncludesOnlyUsedMethods(): void
    {
        // Given
        $filePath = (string) realpath(__DIR__ . '/../../resources/fixtures/SimpleClass.php');

        $classLoader = new FakeClassLoader([
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass' => $filePath,
        ]);

        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint(
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass',
            'myMethod'
        );
        $config = new TraversalConfig(entryPoint: $entryPoint);

        // When
        $result = $traverser->traverse($config);
        $extractor = new CodeExtractor();
        $code = $extractor->extract($result);

        // Then
        self::assertStringContainsString('function myMethod', $code);
        self::assertStringContainsString('class SimpleClass', $code);
    }

    public function testExtractPreservesUseStatements(): void
    {
        // Given
        $filePath = (string) realpath(__DIR__ . '/../../resources/fixtures/SimpleClass.php');

        $classLoader = new FakeClassLoader([
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass' => $filePath,
        ]);

        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint(
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\SimpleClass',
            'myMethod'
        );
        $config = new TraversalConfig(entryPoint: $entryPoint);

        // When
        $result = $traverser->traverse($config);
        $extractor = new CodeExtractor();
        $code = $extractor->extract($result);

        // Then
        self::assertStringContainsString('use Pistacy\FeatureTraverser\Tests\Resources\Fixtures\Dependency\MyService', $code);
        self::assertStringContainsString('use Pistacy\FeatureTraverser\Tests\Resources\Fixtures\Dependency\MyRepository', $code);
    }

    public function testExtractHandlesMultipleFiles(): void
    {
        // Given
        $filePathA = (string) realpath(__DIR__ . '/../../resources/fixtures/CircularA.php');
        $filePathB = (string) realpath(__DIR__ . '/../../resources/fixtures/CircularB.php');

        $classLoader = new FakeClassLoader([
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\CircularA' => $filePathA,
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\CircularB' => $filePathB,
        ]);

        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint(
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\CircularA',
            'method'
        );
        $config = new TraversalConfig(entryPoint: $entryPoint);

        // When
        $result = $traverser->traverse($config);
        $extractor = new CodeExtractor();
        $code = $extractor->extract($result);

        // Then
        self::assertStringContainsString('class CircularA', $code);
        self::assertStringContainsString('class CircularB', $code);
    }

    public function testExtractRemovesFinalAndReadonlyKeywords(): void
    {
        // Given - create a test file with final readonly class and properties
        $testCode = <<<'PHP'
<?php
namespace Test;

final readonly class TestClass
{
    public function __construct(
        private readonly string $id,
        public readonly int $value
    ) {
    }

    private readonly array $data;
}
PHP;

        $tempFile = sys_get_temp_dir() . '/test_readonly_' . uniqid() . '.php';
        file_put_contents($tempFile, $testCode);

        try {
            $classLoader = new FakeClassLoader([
                'Test\TestClass' => $tempFile,
            ]);

            $classResolver = new ClassResolver($classLoader);
            $astAnalyzer = new AstAnalyzer();
            $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

            $entryPoint = new EntryPoint('Test\TestClass', '__construct');
            $config = new TraversalConfig(entryPoint: $entryPoint);

            // When
            $result = $traverser->traverse($config);
            $extractor = new CodeExtractor();
            $code = $extractor->extract($result);

            // Then - verify readonly and final are removed
            self::assertStringContainsString('class TestClass', $code);
            self::assertStringNotContainsString('final class', $code);
            self::assertStringNotContainsString('readonly class', $code);
            self::assertStringNotContainsString('final readonly class', $code);
            self::assertStringNotContainsString('private readonly', $code);
            self::assertStringNotContainsString('public readonly', $code);

            // Verify code is valid PHP
            self::assertStringStartsWith('<?php', $code);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testExtractIncludesParameterTypeDependencies(): void
    {
        // Given - create test files with parameter type dependencies
        $dtoCode = <<<'PHP'
<?php
namespace Test;

class RequestDTO
{
    public string $name;
    public int $age;
}
PHP;

        $controllerCode = <<<'PHP'
<?php
namespace Test;

class Controller
{
    public function handle(RequestDTO $request): void
    {
        // Use DTO properties (not methods)
        $data = $request->name . ' is ' . $request->age;
    }
}
PHP;

        $dtoFile = sys_get_temp_dir() . '/test_dto_' . uniqid() . '.php';
        $controllerFile = sys_get_temp_dir() . '/test_controller_' . uniqid() . '.php';
        file_put_contents($dtoFile, $dtoCode);
        file_put_contents($controllerFile, $controllerCode);

        try {
            $classLoader = new FakeClassLoader([
                'Test\RequestDTO' => $dtoFile,
                'Test\Controller' => $controllerFile,
            ]);

            $classResolver = new ClassResolver($classLoader);
            $astAnalyzer = new AstAnalyzer();
            $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

            $entryPoint = new EntryPoint('Test\Controller', 'handle');
            $config = new TraversalConfig(entryPoint: $entryPoint);

            // When
            $result = $traverser->traverse($config);
            $extractor = new CodeExtractor();
            $code = $extractor->extract($result);

            // Then - verify parameter type DTO is included even though no methods are called
            self::assertStringContainsString('class Controller', $code);
            self::assertStringContainsString('class RequestDTO', $code);
            self::assertStringContainsString('function handle', $code);

            // Verify the DTO properties are included
            self::assertStringContainsString('$name', $code);
            self::assertStringContainsString('$age', $code);

            // Verify code is valid PHP
            self::assertStringStartsWith('<?php', $code);
        } finally {
            if (file_exists($dtoFile)) {
                unlink($dtoFile);
            }
            if (file_exists($controllerFile)) {
                unlink($controllerFile);
            }
        }
    }
}
