<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Result;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, Reference>
 */
final class ReferenceCollection implements IteratorAggregate, Countable
{
    /**
     * @param array<Reference> $references
     */
    public function __construct(
        private array $references = [],
    ) {
    }

    public function add(Reference $reference): void
    {
        $this->references[] = $reference;
    }

    /**
     * @return array<Reference>
     */
    public function all(): array
    {
        return $this->references;
    }

    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->references);
    }

    public function count(): int
    {
        return count($this->references);
    }

    /**
     * Get all unique file paths from references
     *
     * @return array<string>
     */
    public function getUniqueFilePaths(): array
    {
        $filePaths = [];

        foreach ($this->references as $reference) {
            $filePaths[] = $reference->getFilePath();
            $filePaths = array_merge($filePaths, $this->collectFilePathsRecursively($reference));
        }

        return array_unique($filePaths);
    }

    /**
     * @return array<string>
     */
    private function collectFilePathsRecursively(Reference $reference): array
    {
        $filePaths = [];

        foreach ($reference->getChildren() as $child) {
            $filePaths[] = $child->getFilePath();
            $filePaths = array_merge($filePaths, $this->collectFilePathsRecursively($child));
        }

        return $filePaths;
    }

    /**
     * @return array<array<string, mixed>>
     */
    public function toArray(): array
    {
        return array_map(
            static fn (Reference $reference): array => $reference->toArray(),
            $this->references
        );
    }

    public function isEmpty(): bool
    {
        return count($this->references) === 0;
    }

    /**
     * Get methods used per file
     *
     * @return array<string, array<string>>
     */
    public function getMethodsUsedPerFile(): array
    {
        $methodsPerFile = [];

        foreach ($this->references as $reference) {
            $this->collectMethodsPerFile($reference, $methodsPerFile);
        }

        return array_map(
            static fn (array $methods): array => array_unique($methods),
            $methodsPerFile
        );
    }

    /**
     * @param array<string, array<string>> $methodsPerFile
     */
    private function collectMethodsPerFile(Reference $reference, array &$methodsPerFile): void
    {
        $filePath = $reference->getFilePath();
        $methodName = $reference->getMethodName();

        // Ensure file is in the map even if no methods (e.g., parameter types)
        if (!isset($methodsPerFile[$filePath])) {
            $methodsPerFile[$filePath] = [];
        }

        // Add method if not null
        if ($methodName !== null) {
            $methodsPerFile[$filePath][] = $methodName;
        }

        foreach ($reference->getChildren() as $child) {
            $this->collectMethodsPerFile($child, $methodsPerFile);
        }
    }
}
