<?php
/**
 * @copyright Copyright (c) 2026 Dlabsit
 * @license   OSL-3.0
 */

declare(strict_types=1);

namespace Dlabsit\XmlFeed\Model\Feed;

use Dlabsit\XmlFeed\Api\FeedWriterInterface;

class WriterPool
{
    /** @var FeedWriterInterface[] keyed by feed code */
    private array $writers = [];

    /**
     * @param FeedWriterInterface[] $writers
     */
    public function __construct(array $writers = [])
    {
        foreach ($writers as $writer) {
            if (!$writer instanceof FeedWriterInterface) {
                throw new \InvalidArgumentException(
                    'WriterPool expects FeedWriterInterface instances'
                );
            }
            $this->writers[$writer->getCode()] = $writer;
        }
    }

    public function get(string $code): FeedWriterInterface
    {
        if (!isset($this->writers[$code])) {
            throw new \InvalidArgumentException("No feed writer registered for code: {$code}");
        }
        return $this->writers[$code];
    }

    public function has(string $code): bool
    {
        return isset($this->writers[$code]);
    }

    /**
     * @return FeedWriterInterface[]
     */
    public function all(): array
    {
        return $this->writers;
    }

    /**
     * @return string[] list of codes
     */
    public function getCodes(): array
    {
        return array_keys($this->writers);
    }
}
