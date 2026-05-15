<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Api;

interface FeedWriterInterface
{
    /**
     * Return the machine-readable feed code (e.g. "skroutz", "google", "facebook").
     */
    public function getCode(): string;

    /**
     * Return the human-readable feed label.
     */
    public function getLabel(): string;

    /**
     * Return the default filename (e.g. "skroutz.xml", "google.xml").
     */
    public function getDefaultFilename(): string;

    /**
     * Write the feed to the provided absolute file path, reading products
     * from the generator. The generator yields Magento Product objects.
     */
    public function write(string $filePath, \Generator $productSource, int $storeId): void;
}
