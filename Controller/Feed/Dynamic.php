<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Controller\Feed;

use Dlabsit\XmlFeed\Api\FeedRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Store\Model\StoreManagerInterface;

class Dynamic implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly RawFactory $rawResultFactory,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly Filesystem $filesystem,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function execute(): ResultInterface
    {
        $result = $this->rawResultFactory->create();
        $slug = (string) $this->request->getParam('feed_slug');
        $storeId = (int) $this->storeManager->getStore()->getId();

        try {
            $feed = $this->feedRepository->getBySlug($slug, $storeId);
        } catch (NoSuchEntityException $e) {
            return $result->setHttpResponseCode(404)->setContents('Feed not found');
        }

        if (!$feed->isActive()) {
            return $result->setHttpResponseCode(404)->setContents('Feed is disabled');
        }

        $mediaDir = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $feedPath = 'xmlfeed/' . $feed->getFilename();
        $gzPath = $feedPath . '.gz';
        $acceptEncoding = (string) $this->request->getHeader('Accept-Encoding');

        if ($mediaDir->isExist($gzPath) && str_contains($acceptEncoding, 'gzip')) {
            $result->setHeader('Content-Type', 'application/xml; charset=UTF-8');
            $result->setHeader('Content-Encoding', 'gzip');
            $result->setHeader('Vary', 'Accept-Encoding');
            $result->setContents($mediaDir->readFile($gzPath));
            return $result;
        }

        if (!$mediaDir->isExist($feedPath)) {
            return $result->setHttpResponseCode(404)->setContents('Feed file not generated yet');
        }

        $result->setHeader('Content-Type', 'application/xml; charset=UTF-8');
        $result->setHeader('Vary', 'Accept-Encoding');
        $result->setContents($mediaDir->readFile($feedPath));
        return $result;
    }
}
