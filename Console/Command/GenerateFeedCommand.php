<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Console\Command;

use Dlabsit\XmlFeed\Api\FeedRepositoryInterface;
use Dlabsit\XmlFeed\Model\Feed\Generator;
use Dlabsit\XmlFeed\Model\ResourceModel\Feed\CollectionFactory as FeedCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateFeedCommand extends Command
{
    private const OPT_SLUG = 'slug';
    private const OPT_ALL = 'all';

    public function __construct(
        private readonly Generator $generator,
        private readonly FeedRepositoryInterface $feedRepository,
        private readonly FeedCollectionFactory $collectionFactory,
        private readonly FileDriver $fileDriver,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('xml-feed:generate')
            ->setDescription('Generate one or all XML product feeds from the feed registry')
            ->addOption(self::OPT_SLUG, null, InputOption::VALUE_OPTIONAL, 'Feed slug (matches registry row)', null)
            ->addOption(self::OPT_ALL, null, InputOption::VALUE_NONE, 'Generate every active feed');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->appState->setAreaCode(Area::AREA_FRONTEND);
        } catch (\Exception $e) {
            // already set
        }

        $slug = $input->getOption(self::OPT_SLUG);
        $all = (bool) $input->getOption(self::OPT_ALL);

        if (!$all && $slug === null) {
            $output->writeln('<error>Specify either --slug=<feed-slug> or --all</error>');
            $this->listFeeds($output);
            return Command::FAILURE;
        }

        try {
            if ($all) {
                $collection = $this->collectionFactory->create();
                $collection->addFieldToFilter('is_active', 1)->setOrder('sort_order', 'ASC');
                if ($collection->getSize() === 0) {
                    $output->writeln('<comment>No active feeds in registry.</comment>');
                    return Command::SUCCESS;
                }
                foreach ($collection as $feed) {
                    $this->runOne($feed, $output);
                }
                return Command::SUCCESS;
            }

            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('slug', $slug)->setPageSize(1);
            /** @var \Dlabsit\XmlFeed\Model\Feed|null $feed */
            $feed = $collection->getFirstItem();
            if (!$feed || !$feed->getFeedId()) {
                $output->writeln("<error>No feed found with slug '{$slug}'.</error>");
                $this->listFeeds($output);
                return Command::FAILURE;
            }
            $this->runOne($feed, $output);
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $output->writeln('<error>Failed: ' . $e->getMessage() . '</error>');
            return Command::FAILURE;
        }
    }

    private function runOne(\Dlabsit\XmlFeed\Api\Data\FeedInterface $feed, OutputInterface $output): void
    {
        $label = sprintf('%s (%s, store %d)', $feed->getSlug(), $feed->getChannelCode(), $feed->getStoreId());
        $output->writeln("<info>Generating {$label}…</info>");
        $start = microtime(true);
        try {
            $path = $this->generator->generateForFeed($feed);
            $elapsed = round(microtime(true) - $start, 2);
            $size = $this->formatBytes($this->fileSize($path));
            $output->writeln("  <info>✓</info> {$path} ({$size}, {$elapsed}s)");
        } catch (\Exception $e) {
            $output->writeln("  <error>✗ {$e->getMessage()}</error>");
        }
    }

    private function listFeeds(OutputInterface $output): void
    {
        $collection = $this->collectionFactory->create()->setOrder('sort_order', 'ASC');
        if ($collection->getSize() === 0) {
            $output->writeln('<comment>Registry is empty. Create feeds in admin: Dlabsit - XML Feed → Feeds.</comment>');
            return;
        }
        $output->writeln('Available feed slugs:');
        foreach ($collection as $feed) {
            $mark = $feed->isActive() ? '✓' : '✗';
            $output->writeln(sprintf('  %s %-24s %s / store %d', $mark, $feed->getSlug(), $feed->getChannelCode(), $feed->getStoreId()));
        }
    }

    private function fileSize(string $path): int
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
