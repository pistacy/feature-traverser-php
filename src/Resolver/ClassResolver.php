<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Resolver;

final readonly class ClassResolver
{
    /**
     * @param array<string, string> $psr4Prefixes PSR-4 namespace => directory mappings
     */
    public function __construct(
        private array $psr4Prefixes,
    ) {
    }

    /**
     * Create ClassResolver by reading composer.json from project root
     */
    public static function fromComposerJson(string $projectRoot): self
    {
        $psr4Prefixes = ComposerConfigReader::readPsr4Mappings($projectRoot);
        return new self($psr4Prefixes);
    }

    /**
     * Resolve a class name to its file path using PSR-4 mapping
     */
    public function resolve(string $className): ?string
    {
        // Normalize class name (remove leading backslash)
        $className = ltrim($className, '\\');

        // Try each PSR-4 prefix
        foreach ($this->psr4Prefixes as $prefix => $baseDir) {
            $prefix = trim($prefix, '\\');

            // Check if class matches this prefix
            if (str_starts_with($className, $prefix)) {
                // Remove prefix from class name
                $relativeClass = substr($className, strlen($prefix));
                $relativeClass = ltrim($relativeClass, '\\');

                // Convert namespace to file path
                $filePath = $baseDir . '/' . str_replace('\\', '/', $relativeClass) . '.php';

                // Normalize to absolute path
                $realPath = realpath($filePath);

                if ($realPath !== false && is_file($realPath)) {
                    return $realPath;
                }
            }
        }

        return null;
    }

    /**
     * Check if a class exists in the configured paths
     */
    public function exists(string $className): bool
    {
        return $this->resolve($className) !== null;
    }
}
