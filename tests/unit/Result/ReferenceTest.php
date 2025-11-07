<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Result;

use Codeviastudio\FeatureTraverser\Result\Reference;
use Codeviastudio\FeatureTraverser\Result\ReferenceType;
use PHPUnit\Framework\TestCase;

final class ReferenceTest extends TestCase
{
    public function testGetFullyQualifiedName(): void
    {
        // Given
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
        );

        // When
        $result = $reference->getFullyQualifiedName();

        // Then
        self::assertSame('MyClass::myMethod', $result);
    }

    public function testGetType(): void
    {
        // Given
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::STATIC_CALL,
            filePath: '/path/to/file.php',
        );

        // When
        $result = $reference->getType();

        // Then
        self::assertSame(ReferenceType::STATIC_CALL, $result);
    }

    public function testGetFilePath(): void
    {
        // Given
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
        );

        // When
        $result = $reference->getFilePath();

        // Then
        self::assertSame('/path/to/file.php', $result);
    }

    public function testGetDepth(): void
    {
        // Given
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
            depth: 3,
        );

        // When
        $result = $reference->getDepth();

        // Then
        self::assertSame(3, $result);
    }

    public function testGetParent(): void
    {
        // Given
        $parent = new Reference(
            fullyQualifiedName: 'ParentClass::parentMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/parent.php',
        );

        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
            parent: $parent,
        );

        // When
        $result = $reference->getParent();

        // Then
        self::assertSame($parent, $result);
    }

    public function testAddChildAndGetChildren(): void
    {
        // Given
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
        );

        $child = new Reference(
            fullyQualifiedName: 'ChildClass::childMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/child.php',
        );

        // When
        $reference->addChild($child);
        $children = $reference->getChildren();

        // Then
        self::assertCount(1, $children);
        self::assertSame($child, $children[0]);
    }

    public function testHasChildrenReturnsTrueWhenChildrenExist(): void
    {
        // Given
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
        );

        $child = new Reference(
            fullyQualifiedName: 'ChildClass::childMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/child.php',
        );

        $reference->addChild($child);

        // When
        $result = $reference->hasChildren();

        // Then
        self::assertTrue($result);
    }

    public function testHasChildrenReturnsFalseWhenNoChildren(): void
    {
        // Given
        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
        );

        // When
        $result = $reference->hasChildren();

        // Then
        self::assertFalse($result);
    }

    public function testToArray(): void
    {
        // Given
        $parent = new Reference(
            fullyQualifiedName: 'ParentClass::parentMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/parent.php',
            depth: 0,
        );

        $child = new Reference(
            fullyQualifiedName: 'ChildClass::childMethod',
            type: ReferenceType::STATIC_CALL,
            filePath: '/path/to/child.php',
            depth: 2,
            parent: $parent,
        );

        $reference = new Reference(
            fullyQualifiedName: 'MyClass::myMethod',
            type: ReferenceType::METHOD_CALL,
            filePath: '/path/to/file.php',
            depth: 1,
            parent: $parent,
        );

        $reference->addChild($child);

        // When
        $result = $reference->toArray();

        // Then
        self::assertSame('MyClass::myMethod', $result['fullyQualifiedName']);
        self::assertSame('method_call', $result['type']);
        self::assertSame('/path/to/file.php', $result['filePath']);
        self::assertSame(1, $result['depth']);
        self::assertCount(1, $result['children']);
        self::assertSame('ChildClass::childMethod', $result['children'][0]['fullyQualifiedName']);
    }
}
