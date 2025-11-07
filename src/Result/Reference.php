<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Result;

final class Reference
{
    /**
     * @param string $fullyQualifiedName Fully qualified name (e.g., "MyClass::myMethod" or "myFunction")
     * @param ReferenceType $type Type of reference
     * @param string $filePath File path where the reference is defined
     * @param int $depth Depth level in the traversal tree
     * @param Reference|null $parent Parent reference
     * @param array<Reference> $children Child references
     * @param string|null $className Class name (e.g., "MyClass")
     * @param string|null $methodName Method/function name (e.g., "myMethod")
     */
    public function __construct(
        private readonly string $fullyQualifiedName,
        private readonly ReferenceType $type,
        private readonly string $filePath,
        private readonly int $depth = 0,
        private readonly ?Reference $parent = null,
        private array $children = [],
        private readonly ?string $className = null,
        private readonly ?string $methodName = null,
    ) {
    }

    public function getFullyQualifiedName(): string
    {
        return $this->fullyQualifiedName;
    }

    public function getType(): ReferenceType
    {
        return $this->type;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getParent(): ?Reference
    {
        return $this->parent;
    }

    /**
     * @return array<Reference>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(Reference $child): void
    {
        $this->children[] = $child;
    }

    public function hasChildren(): bool
    {
        return count($this->children) > 0;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getMethodName(): ?string
    {
        return $this->methodName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'fullyQualifiedName' => $this->fullyQualifiedName,
            'type' => $this->type->value,
            'filePath' => $this->filePath,
            'depth' => $this->depth,
            'className' => $this->className,
            'methodName' => $this->methodName,
            'children' => array_map(
                static fn (Reference $child): array => $child->toArray(),
                $this->children
            ),
        ];
    }
}
