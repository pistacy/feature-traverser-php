<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Config;

final readonly class EntryPoint
{
    public function __construct(
        private string $className,
        private string $methodName,
    ) {
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getFullyQualifiedName(): string
    {
        return $this->className . '::' . $this->methodName;
    }
}
