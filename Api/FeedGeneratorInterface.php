<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Api;

interface FeedGeneratorInterface
{
    /**
     * Generate a single feed for a given store.
     *
     * @return string Absolute path to the generated file
     */
    public function generate(string $feedCode, int $storeId): string;

    /**
     * Generate all enabled feeds for a given store.
     *
     * @return string[] Map feedCode => file path
     */
    public function generateAll(int $storeId): array;
}
