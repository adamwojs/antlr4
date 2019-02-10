<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

final class Character
{
    /**
     * The minimum value of a Unicode supplementary code point
     *
     * @var int
     */
    public const MIN_SUPPLEMENTARY_CODE_POINT = 0x010000;

    /**
     * The maximum value of a Unicode code point, constant {@code U+10FFFF}.
     *
     * @var int
     */
    public const MAX_CODE_POINT = 0X10FFFF;

    /**
     * The constant value of this field is the largest value of type
     * {@code char}, {@code '\u005CuFFFF'}.
     *
     * @var int
     */
    public const MAX_VALUE = 0xFFFF;

    /**
     * Determines whether the specified character (Unicode code point)
     * is in the supplementary character range.
     *
     * @param int $codePoint
     *
     * @return bool
     */
    public static function isSupplementaryCodePoint(int $codePoint): bool
    {
        return $codePoint >= self::MIN_SUPPLEMENTARY_CODE_POINT
            && $codePoint < self::MAX_CODE_POINT + 1;
    }
}
