<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Resolver;

use Composer\Autoload\ClassLoader;

final readonly class ClassResolver
{
    public function __construct(
        private ClassLoader $classLoader,
    ) {
    }

    /**
     * Resolve a class name to its file path
     */
    public function resolve(string $className): ?string
    {
        $file = $this->classLoader->findFile($className);

        if ($file === false) {
            return null;
        }

        // Normalize to absolute path (resolve ../ and ./)
        $realPath = realpath($file);

        return $realPath !== false ? $realPath : $file;
    }

    /**
     * Check if a class exists in the autoloader
     */
    public function exists(string $className): bool
    {
        return $this->resolve($className) !== null;
    }
}
