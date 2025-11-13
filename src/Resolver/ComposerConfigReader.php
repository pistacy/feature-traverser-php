<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Resolver;

final class ComposerConfigReader
{
    /**
     * Read PSR-4 mappings from composer.json
     *
     * @param string $projectRoot Path to the project root (where composer.json is located)
     * @return array<string, string> PSR-4 namespace => directory mappings
     */
    public static function readPsr4Mappings(string $projectRoot): array
    {
        $composerJsonPath = rtrim($projectRoot, '/') . '/composer.json';

        if (!file_exists($composerJsonPath)) {
            throw new \RuntimeException("composer.json not found at: {$composerJsonPath}");
        }

        $composerData = json_decode(file_get_contents($composerJsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse composer.json: " . json_last_error_msg());
        }

        $psr4Mappings = [];

        // Read autoload section
        if (isset($composerData['autoload']['psr-4']) && is_array($composerData['autoload']['psr-4'])) {
            foreach ($composerData['autoload']['psr-4'] as $namespace => $path) {
                $psr4Mappings[$namespace] = self::normalizePath($projectRoot, $path);
            }
        }

        // Read autoload-dev section (for tests, etc.)
        if (isset($composerData['autoload-dev']['psr-4']) && is_array($composerData['autoload-dev']['psr-4'])) {
            foreach ($composerData['autoload-dev']['psr-4'] as $namespace => $path) {
                $psr4Mappings[$namespace] = self::normalizePath($projectRoot, $path);
            }
        }

        return $psr4Mappings;
    }

    /**
     * Normalize path relative to project root
     *
     * @param string $projectRoot
     * @param string|array<string> $path
     * @return string
     */
    private static function normalizePath(string $projectRoot, string|array $path): string
    {
        // Handle array of paths (take first one)
        if (is_array($path)) {
            $path = $path[0] ?? '';
        }

        $projectRoot = rtrim($projectRoot, '/');
        $path = trim($path, '/');

        if ($path === '') {
            return $projectRoot;
        }

        return $projectRoot . '/' . $path;
    }
}
