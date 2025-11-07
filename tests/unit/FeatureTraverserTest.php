<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests;

use Pistacy\FeatureTraverser\Analyzer\AstAnalyzer;
use Pistacy\FeatureTraverser\Config\EntryPoint;
use Pistacy\FeatureTraverser\Config\TraversalConfig;
use Pistacy\FeatureTraverser\FeatureTraverser;
use Pistacy\FeatureTraverser\Tests\Resolver\FakeClassLoader;
use Pistacy\FeatureTraverser\Resolver\ClassResolver;
use PHPUnit\Framework\TestCase;

final class FeatureTraverserTest extends TestCase
{
    public function testTraverseReturnsEmptyCollectionWhenEntryPointNotFound(): void
    {
        $classLoader = new FakeClassLoader([]);
        $classResolver = new ClassResolver($classLoader);
        $astAnalyzer = new AstAnalyzer();
        $traverser = new FeatureTraverser($classResolver, $astAnalyzer);

        $entryPoint = new EntryPoint('NonExistent\Class', 'method');
        $config = new TraversalConfig(entryPoint: $entryPoint);

        $result = $traverser->traverse($config);

        self::assertTrue($result->isEmpty());
    }

    public function testTraverseReturnsEmptyCollectionWhenEntryPointIsExcluded(): void
    {
        $filePath = realpath(__DIR__ . '/../resources/fixtures/SimpleClass.php');
        if ($filePath === false) {
            self::fail('Could not resolve file path');
        }
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
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            excludedPatterns: ['/SimpleClass\.php$/'],
        );

        $result = $traverser->traverse($config);

        self::assertTrue($result->isEmpty());
    }

    public function testTraverseRespectsMaxDepth(): void
    {
        $filePathA = (string) realpath(__DIR__ . '/../resources/fixtures/CircularA.php');
        $filePathB = (string) realpath(__DIR__ . '/../resources/fixtures/CircularB.php');
        $filePathC = (string) realpath(__DIR__ . '/../resources/fixtures/CircularC.php');

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
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            maxDepth: 2,
        );

        $result = $traverser->traverse($config);

        $uniqueFiles = $result->getUniqueFilePaths();
        self::assertLessThanOrEqual(3, count($uniqueFiles));
    }

    public function testTraverseHandlesCircularReferencesWithoutInfiniteLoop(): void
    {
        $filePathA = (string) realpath(__DIR__ . '/../resources/fixtures/CircularA.php');
        $filePathB = (string) realpath(__DIR__ . '/../resources/fixtures/CircularB.php');
        $filePathC = (string) realpath(__DIR__ . '/../resources/fixtures/CircularC.php');

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

        $result = $traverser->traverse($config);

        self::assertFalse($result->isEmpty());

        $uniqueFiles = $result->getUniqueFilePaths();
        self::assertCount(3, $uniqueFiles);
        self::assertContains($filePathA, $uniqueFiles);
        self::assertContains($filePathB, $uniqueFiles);
        self::assertContains($filePathC, $uniqueFiles);
    }

    public function testTraverseBuildsReferenceTree(): void
    {
        $filePathA = (string) realpath(__DIR__ . '/../resources/fixtures/CircularA.php');
        $filePathB = (string) realpath(__DIR__ . '/../resources/fixtures/CircularB.php');
        $filePathC = (string) realpath(__DIR__ . '/../resources/fixtures/CircularC.php');

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

        $result = $traverser->traverse($config);

        self::assertCount(1, $result->all());

        $rootReference = $result->all()[0];
        self::assertTrue($rootReference->hasChildren());
        self::assertCount(1, $rootReference->getChildren());

        $firstChild = $rootReference->getChildren()[0];
        self::assertSame(
            'Pistacy\FeatureTraverser\Tests\Resources\Fixtures\CircularB::staticMethod',
            $firstChild->getFullyQualifiedName()
        );
    }
}
