<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Config;

use Codeviastudio\FeatureTraverser\Config\EntryPoint;
use Codeviastudio\FeatureTraverser\Config\TraversalConfig;
use PHPUnit\Framework\TestCase;

final class TraversalConfigTest extends TestCase
{
    public function testGetEntryPoint(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $config = new TraversalConfig($entryPoint);

        // When
        $result = $config->getEntryPoint();

        // Then
        self::assertSame($entryPoint, $result);
    }

    public function testGetExcludedPaths(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $excludedPaths = ['tests/', 'var/'];
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            excludedPaths: $excludedPaths,
        );

        // When
        $result = $config->getExcludedPaths();

        // Then
        self::assertSame($excludedPaths, $result);
    }

    public function testGetExcludedPatterns(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $excludedPatterns = ['/Test\.php$/', '/Dto/'];
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            excludedPatterns: $excludedPatterns,
        );

        // When
        $result = $config->getExcludedPatterns();

        // Then
        self::assertSame($excludedPatterns, $result);
    }

    public function testGetProjectRoot(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $projectRoot = '/path/to/project';
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            projectRoot: $projectRoot,
        );

        // When
        $result = $config->getProjectRoot();

        // Then
        self::assertSame($projectRoot, $result);
    }

    public function testGetMaxDepth(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $maxDepth = 10;
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            maxDepth: $maxDepth,
        );

        // When
        $result = $config->getMaxDepth();

        // Then
        self::assertSame($maxDepth, $result);
    }

    public function testIsPathExcludedReturnsTrueForVendorDirectory(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $config = new TraversalConfig(entryPoint: $entryPoint);

        // When
        $result = $config->isPathExcluded('/project/vendor/package/File.php');

        // Then
        self::assertTrue($result);
    }

    public function testIsPathExcludedReturnsTrueForExcludedPath(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $projectRoot = __DIR__;
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            excludedPaths: ['tests/'],
            projectRoot: $projectRoot,
        );

        // When
        $result = $config->isPathExcluded($projectRoot . '/tests/SomeTest.php');

        // Then
        self::assertTrue($result);
    }

    public function testIsPathExcludedReturnsTrueForMatchingPattern(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            excludedPatterns: ['/Test\.php$/'],
        );

        // When
        $result = $config->isPathExcluded('/project/src/MyTest.php');

        // Then
        self::assertTrue($result);
    }

    public function testIsPathExcludedReturnsFalseForNonExcludedPath(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $projectRoot = __DIR__;
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            excludedPaths: ['tests/'],
            projectRoot: $projectRoot,
        );

        // When
        $result = $config->isPathExcluded($projectRoot . '/src/MyClass.php');

        // Then
        self::assertFalse($result);
    }

    public function testIsPathExcludedReturnsFalseForNonMatchingPattern(): void
    {
        // Given
        $entryPoint = new EntryPoint('MyClass', 'myMethod');
        $config = new TraversalConfig(
            entryPoint: $entryPoint,
            excludedPatterns: ['/Test\.php$/'],
        );

        // When
        $result = $config->isPathExcluded('/project/src/MyClass.php');

        // Then
        self::assertFalse($result);
    }
}
