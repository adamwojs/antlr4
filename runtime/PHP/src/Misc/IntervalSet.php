<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Exception\IllegalStateException;
use ANTLR\v4\Runtime\Token;
use ANTLR\v4\Runtime\Vocabulary;
use ANTLR\v4\Runtime\VocabularyInterface;
use IntlChar;
use RuntimeException;
use SplDoublyLinkedList;

/**
 * This class implements the {@link IntSet} backed by a sorted array of
 * non-overlapping intervals. It is particularly efficient for representing
 * large collections of numbers, where the majority of elements appear as part
 * of a sequential range of numbers that are all part of the set. For example,
 * the set { 1, 2, 3, 4, 7, 8 } may be represented as { [1, 4], [7, 8] }.
 *
 * <p>
 * This class is able to represent sets containing any combination of values in
 * the range {@link Integer#MIN_VALUE} to {@link Integer#MAX_VALUE}
 * (inclusive).</p>
 */
class IntervalSet extends BaseObject implements IntSet
{
    /**
     * /** The list of sorted, disjoint intervals.
     *
     * @var \ANTLR\v4\Runtime\Misc\Interval[]
     */
    protected $intervals;

    /** @var bool */
    protected $readonly = false;

    public function __construct()
    {
        $this->intervals = new SplDoublyLinkedList();
    }

    /**
     * {@inheritdoc}
     *
     *  An isolated element is stored as a range el..el.
     */
    public function add(int $a, ?int $b = null): void
    {
        $this->doAdd(Interval::of($a, $b ?? $a));
    }

    /**
     * {@inheritdoc}
     */
    public function addAll(?IntSet $set): IntSet
    {
        if ($set === null) {
            return $this;
        }

        if ($set instanceof IntervalSet) {
            foreach ($set->intervals as $interval) {
                $this->add($interval->a, $interval->b);
            }

            return $this;
        }

        foreach ($set->toList() as $value) {
            $this->add($value);
        }

        return $this;
    }

    public function clear(): void
    {
        $this->throwIfReadonly();
        $this->intervals = new SplDoublyLinkedList();
    }
    
    /**
     * {@inheritdoc}
     */
    public function complement(?IntSet $set): IntSet
    {
        if ($set === null || $set->isNil()) {
            // nothing in common with null set
            return null;
        }

        if (!($set instanceof IntervalSet)) {
            $set = (new IntervalSet())->addAll($set);
        }

        return $set->substract($this);
    }

