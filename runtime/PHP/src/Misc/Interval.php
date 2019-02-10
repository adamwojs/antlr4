<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

/**
 * An immutable inclusive interval a..b
 */
class Interval
{
    /** @var int */
    public $a;

    /** @var int */
    public $b;

    /**
     * @param int $a
     * @param int $b
     */
    public function __construct(int $a, int $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    /**
     * Return number of elements between a and b inclusively. x..x is length 1.
     * if b < a, then length is 0.
     *
     * @return int
     */
    public function length(): int
    {
        if ($this->b < $this->a) {
            return 0;
        }

        return $this->b - $this->a + 1;
    }

    /**
     * Return true if given $o is equal to this interval.
     *
     * @param mixed $o
     * 
     * @return bool
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof Interval) {
            return $this->a === $o->a && $this->b === $o->b;
        }

        return false;
    }

    /**
     * Does this start completely before other?
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return bool
     */
    public function startsBeforeDisjoint(Interval $other): bool
    {
        return $this->a < $other->a && $this->b < $other->a;
    }

    /**
     * Does this start at or before other?
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return bool
     */
    public function startsBeforeNonDisjoint(Interval $other): bool
    {
        return $this->a <= $other->a && $this->b >= $other->a;
    }

    /**
     * Does this.a start after other.b? May or may not be disjoint
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return bool
     */
    public function startsAfter(Interval $other): bool
    {
        return $this->a > $other->a;
    }

    /**
     * Does this start completely after other? Disjoint
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return bool
     */
    public function startsAfterDisjoint(Interval $other): bool
    {
        return $this->a > $other->b;
    }

    /**
     * Does this start after other? NonDisjoint
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return bool
     */
    public function startsAfterNonDisjoint(Interval $other): bool
    {
        return $this->a > $other->a && $this->a <= $other->b; // $this->>b >= $other->b implied
    }

    /**
     * Are both ranges disjoint? I.e., no overlap?
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return bool
     */
    public function disjoint(Interval $other): bool
    {
        return $this->startsBeforeDisjoint($other) || $this->startsAfterDisjoint($other);
    }

    /**
     * Are two intervals adjacent such as 0..41 and 42..42?
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return bool
     */
    public function adjacent(Interval $other): bool
    {
        return $this->a === $other->b + 1 || $this->b === $other->a - 1;
    }

    /**
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return bool
     */
    public function properlyContains(Interval $other): bool
    {
        return $other->a >= $this->a && $other->b <= $this->b;
    }

    /**
     * Return the interval computed from combining this and other
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return \ANTLR\v4\Runtime\Misc\Interval
     */
    public function union(Interval $other): self
    {
        return self::of(min($this->a, $other->a), max($this->b, $other->b));
    }

    /**
     * Return the interval in common between this and other
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return \ANTLR\v4\Runtime\Misc\Interval
     */
    public function intersection(Interval $other): self
    {
        return self::of(max($this->a, $other->a), min($this->b, $other->b));
    }

    /**
     * Return the interval with elements from this not in other;
     * other must not be totally enclosed (properly contained)
     * within this, which would result in two disjoint intervals
     * instead of the single one returned by this method.
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $other
     *
     * @return \ANTLR\v4\Runtime\Misc\Interval|null
     */
    public function differenceNotProperlyContained(Interval $other): ?self
    {
        if ($other->startsBeforeNonDisjoint($this)) {
            // $other->a to left of $this->>a (or same)
            return self::of(max($this->a, $other->b + 1), $this->b);
        }

        if ($other->startsAfterNonDisjoint($this)) {
            // $other->a to right of $this->a
            return self::of($this->a, $other->a - 1);
        }

        return null;
    }

    public function __toString(): string
    {
        return "{$this->a}..{$this->b}";
    }

    public static function getInvalid(): Interval
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new Interval(-1, -2);
        }

        return $instance;
    }

    public static function of(int $a, int $b): Interval
    {
        // TODO: Missing cache implementation
        return new self($a, $b);
    }
}
