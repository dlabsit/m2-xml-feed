<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Controller\Adminhtml\Feed;

use Dlabsit\XmlFeed\Api\FeedRepositoryInterface;
use Dlabsit\XmlFeed\Controller\Adminhtml\Feed;
use Dlabsit\XmlFeed\Model\Feed\Generator;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\View\Result\PageFactory;

class Generate extends Feed implements HttpGetActionInterface, HttpPostActionInterface
{
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly Generator $generator,
        private readonly JsonFactory $jsonFactory,
        private readonly FileDriver $fileDriver
    ) {
        parent::__construct($context, $resultPageFactory);
    }

    public function execute(): ResultInterface
    {
        // Large catalogs can take 30–120s to write. Lift the timeout and keep
        // the request alive if the browser disconnects so the XML finishes.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        set_time_limit(600);
        ignore_user_abort(true);

        $id = (int) $this->getRequest()->getParam('feed_id');
        $isAjax = $this->getRequest()->isAjax()
            || str_contains((string) $this->getRequest()->getHeader('Accept'), 'application/json');

        $startedAt = microtime(true);

        try {
            $feed = $this->feedRepository->getById($id);
            $path = $this->generator->generateForFeed($feed);
            $elapsed = round(microtime(true) - $startedAt, 2);
            $size = $this->readSize($path);

            if ($isAjax) {
                return $this->jsonFactory->create()->setData([
                    'success'  => true,
                    'slug'     => $feed->getSlug(),
                    'path'     => $path,
                    'size'     => $this->formatBytes($size),
                    'duration' => $elapsed,
                    'message'  => __(
                        'Feed "%1" generated in %2s — %3',
                        $feed->getSlug(),
                        $elapsed,
                        $this->formatBytes($size)
                    )->render(),
                ]);
            }

            $this->messageManager->addSuccessMessage(
                __('Feed "%1" generated (%2s, %3)', $feed->getSlug(), $elapsed, $this->formatBytes($size))
            );
        } catch (NoSuchEntityException $e) {
            return $this->fail($e, __('This feed no longer exists.'), $isAjax);
        } catch (\Exception $e) {
            return $this->fail($e, __('Feed generation failed.'), $isAjax);
        }

        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }

    private function fail(\Throwable $e, \Magento\Framework\Phrase $fallback, bool $isAjax): ResultInterface
    {
        if ($isAjax) {
            return $this->jsonFactory->create()->setData([
                'success' => false,
                'message' => $fallback->render() . ' — ' . $e->getMessage(),
            ]);
        }
        $this->messageManager->addExceptionMessage($e, $fallback);
        return $this->resultFactory->create(ResultFactory::TYPE_REDIRECT)->setPath('*/*/index');
    }

    private function readSize(string $path): int
    {
        try {
            $stat = $this->fileDriver->stat($path);
            return (int) ($stat['size'] ?? 0);
        } catch (\Exception $e) {
            return 0;
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
        return $bytes . ' B';
    }
}