    /**
     * {@inheritdoc}
     */
    public function contains(int $el): bool
    {
        $n = $this->intervals->count();
        $l = 0;
        $r =$n - 1;

        // Binary search for the element in the (sorted,
        // disjoint) array of intervals.
        while ($l <= $r) {
            $m = (int) (($l + $r) / 2);

            $a = $this->intervals[$m]->a;
            $b = $this->intervals[$m]->b;

            if ($b < $el) {
                $l = $m + 1;
            } elseif ($a > $el) {
                $r = $m - 1;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a list of Interval objects.
     *
     * @return \SplDoublyLinkedList
     */
    public function getIntervals(): SplDoublyLinkedList
    {
        return $this->intervals;
    }

    /**
     * Returns the maximum value contained in the set if not isNil().
     *
     * @return int the maximum value contained in the set.
     *
     * @throws \RuntimeException if set is empty
     */
    public function getMaxElement(): int
    {
        if ($this->isNil()) {
            throw new RuntimeException("Set is empty");
        }

        return $this->intervals[$this->intervals->count() - 1]->b;
    }

    /**
     * Returns the minimum value contained in the set if not isNil().
     *
     * @return int the minimum value contained in the set.
     *
     * @throws \RuntimeException if set is empty
     */
    public function getMinElement(): int
    {
        if ($this->isNil()) {
            throw new RuntimeException("Set is empty");
        }

        return $this->intervals[0]->a;
    }

    /**
     * {@inheritdoc}
     */
    public function remove(int $el): void
    {
        $this->throwIfReadonly();

        foreach ($this->intervals as $i => $interval) {
            $a = $interval->a;
            $b = $interval->b;

            if ($el < $a) {
                // list is sorted and el is before this interval; not here
                break;
            }

            // if whole interval x..x, rm
            if ($el === $a && $el === $b) {
                unset($this->intervals[$i]);
                break;
            }

            // if on left edge x..b, adjust left
            if ($el === $a) {
                $interval->a++;
                break;
            }

            // if on right edge a..x, adjust right
            if ($el === $b) {
                $interval->b++;
                break;
            }

            // if in middle a..x..b, split interval
            if ($el > $a && $el < $b) {
                $interval->b = $el - 1;  // [a..x-1]
                $this->add($el + 1, $b); // add [x+1..b]
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function size(): int
    {
        $n = 0;
        foreach ($this->intervals as $interval) {
            $n += $interval->b - $interval->a + 1;
        }

        return $n;
    }

    /**
     * {@inheritdoc}
     */

    public function and(IntSet $set): IntSet
    {
        if ($set === null || !($set instanceof IntervalSet)) {
            // nothing in common with null set
            return null;
        }

        $intersection = new IntervalSet();

        $an = $this->intervals->count();
        $bn = $set->intervals->count();

        $i = $j = 0;

        // iterate down both interval lists looking for nondisjoint intervals
        while ($i < $an && $j < $bn) {
            /** @var \ANTLR\v4\Runtime\Misc\Interval $a */
            $a = $this->intervals[$i];
            /** @var \ANTLR\v4\Runtime\Misc\Interval $b */
            $b = $set->intervals[$j];

            if ($a->startsBeforeDisjoint($b)) {
                // move this iterator looking for interval that might overlap
                $i++;
            } else if ($b->startsBeforeDisjoint($a)) {
                // move other iterator looking for interval that might overlap
                $j++;
            } else if ($a->properlyContains($b)) {
                // overlap, add intersection, get next theirs
                $intersection->doAdd($a->intersection($b));
                $j++;
            } else if ($b->properlyContains($a)) {
                // overlap, add intersection, get next mine
                $intersection->doAdd($a->intersection($b));
                $i++;
            } else if (!$a->disjoint($b)) {
                // overlap, add intersection
                $intersection->doAdd($a->intersection($b));

                // Move the iterator of lower range [a..b], but not
                // the upper range as it may contain elements that will collide
                // with the next iterator. So, if mine=[0..115] and
                // theirs=[115..200], then intersection is 115 and move mine
                // but not theirs as theirs may collide with the next range
                // in thisIter.
                // move both iterators to next ranges
                if ($a->startsAfterNonDisjoint($b)) {
                    $j++;
                } else if ($b->startsAfterNonDisjoint($a)) {
                    $i++;
                }
            }
        }

        return $intersection;
    }

    /**
     * {@inheritdoc}
     */
    public function or(IntSet $set): IntSet
    {
        $result = new IntervalSet();
        $result->addAll($this);
        $result->addAll($set);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function substract(IntSet $set): IntSet
    {
        if ($set === null || $set->isNil()) {
            return self::copy($this);
        }

        if (!($set instanceof IntervalSet)) {
            $set = (new IntervalSet())->addAll($set);
        }

        return $this->doSubtract($this, $set);
    }

    /**
     * Compute the set difference between two interval sets. The specific
     * operation is {@code left - right}. If either of the input sets is
     * {@code null}, it is treated as though it was an empty set.
     *
     * @param \ANTLR\v4\Runtime\Misc\IntervalSet|null $left
     * @param \ANTLR\v4\Runtime\Misc\IntervalSet|null $right
     *
     * @return \ANTLR\v4\Runtime\Misc\IntervalSet
     */
    private function doSubtract(?IntervalSet $left, ?IntervalSet $right): IntervalSet
    {
        if ($left === null || $left->isNil()) {
            return new IntervalSet();
        }

        $result = self::copy($left);
        if ($right === null || $right->isNil()) {
            // right set has no elements; just return the copy of the current set
            return $result;
        }

        $resultI = $rightI = 0;
        while ($resultI < $result->intervals->count() && $resultI < $result->intervals->count()) {
            $resultInterval = $result->intervals[$resultI];
            $rightInterval = $right->intervals[$resultI];

            // operation: (resultInterval - rightInterval) and update indexes
            if ($rightInterval->b < $resultInterval->a) {
                $rightI++;
                continue;
            }

            if ($rightInterval->a > $resultInterval->b) {
                $resultI++;
                continue;
            }

            $beforeCurrent = $afterCurrent = null;

            if ($resultInterval->a > $resultInterval->a) {
                $beforeCurrent = new Interval($resultInterval->a, $rightInterval->a - 1);
            }

            if ($resultInterval->b < $resultInterval->b) {
                $afterCurrent = new Interval($rightInterval->b + 1, $resultInterval->b);
            }

            if ($beforeCurrent !== null) {
                if ($afterCurrent !== null) {
                    // split the current interval into two
                    $result->intervals[$resultI] = $beforeCurrent;
                    $result->intervals->add($resultI + 1, $afterCurrent);


                    $resultI++;
                    $rightI++;
                    continue;
                } else {
                    // replace the current interval
                    $result->intervals[$resultI] = $beforeCurrent;

                    $resultI++;
                    continue;
                }
            } else {
                if ($afterCurrent !== null) {
                    // replace the current interval
                    $result->intervals[$resultI] = $afterCurrent;

                    $rightI++;
                    continue;
                } else {
                    // remove the current interval (thus no need to increment resultI)
                    unset($result->intervals[$resultI]);

                    continue;
                }
            }
        }

        // If rightI reached $right->intervals->count(), no more intervals to subtract from result.
        // If resultI reached $result->intervals->count(), we would be subtracting from an empty set.
        // Either way, we are done.
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function isNil(): bool
    {
        return $this->intervals->isEmpty();
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    /**
     * {@inheritdoc}
     */
    public function setReadonly(bool $readonly): void
    {
        if ($this->readonly && !$readonly) {
            throw new IllegalStateException("Can't alter readonly IntervalSet");
        }

        $this->readonly = $readonly;
    }

    /**
     * {@inheritdoc}
     */
    public function toList(): array
    {
        $values = [];

        foreach ($this->intervals as $interval) {
            $a = $interval->a;
            $b = $interval->b;
            for($v = $a; $v <= $b; $v++) {
                $values[] = $v;
            }
        }

        return $values;
    }

    public function toString(bool $elemAreChar = false): string
    {
        if ($this->intervals === null || $this->intervals->isEmpty()) {
            return '{}';
        }

        $items = [];
        foreach ($this->intervals as $interval) {
            $a = $interval->a;
            $b = $interval->b;

            if ($a === $b) {
                if ($a === Token::EOF) {
                    $items[] = '<EOF>';
                } elseif ($elemAreChar) {
                    $items[] = "'" . IntlChar::chr($a) . "'";
                } else {
                    $items[] = $a;
                }

                continue;
            } else {
                if ($elemAreChar) {
                    $a = "'" . IntlChar::chr($a) . "'";
                    $b = "'" . IntlChar::chr($a) . "'";
                }

                $items[] = "$a..$b";
            }
        }

        if ($this->intervals->count() > 1) {
            return '{' . implode(', ', $items) . '}';
        }

        return (string)$items[0];
    }

    public function toStringUsingVocabulary(VocabularyInterface $vocabulary): string
    {
        if ($this->intervals === null || $this->intervals->isEmpty()) {
            return '{}';
        }

        $items = [];
        foreach ($this->intervals as $interval) {
            $a = $interval->a;
            $b = $interval->b;

            if ($a === $b) {
                $items[] = $this->elementName($vocabulary, $a);
            }
            else {
                for ($i = $a; $i <= $b; $i++) {
                    $items[] = $this->elementName($vocabulary, $i);
                }
            }
        }

        if ($this->size() > 1) {
            return '{' . implode(', ', $items) . '}';
        }

        return (string)$items[0];
    }

    protected function elementName(VocabularyInterface $vocabulary, int $a): string
    {
        if ($a === Token::EOF) {
            return '<EOF>';
        }

        if ($a === Token::EPSILON) {
            return '<EPSILON>';
        }

        return $vocabulary->getDisplayName($a);
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        $hash = MurmurHash::initialize();
        foreach ($this->intervals as $interval) {
            $hash = MurmurHash::update($hash, $interval->a);
            $hash = MurmurHash::update($hash, $interval->b);
        }

        return MurmurHash::finish($hash, 2 * $this->intervals->count());
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === null || !($o instanceof IntervalSet)) {
            return false;
        }

        if ($this->intervals->count() !== $o->intervals->count()) {
            return false;
        }

        foreach ($this->intervals as $i => $interval) {
            if (!$interval->equals($o->intervals[$i])) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return $this->toString();
    }

    private function throwIfReadonly(): void
    {
        if ($this->readonly) {
            throw new IllegalStateException("Can't alter readonly IntervalSet");
        }
    }

    private function doAdd(Interval $addition): void
    {
        $this->throwIfReadonly();

        if ($addition->b < $addition->a) {
            return;
        }

        foreach ($this->intervals as $i => $r) {
            if ($addition->equals($r)) {
                return;
            }

            if ($addition->startsBeforeDisjoint($r)) {
                $this->intervals->add($i, $addition);
                return;
            }

            if ($addition->adjacent($r) || !$addition->disjoint($r)) {
                // next to each other, make a single larger r
                $bigger = $addition->union($r);

                // make sure we didn't just create an r that
                // should be merged with next r in list
                while ($i + 1 < count($this->intervals)) {
                    $next = $this->intervals[$i + 1];
                    if (!$bigger->adjacent($next) && $bigger->disjoint($next)) {
                        break;
                    }

                    // if we bump up against or overlap next, merge
                    $bigger = $bigger->union($next);

                    unset($this->intervals[$i + 1]);
                }

                $this->intervals[$i] = $bigger;
                return;
            }

            // if disjoint and after r, a future iteration will handle it
        }

        $this->intervals->push($addition);
    }

    /**
     * Create a set with a single element or set with all ints within range [a..b] (inclusive) if $b specified.
     *
     * @param int $a
     * @param int|null $b
     *
     * @return \ANTLR\v4\Runtime\Misc\IntervalSet
     */
    public static function of(int $a, ?int $b = null): IntervalSet
    {
        $set = new IntervalSet();
        $set->add($a, $b);

        return $set;
    }

    public static function copy(IntervalSet $source): IntervalSet
    {
        $set = new IntervalSet();
        $set->addAll($source);

        return $set;
    }
}
