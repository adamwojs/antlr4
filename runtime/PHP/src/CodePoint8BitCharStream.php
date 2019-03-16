<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Exception\UnsupportedOperationException;
use ANTLR\v4\Runtime\Misc\Interval;

/**
 * 8-bit storage for code points <= U+00FF.
 */
final class CodePoint8BitCharStream extends CodePointCharStream
{
    /** @var array */
    private $data;

    public function __construct(int $position, int $remaining, ?string $name, array $data, int $arrayOffset)
    {
        parent::__construct($position, $remaining, $name);

        assert($arrayOffset === 0);
        $this->data = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function getText(Interval $interval): string
    {
        $offset = min($interval->a, $this->size);
        $length = min($interval->b - $interval->a + 1, $this->size - $offset);

        return implode('', array_slice($this->data, $offset, $length));
    }

    /**
     * {@inheritdoc}
     */
    public function LA(int $i): int
    {
        $signum = $i <=> 0;

        switch ($signum) {
            case -1:
                $offset = $this->position + $i;
                if ($offset < 0) {
                    return IntStream::EOF;
                }

                return $this->data[$i] & 0xFF;
            case 0:
                // Undefined
                return 0;
            case 1:
                $offset = $this->position + $i - 1;
                if ($offset >= $this->size) {
                    return self::EOF;
                }

                return $this->data[$offset] & 0xFF;
        }

        throw new UnsupportedOperationException('Non reachable');
    }
}
