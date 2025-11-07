<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests\Result;

use Pistacy\FeatureTraverser\Result\Reference;
use Pistacy\FeatureTraverser\Result\ReferenceCollection;
use Pistacy\FeatureTraverser\Result\ReferenceType;
use PHPUnit\Framework\TestCase;

final class ReferenceCollectionTest extends TestCase
{
    public function testAddAndAll(): void
    {
        // Given
        $collection = new ReferenceCollection();
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
        );

        // When
        $collection->add($reference);
        $result = $collection->all();

        // Then
        self::assertCount(1, $result);
        self::assertSame($reference, $result[0]);
    }

    public function testCount(): void
    {
        // Given
        $collection = new ReferenceCollection();
        $reference1 = new Reference(
            fullyQualifiedName: 'MyClass::method1',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file1.php',
        );
        $reference2 = new Reference(
            fullyQualifiedName: 'MyClass::method2',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file2.php',
        );

        $collection->add($reference1);
        $collection->add($reference2);

        // When
        $result = $collection->count();

        // Then
        self::assertSame(2, $result);
    }

    public function testIsEmptyReturnsTrueWhenEmpty(): void
    {
        // Given
        $collection = new ReferenceCollection();

        // When
        $result = $collection->isEmpty();

        // Then
        self::assertTrue($result);
    }

    public function testIsEmptyReturnsFalseWhenNotEmpty(): void
    {
        // Given
        $collection = new ReferenceCollection();
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
        );

        $collection->add($reference);

        // When
        $result = $collection->isEmpty();

        // Then
        self::assertFalse($result);
    }

    public function testGetUniqueFilePaths(): void
    {
        // Given
        $collection = new ReferenceCollection();

        $child1 = new Reference(
            fullyQualifiedName: 'Child1::method',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/child1.php',
        );

        $child2 = new Reference(
            fullyQualifiedName: 'Child2::method',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/child2.php',
        );

        $reference = new Reference(
            fullyQualifiedName: 'Parent::method',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/parent.php',
        );

        $reference->addChild($child1);
        $reference->addChild($child2);
        $collection->add($reference);

        // When
        $result = $collection->getUniqueFilePaths();

        // Then
        self::assertCount(3, $result);
        self::assertContains('/path/to/parent.php', $result);
        self::assertContains('/path/to/child1.php', $result);
        self::assertContains('/path/to/child2.php', $result);
    }

    public function testGetUniqueFilePathsRemovesDuplicates(): void
    {
        // Given
        $collection = new ReferenceCollection();

        $child = new Reference(
            fullyQualifiedName: 'Child::method',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/same.php',
        );

        $reference1 = new Reference(
            fullyQualifiedName: 'Parent1::method',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/same.php',
        );
        $reference1->addChild($child);

        $reference2 = new Reference(
            fullyQualifiedName: 'Parent2::method',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/same.php',
        );

        $collection->add($reference1);
        $collection->add($reference2);

        // When
        $result = $collection->getUniqueFilePaths();

        // Then
        self::assertCount(1, $result);
        self::assertContains('/path/to/same.php', $result);
    }

    public function testToArray(): void
    {
        // Given
        $collection = new ReferenceCollection();
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
        );

        $collection->add($reference);

        // When
        $result = $collection->toArray();

        // Then
        self::assertCount(1, $result);
        self::assertSame('MyClass::myMethod', $result[0]['fullyQualifiedName']);
    }

    public function testIterator(): void
    {
        // Given
        $collection = new ReferenceCollection();
        $reference1 = new Reference(
            fullyQualifiedName: 'MyClass::method1',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file1.php',
        );
        $reference2 = new Reference(
            fullyQualifiedName: 'MyClass::method2',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file2.php',
        );

        $collection->add($reference1);
        $collection->add($reference2);

        // When
        $count = 0;
        foreach ($collection as $reference) {
            $count++;
        }

        // Then
        self::assertSame(2, $count);
    }
}
