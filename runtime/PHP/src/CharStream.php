<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Misc\Interval;

/**
 * A source of characters for an ANTLR lexer.
 */
interface CharStream extends IntStream
{
    /**
     * This method returns the text for a range of characters within this input
     * stream. This method is guaranteed to not throw an exception if the
     * specified {@code interval} lies entirely within a marked range. For more
     * information about marked ranges, see {@link IntStream#mark}.
     *
     * @param \ANTLR\v4\Runtime\Misc\Interval $interval an interval within the stream
     *
     * @return string the text of the specified interval
     *
     * @throws \ANTLR\v4\Runtime\Exception\NullPointerException if {@code interval} is {@code null}
     * @throws \ANTLR\v4\Runtime\Exception\IllegalArgumentException if {@code interval.a < 0}, or if
     * {@code interval.b < interval.a - 1}, or if {@code interval.b} lies at or
     * past the end of the stream
     * @throws \ANTLR\v4\Runtime\Exception\UnsupportedOperationException if the stream does not support
     * getting the text of the specified interval
     */
    public function getText(Interval $interval): string;
}
