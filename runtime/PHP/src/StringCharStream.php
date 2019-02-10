<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Exception\IllegalStateException;
use ANTLR\v4\Runtime\Misc\Interval;

class StringCharStream implements CharStream
{
    /** @var string */
    private $name;

    /** @var string */
    private $data;

    /** @var int */
    private $size;

    /** @var int */
    private $pos;

    public function __construct(string $data, string $name = '')
    {
        $this->data = $data;
        $this->name = $name;
        $this->size = strlen($data);
        $this->pos  = 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getText(Interval $interval): string
    {
        $idx = min($interval->a, $this->size);
        $len = min($interval->b - $interval->a + 1, $this->size - $idx);

        return substr($this->data, $idx, $len);
    }

    /**
     * {@inheritdoc}
     */
    public function LA(int $i): int
    {
        if ($i === 0) {
            return 0; // Undefined
        }

        if ($i < 0) {
            $idx = $this->pos + $i;

            if ($idx < 0) {
                return IntStream::EOF;
            }

            return ord($this->data[$idx]);
        } else {
            $idx = $this->pos + $i - 1;

            if ($idx >= $this->size) {
                return IntStream::EOF;
            }

            return ord($this->data[$idx]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function consume(): void
    {
        if ($this->size - $this->pos === 0) {
            assert($this->LA(1) === IntStream::EOF);
            throw new IllegalStateException("cannot consume EOF");
        }

        $this->pos += 1;
    }

    /**
     * {@inheritdoc}
     */
    public function mark(): int
    {
        return -1;
    }

    /**
     * {@inheritdoc}
     */
    public function release(int $marker): void
    {
        // do nothing
    }

    /**
     * {@inheritdoc}
     */
    public function index(): int
    {
        return $this->pos;
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $index): void
    {
        $this->pos = $index;
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
    public function getSourceName(): string
    {
        return $this->name;
    }
}
