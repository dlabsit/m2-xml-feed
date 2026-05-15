<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   FSL-1.1-MIT
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\App;

use Dlabsit\XmlFeed\Api\FeedRepositoryInterface;
use Dlabsit\XmlFeed\Controller\Feed\Dynamic;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Router\NoRouteHandler;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Custom router: maps /feed/<slug> to a single dynamic controller regardless
 * of slug. Falls through to Magento's regular routing when the path isn't
 * under /feed/ or the slug doesn't resolve to a feed entity.
 */
class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        $path = trim((string) $request->getPathInfo(), '/');
        if ($path === 'feed' || !str_starts_with($path, 'feed/')) {
            return null;
        }

        $slug = substr($path, 5);
        // Only a single segment — deeper paths fall through.
        if ($slug === '' || str_contains($slug, '/')) {
            return null;
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        try {
            $this->feedRepository->getBySlug($slug, $storeId);
        } catch (NoSuchEntityException $e) {
            return null;
        }

        $request->setModuleName('feed')
            ->setControllerName('dynamic')
            ->setActionName('index')
            ->setParam('feed_slug', $slug);

        return $this->actionFactory->create(Dynamic::class);
    }
}
