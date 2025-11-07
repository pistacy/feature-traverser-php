<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Resolver;

use Codeviastudio\FeatureTraverser\Resolver\ClassResolver;
use PHPUnit\Framework\TestCase;

final class ClassResolverTest extends TestCase
{
    public function testResolveReturnsFilePathWhenClassExists(): void
    {
        $classLoader = new FakeClassLoader([
            'MyNamespace\MyClass' => '/path/to/MyClass.php',
        ]);
        $resolver = new ClassResolver($classLoader);

        $result = $resolver->resolve('MyNamespace\MyClass');

        self::assertSame('/path/to/MyClass.php', $result);
    }

    public function testResolveReturnsNullWhenClassDoesNotExist(): void
    {
        $classLoader = new FakeClassLoader([]);
        $resolver = new ClassResolver($classLoader);

        $result = $resolver->resolve('NonExistent\MyClass');

        self::assertNull($result);
    }

    public function testExistsReturnsTrueWhenClassExists(): void
    {
        $classLoader = new FakeClassLoader([
            'MyNamespace\MyClass' => '/path/to/MyClass.php',
        ]);
        $resolver = new ClassResolver($classLoader);

        $result = $resolver->exists('MyNamespace\MyClass');

        self::assertTrue($result);
    }

    public function testExistsReturnsFalseWhenClassDoesNotExist(): void
    {
        $classLoader = new FakeClassLoader([]);
        $resolver = new ClassResolver($classLoader);

        $result = $resolver->exists('NonExistent\MyClass');

        self::assertFalse($result);
    }
}
