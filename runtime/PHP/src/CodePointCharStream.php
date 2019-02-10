<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Exception\IllegalStateException;
use ANTLR\v4\Runtime\Misc\Interval;

/**
 * Alternative to {@link ANTLRInputStream} which treats the input
 * as a series of Unicode code points, instead of a series of UTF-16
 * code units.
 *
 * Use this if you need to parse input which potentially contains
 * Unicode values > U+FFFF.
 */
abstract class CodePointCharStream extends BaseObject implements CharStream
{
    /** @var int */
    protected $size;

    /** @var string|null */
    protected $name;

    /** @var int */
    protected $position;

    public function __construct(int $position, int $remaining, ?string $name)
    {
        $this->size = $remaining;
        $this->name = $name;
        $this->position = $position;
    }

    /**
     * {@inheritdoc}
     */
    public function consume(): void
    {
        if ($this->size - $this->position === 0) {
            throw new IllegalStateException("Cannot consume EOF");
        }

        $this->position = $this->position + 1;
    }

    /**
     * {@inheritdoc}
     */
    public function index(): int
    {
        return $this->position;
    }

    /**
     * {@inheritdoc}
     */
    public function size(): int
    {
        return $this->size;
    }

    /**
     * {@inheritdoc}
     */
    public function mark(): int
    {
        // mark do nothing; we have entire buffer
        return -1;
    }

    /**
     * {@inheritdoc}
     */
    public function release(int $marker): void
    {
        // release do nothing; we have entire buffer
        return ;
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $index): void
    {
        $this->position = $index;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceName(): string
    {
        if (empty($this->name)) {
            return self::UNKNOWN_SOURCE_NAME;
        }

        return $this->name;
    }

    public function toString(): string
    {
        return $this->getText(Interval::of(0, $this->size - 1));
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
