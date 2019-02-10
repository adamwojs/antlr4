<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

/**
 * A generic set of integers.
 *
 * @see \ANTLR\v4\Runtime\Misc\IntervalSet
 */
interface IntSet
{
    /**
     * Adds the specified value to the current set.
     *
     * @param int $a
     * @param int $b
     *
     * @throws \ANTLR\v4\Runtime\Exception\IllegalStateException if the current set is read-only
     */
    public function add(int $a, ?int $b = null): void;

    /**
     * Modify the current {@link IntSet} object to contain all elements that are
     * present in itself, the specified {@code set}, or both.
     *
     * @param \ANTLR\v4\Runtime\Misc\IntSet $set The set to add to the current set. A {@code null} argument is
     * treated as though it were an empty set.
     *
     * @return \ANTLR\v4\Runtime\Misc\IntSet {@code this} (to support chained calls)
     *
     * @throws \ANTLR\v4\Runtime\Exception\IllegalStateException  if the current set is read-only
     */
    public function addAll(?IntSet $set): IntSet;

    /**
     * Return a new {@link IntSet} object containing all elements that are
     * present in both the current set and the specified set {@code a}.
     *
     * @param \ANTLR\v4\Runtime\Misc\IntSet $set The set to intersect with the current set. A {@code null}
     * argument is treated as though it were an empty set.
     *
     * @return \ANTLR\v4\Runtime\Misc\IntSet A new {@link IntSet} instance containing the intersection of the
     * current set and {@code a}. The value {@code null} may be returned in
     * place of an empty result set.
     */
    public function and(IntSet $set): IntSet;

    /**
     * Return a new {@link IntSet} object containing all elements that are
     * present in {@code elements} but not present in the current set. The
     * following expressions are equivalent for input non-null {@link IntSet}
     * instances {@code x} and {@code y}.
     *
     * <ul>
     * <li>{@code x.complement(y)}</li>
     * <li>{@code y.subtract(x)}</li>
     * </ul>
     *
     * @param \ANTLR\v4\Runtime\Misc\IntSet $set The set to compare with the current set. A {@code null}
     * argument is treated as though it were an empty set.
     *
     * @return \ANTLR\v4\Runtime\Misc\IntSet A new {@link IntSet} instance containing the elements present in
     * {@code elements} but not present in the current set. The value
     * {@code null} may be returned in place of an empty result set.
     */

    public function complement(IntSet $set): IntSet;

    /**
     * Return a new {@link IntSet} object containing all elements that are
     * present in the current set, the specified set {@code a}, or both.
     *
     * <p>
     * This method is similar to {@link #addAll(IntSet)}, but returns a new
     * {@link IntSet} instance instead of modifying the current set.</p>
     *
     * @param \ANTLR\v4\Runtime\Misc\IntSet $set The set to union with the current set. A {@code null} argument
     * is treated as though it were an empty set.
     *
     * @return \ANTLR\v4\Runtime\Misc\IntSet A new {@link IntSet} instance containing the union of the current
     * set and {@code a}. The value {@code null} may be returned in place of an
     * empty result set.
     */
    public function or(IntSet $set): IntSet;

    /**
     * Return a new {@link IntSet} object containing all elements that are
     * present in the current set but not present in the input set {@code a}.
     * The following expressions are equivalent for input non-null
     * {@link IntSet} instances {@code x} and {@code y}.
     *
     * <ul>
     * <li>{@code y.subtract(x)}</li>
     * <li>{@code x.complement(y)}</li>
     * </ul>
     *
     * @param \ANTLR\v4\Runtime\Misc\IntSet $set The set to compare with the current set. A {@code null}
     * argument is treated as though it were an empty set.
     *
     * @return \ANTLR\v4\Runtime\Misc\IntSet A new {@link IntSet} instance containing the elements present in
     * {@code elements} but not present in the current set. The value
     * {@code null} may be returned in place of an empty result set.
     */
    public function substract(IntSet $set): IntSet;

    /**
     * Return the total number of elements represented by the current set.
     *
     * @return int the total number of elements represented by the current set,
     * regardless of the manner in which the elements are stored.
     */
    public function size(): int;

    /**
     * Returns {@code true} if this set contains no elements.
     *
     * @return bool {@code true} if the current set contains no elements; otherwise, {@code false}.
     *
     * TODO: Rename to isEmpty
     */
    public function isNil(): bool;

    public function equals($o): bool;

    /**
     * Returns {@code true} if the set contains the specified element.
     *
     * @param int $el The element to check for.
     *
     * @return {@code true} if the set contains {@code el}; otherwise {@code false}.
     */
    public function contains(int $el): bool;

    /**
     * Removes the specified value from the current set. If the current set does
     * not contain the element, no changes are made.
     *
     * @param int $el the value to remove
     *
     * @throws \ANTLR\v4\Runtime\Exception\IllegalStateException  if the current set is read-only
     */
    public function remove(int $el): void;

    /**
     * Return a list containing the elements represented by the current set. The
     * list is returned in ascending numerical order.
     *
     * @return int[] A list containing all element present in the current set, sorted
     * in ascending numerical order.
     */
    public function toList(): array;

    public function __toString(): string;
}
