<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed;

use Dlabsit\XmlFeed\Api\Data\FeedInterface;
use Dlabsit\XmlFeed\Api\FeedGeneratorInterface;
use Dlabsit\XmlFeed\Helper\Config;
use Dlabsit\XmlFeed\Logger\Logger;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Store\Model\StoreManagerInterface;

class Generator implements FeedGeneratorInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly WriterPool $writerPool,
        private readonly ProductCollector $productCollector,
        private readonly AttributeMapper $attributeMapper,
        private readonly Filesystem $filesystem,
        private readonly FileDriver $fileDriver,
        private readonly StoreManagerInterface $storeManager,
        private readonly Logger $logger
    ) {
    }

    public function generate(string $feedCode, int $storeId): string
    {
        if (!$this->writerPool->has($feedCode)) {
            throw new \InvalidArgumentException("Unknown feed code: {$feedCode}");
        }

        $startTime = microtime(true);
        $this->logger->info("Generating feed '{$feedCode}' for store {$storeId}");

        $this->storeManager->setCurrentStore($storeId);
        $writer = $this->writerPool->get($feedCode);

        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $mediaDir->create('xmlfeed');

        $filename = $this->config->getFeedFilename($feedCode, $storeId) ?: $writer->getDefaultFilename();
        $filePath = $mediaDir->getAbsolutePath('xmlfeed/' . $filename);
        $tempPath = $filePath . '.tmp';

        $writer->write($tempPath, $this->productCollector->collect($storeId), $storeId);

        if ($this->fileDriver->isExists($tempPath)) {
            $this->fileDriver->rename($tempPath, $filePath);
        }

        $finalPath = $filePath;
        if ($this->config->isGzipEnabled($storeId) && $this->statSize($filePath) > 10 * 1024 * 1024) {
            $gzPath = $filePath . '.gz';
            $this->compressGzip($filePath, $gzPath);
            $finalPath = $gzPath;
        }

        $this->attributeMapper->clearCache();

        $elapsed = round(microtime(true) - $startTime, 2);
        $size = $this->formatBytes($this->statSize($finalPath));
        $this->logger->info("Feed '{$feedCode}' generated: {$size} in {$elapsed}s → {$finalPath}");

        return $finalPath;
    }

    public function generateAll(int $storeId): array
    {
        $results = [];
        foreach ($this->writerPool->getCodes() as $code) {
            if (!$this->config->isFeedEnabled($code, $storeId)) {
                continue;
            }
            try {
                $results[$code] = $this->generate($code, $storeId);
            } catch (\Exception $e) {
                $this->logger->error("Feed '{$code}' failed", ['exception' => $e->getMessage()]);
                $results[$code] = null;
            }
        }
        return $results;
    }

    /**
     * Generate a feed using a Feed registry entity. This is the new path —
     * the feed carries its own slug, filename, filters and channel settings,
     * bypassing the legacy shared/per-channel system.xml.
     */
    public function generateForFeed(FeedInterface $feed): string
    {
        $channel = $feed->getChannelCode();
        if (!$this->writerPool->has($channel)) {
            throw new \InvalidArgumentException("Unknown channel code on feed: {$channel}");
        }

        $defaultStore = $this->storeManager->getDefaultStoreView();
        $storeId = $feed->getStoreId() ?: (int) ($defaultStore ? $defaultStore->getId() : 0);
        $startTime = microtime(true);
        $this->logger->info("Generating feed '{$feed->getSlug()}' ({$channel}) for store {$storeId}");

        $this->storeManager->setCurrentStore($storeId);
        $writer = $this->writerPool->get($channel);

        $mediaDir = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $mediaDir->create('xmlfeed');

        $filename = $feed->getFilename() ?: ($feed->getSlug() . '.xml');
        $filePath = $mediaDir->getAbsolutePath('xmlfeed/' . $filename);
        $tempPath = $filePath . '.tmp';

        $writer->write($tempPath, $this->productCollector->collectForFeed($feed, $storeId), $storeId);

        if ($this->fileDriver->isExists($tempPath)) {
            $this->fileDriver->rename($tempPath, $filePath);
        }

        $finalPath = $filePath;
        if ($this->config->isGzipEnabled($storeId) && $this->statSize($filePath) > 10 * 1024 * 1024) {
            $gzPath = $filePath . '.gz';
            $this->compressGzip($filePath, $gzPath);
            $finalPath = $gzPath;
        }

        $this->attributeMapper->clearCache();

        $elapsed = round(microtime(true) - $startTime, 2);
        $size = $this->formatBytes($this->statSize($finalPath));
        $this->logger->info("Feed '{$feed->getSlug()}' generated: {$size} in {$elapsed}s → {$finalPath}");

        return $finalPath;
    }

    private function statSize(string $path): int
    {
        try {
            $stat = $this->fileDriver->stat($path);
            return (int) ($stat['size'] ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Stream a plain file through gzip. We deliberately use the native gz*
     * functions here — they are the only way to write incrementally to a
     * gzip stream without loading the whole file into memory. For large
     * feeds (18 MB+) reading into memory and gzencode() would spike RAM.
     * All I/O except the gzip writing itself goes through the M2 FileDriver.
     */
    private function compressGzip(string $source, string $destination): void
    {
        $readHandle = $this->fileDriver->fileOpen($source, 'rb');
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $gzHandle = gzopen($destination, 'wb9');

        if ($gzHandle === false) {
            $this->fileDriver->fileClose($readHandle);
            throw new \RuntimeException('Failed to open destination for gzip compression');
        }

        try {
            while (!$this->fileDriver->endOfFile($readHandle)) {
                $chunk = $this->fileDriver->fileRead($readHandle, 8192);
                if ($chunk !== false && $chunk !== '') {
                    // phpcs:ignore Magento2.Functions.DiscouragedFunction
                    gzwrite($gzHandle, $chunk);
                }
            }
        } finally {
            $this->fileDriver->fileClose($readHandle);
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            gzclose($gzHandle);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
