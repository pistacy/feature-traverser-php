<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Config;

final readonly class TraversalConfig
{
    /**
     * @param EntryPoint $entryPoint Entry point for traversal
     * @param array<string> $excludedPaths Paths to exclude from analysis (relative to project root)
     * @param array<string> $excludedPatterns Regex patterns to exclude files (e.g., '/UserInterface\/Web\/Dto/')
     * @param string $projectRoot Project root directory
     * @param int $maxDepth Maximum traversal depth (0 = unlimited)
     */
    public function __construct(
        private EntryPoint $entryPoint,
        private array $excludedPaths = [],
        private array $excludedPatterns = [],
        private string $projectRoot = '',
        private int $maxDepth = 0,
    ) {
    }

    public function getEntryPoint(): EntryPoint
    {
        return $this->entryPoint;
    }

    /**
     * @return array<string>
     */
    public function getExcludedPaths(): array
    {
        return $this->excludedPaths;
    }

    /**
     * @return array<string>
     */
    public function getExcludedPatterns(): array
    {
        return $this->excludedPatterns;
    }

    public function getProjectRoot(): string
    {
        return $this->projectRoot;
    }

    public function getMaxDepth(): int
    {
        return $this->maxDepth;
    }

    public function isPathExcluded(string $filePath): bool
    {
        // Normalize path to resolve ../.. patterns
        $normalizedPath = realpath($filePath);
        if ($normalizedPath === false) {
            // If realpath fails, use the original path
            $normalizedPath = $filePath;
        }

        // Always exclude vendor directory (check after normalization)
        if (str_contains($normalizedPath, '/vendor/')) {
            return true;
        }

        // Check configured path exclusions
        foreach ($this->excludedPaths as $excludedPath) {
            $normalizedExcludedPath = $this->projectRoot . '/' . ltrim($excludedPath, '/');

            if (str_starts_with($normalizedPath, $normalizedExcludedPath)) {
                return true;
            }
        }

        // Check regex pattern exclusions
        foreach ($this->excludedPatterns as $pattern) {
            if (@preg_match($pattern, $normalizedPath) === 1) {
                return true;
            }
        }

        return false;
    }
}
