<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Misc\Character;
use ANTLR\v4\Runtime\Misc\IntervalSet;

/**
 * Utility class to create {@link AtomTransition}, {@link RangeTransition},
 * and {@link SetTransition} appropriately based on the range of the input.
 *
 * To keep the serialized ATN size small, we only inline atom and
 * range transitions for Unicode code points <= U+FFFF.
 *
 * Whenever we encounter a Unicode code point > U+FFFF, we represent that
 * as a set transition (even if it is logically an atom or a range).
 */
final class CodePointTransitions extends BaseObject
{
    /**
     * If {@code codePoint} is <= U+FFFF, returns a new {@link AtomTransition}.
     * Otherwise, returns a new {@link SetTransition}.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     * @param int $codePoint
     *
     * @return \ANTLR\v4\Runtime\ATN\Transition
     */
    public static function createWithCodePoint(ATNState $target, int $codePoint): Transition
    {
        if (Character::isSupplementaryCodePoint($codePoint)) {
            return new SetTransition($target, IntervalSet::of($codePoint));
        }

        return new AtomTransition($target, $codePoint);
    }

    /**
     * If {@code codePointFrom} and {@code codePointTo} are both
     * <= U+FFFF, returns a new {@link RangeTransition}.
     * Otherwise, returns a new {@link SetTransition}.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     * @param int $codePointFrom
     * @param int $codePointTo
     *
     * @return \ANTLR\v4\Runtime\ATN\Transition
     */
    public static function createWithCodePointRange(
        ATNState $target,
        int $codePointFrom,
        int $codePointTo
    ): Transition {
        $isSupplementary = Character::isSupplementaryCodePoint($codePointFrom) ||
            Character::isSupplementaryCodePoint($codePointTo);

        if ($isSupplementary) {
            return new SetTransition($target, IntervalSet::of($codePointFrom, $codePointTo));
        }

        return new RangeTransition($target, $codePointFrom, $codePointTo);
    }
}
