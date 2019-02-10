<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

/**
 * This interface provides an abstract concept of object equality independent of
 * {@link Object#equals} (object equality) and the {@code ==} operator
 * (reference equality). It can be used to provide algorithm-specific unordered
 * comparisons without requiring changes to the object itself.
 *
 * @author Sam Harwell
 */
interface EqualityComparator
{
    /**
     * This method returns a hash code for the specified object.
     *
     * @param mixed $obj The object.
     *
     * @return int The hash code for {@code obj}.
     */
    public function hashOf(?BaseObject $obj): int;

    /**
     * This method tests if two objects are equal.
     *
     * @param mixed $a The first object to compare.
     * @param mixed $b The second object to compare.
     *
     * @return {@code true} if {@code a} equals {@code b}, otherwise {@code false}.
     */
    public function equalsTo(?BaseObject $a, ?BaseObject $b): bool;
}
