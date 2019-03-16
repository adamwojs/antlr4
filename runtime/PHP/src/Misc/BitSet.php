<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

use ANTLR\v4\Runtime\BaseObject;

final class BitSet extends BaseObject
{
    /** @var \GMP */
    private $data;

    public function __construct()
    {
        $this->data = gmp_init(0);
    }

    public function get(int $index): bool
    {
        return gmp_testbit($this->data, $index);
    }

    public function clear(int $index): void
    {
        gmp_clrbit($this->data, $index);
    }

    public function set(int $index): void
    {
        gmp_setbit($this->data, $index);
    }

    /**
     * Returns the number of bits set to {@code true} in this {@code BitSet}.
     *
     * @return int
     */
    public function cardinality(): int
    {
        $sum = 0;
        $idx = -1;

        while (($idx = gmp_scan1($this->data, $idx + 1)) !== -1) {
            $sum++;
        }

        return $sum;
    }

    /**
     * Returns the index of the first bit that is set to {@code true}
     * that occurs on or after the specified starting index. If no such
     * bit exists then {@code -1} is returned.
     *
     * @param int $idx
     *
     * @return int
     */
    public function nextSetBit(int $idx): int
    {
        return gmp_scan1($this->data, $idx);
    }

    /**
     * Performs a logical <b>OR</b> of this bit set with the bit set
     * argument. This bit set is modified so that a bit in it has the
     * value {@code true} if and only if it either already had the
     * value {@code true} or the corresponding bit in the bit set
     * argument has the value {@code true}.
     *
     * @param \ANTLR\v4\Runtime\Misc\BitSet $set
     */
    public function or(self $set): void
    {
        $this->data = gmp_or($this->data, $set->data);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof self) {
            return gmp_cmp($this->data, $o->data) === 0;
        }

        return false;
    }

    public function toString(): string
    {
        $bits = [];

        $idx = -1;
        while (($idx = gmp_scan1($this->data, $idx + 1)) !== -1) {
            $bits[] = $idx;
        }

        return '{' . implode(', ', $bits) . '}';
    }
}
