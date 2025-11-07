<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Resolver;

use Composer\Autoload\ClassLoader;

final class FakeClassLoader extends ClassLoader
{
    /**
     * @param array<string, string> $classMap
     */
    public function __construct(
        private array $classMap = [],
    ) {
        parent::__construct();
    }

    public function findFile($class): string|false
    {
        return $this->classMap[$class] ?? false;
    }
}
