<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Config;

use Codeviastudio\FeatureTraverser\Config\EntryPoint;
use PHPUnit\Framework\TestCase;

final class EntryPointTest extends TestCase
{
    public function testGetClassName(): void
    {
        // Given
        $entryPoint = new EntryPoint(
            className: 'MyNamespace\MyClass',
            methodName: 'myMethod',
        );

        // When
        $className = $entryPoint->getClassName();

        // Then
        self::assertSame('MyNamespace\MyClass', $className);
    }

    public function testGetMethodName(): void
    {
        // Given
        $entryPoint = new EntryPoint(
            className: 'MyNamespace\MyClass',
            methodName: 'myMethod',
        );

        // When
        $methodName = $entryPoint->getMethodName();

        // Then
        self::assertSame('myMethod', $methodName);
    }

    public function testGetFullyQualifiedName(): void
    {
        // Given
        $entryPoint = new EntryPoint(
            className: 'MyNamespace\MyClass',
            methodName: 'myMethod',
        );

        // When
        $fqn = $entryPoint->getFullyQualifiedName();

        // Then
        self::assertSame('MyNamespace\MyClass::myMethod', $fqn);
    }
}
