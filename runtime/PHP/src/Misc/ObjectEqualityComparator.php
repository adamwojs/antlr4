<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\EqualityComparator;

/**
 * This default implementation of {@link EqualityComparator} uses object equality
 * for comparisons by calling {@link Object#hashCode} and {@link Object#equals}.
 *
 * @author Sam Harwell
 */
final class ObjectEqualityComparator implements EqualityComparator
{
    private function __construct()
    {
    }

    /**
     * {@inheritdoc}
     *
     * <p>This implementation returns
     * {@code obj.}{@link Object#hashCode hashCode()}.</p>
     */
    public function hashOf(?BaseObject $o): int
    {
        if ($o === null) {
            return 0;
        }

        return $o->hash();
    }

    /**
     * {@inheritdoc}
     *
     * <p>This implementation relies on object equality. If both objects are
     * {@code null}, this method returns {@code true}. Otherwise if only
     * {@code a} is {@code null}, this method returns {@code false}. Otherwise,
     * this method returns the result of
     * {@code a.}{@link Object#equals equals}{@code (b)}.</p>
     */
    public function equalsTo(?BaseObject $a, ?BaseObject $b): bool
    {
        if ($a === null) {
            return $b === null;
        }

        return $a->equals($b);
    }

    public static function getInstance(): self
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }
}
