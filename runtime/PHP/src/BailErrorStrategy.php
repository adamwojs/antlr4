<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Exception\InputMismatchException;
use ANTLR\v4\Runtime\Exception\ParseCancellationException;
use ANTLR\v4\Runtime\Exception\RecognitionException;

/**
 * This implementation of {@link ANTLRErrorStrategy} responds to syntax errors
 * by immediately canceling the parse operation with a
 * {@link ParseCancellationException}. The implementation ensures that the
 * {@link ParserRuleContext#exception} field is set for all parse tree nodes
 * that were not completed prior to encountering the error.
 *
 * <p>
 * This error strategy is useful in the following scenarios.</p>
 *
 * <ul>
 * <li><strong>Two-stage parsing:</strong> This error strategy allows the first
 * stage of two-stage parsing to immediately terminate if an error is
 * encountered, and immediately fall back to the second stage. In addition to
 * avoiding wasted work by attempting to recover from errors here, the empty
 * implementation of {@link BailErrorStrategy#sync} improves the performance of
 * the first stage.</li>
 * <li><strong>Silent validation:</strong> When syntax errors are not being
 * reported or logged, and the parse result is simply ignored if errors occur,
 * the {@link BailErrorStrategy} avoids wasting work on recovering from errors
 * when the result will be ignored either way.</li>
 * </ul>
 *
 * <p>
 * {@code myparser.setErrorHandler(new BailErrorStrategy());}</p>
 *
 * @see Parser#setErrorHandler(ANTLRErrorStrategy)
 */
class BailErrorStrategy extends DefaultErrorStrategy
{
    /**
     * {@inheritdoc}
     *
     * Instead of recovering from exception {@code e}, re-throw it wrapped
     * in a {@link ParseCancellationException} so it is not caught by the
     * rule function catches.  Use {@link Exception#getCause()} to get the
     * original {@link RecognitionException}.
     */
    public function recover(Parser $recognizer, RecognitionException $e): void
    {
        for ($c = $recognizer->getContext(); $c !== null; $c = $c->getParent()) {
            $c->exception = $e;
        }

        throw new ParseCancellationException($e);
    }

    /**
     * {@inheritdoc}
     *
     * Make sure we don't attempt to recover inline; if the parser
     * successfully recovers, it won't throw an exception.
     */
    public function recoverInline(Parser $recognizer): Token
    {
        $e = new InputMismatchException($recognizer);
        for ($c = $recognizer->getContext(); $c !== null; $c = $c->getParent()) {
            $c->exception = $e;
        }

        throw new ParseCancellationException($e);
    }

    /**
     * {@inheritdoc}
     *
     * Make sure we don't attempt to recover from problems in subrules.
     */
    public function sync(Parser $recognizer): void
    {
    }
}
