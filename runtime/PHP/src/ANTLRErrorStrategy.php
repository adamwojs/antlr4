<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Exception\RecognitionException;

/**
 * The interface for defining strategies to deal with syntax errors encountered
 * during a parse by ANTLR-generated parsers. We distinguish between three
 * different kinds of errors:
 *
 * <ul>
 * <li>The parser could not figure out which path to take in the ATN (none of
 * the available alternatives could possibly match)</li>
 * <li>The current input does not match what we were looking for</li>
 * <li>A predicate evaluated to false</li>
 * </ul>
 *
 * Implementations of this interface report syntax errors by calling
 * {@link Parser#notifyErrorListeners}.
 *
 * <p>TODO: what to do about lexers</p>
 */
interface ANTLRErrorStrategy
{
    /**
     * Reset the error handler state for the specified {@code recognizer}.
     *
     * @param \ANTLR\v4\Runtime\Parser $recognizer the parser instance
     */
    public function reset(Parser $recognizer): void;

    /**
     * This method is called when an unexpected symbol is encountered during an
     * inline match operation, such as {@link Parser#match}. If the error
     * strategy successfully recovers from the match failure, this method
     * returns the {@link Token} instance which should be treated as the
     * successful result of the match.
     *
     * <p>This method handles the consumption of any tokens - the caller should
     * <b>not</b> call {@link Parser#consume} after a successful recovery.</p>
     *
     * <p>Note that the calling code will not report an error if this method
     * returns successfully. The error strategy implementation is responsible
     * for calling {@link Parser#notifyErrorListeners} as appropriate.</p>
     *
     * @param \ANTLR\v4\Runtime\Parser recognizer the parser instance
     *
     * @return \ANTLR\v4\Runtime\Token
     *
     * @throws \ANTLR\v4\Runtime\Exception\RecognitionException if the error strategy was not able to
     * recover from the unexpected input symbol
     */
    public function recoverInline(Parser $recognizer): Token;

    /**
     * This method is called to recover from exception {@code e}. This method is
     * called after {@link #reportError} by the default exception handler
     * generated for a rule method.
     *
     * @see #reportError
     *
     * @param \ANTLR\v4\Runtime\Parser $recognizer the parser instance
     * @param \ANTLR\v4\Runtime\Exception\RecognitionException $e the recognition exception to recover from
     *
     * @throws \ANTLR\v4\Runtime\Exception\RecognitionException if the error strategy could not recover from
     * the recognition exception
     */
    public function recover(Parser $recognizer, RecognitionException $e): void;

    /**
     * This method provides the error handler with an opportunity to handle
     * syntactic or semantic errors in the input stream before they result in a
     * {@link RecognitionException}.
     *
     * <p>The generated code currently contains calls to {@link #sync} after
     * entering the decision state of a closure block ({@code (...)*} or
     * {@code (...)+}).</p>
     *
     * <p>For an implementation based on Jim Idle's "magic sync" mechanism, see
     * {@link DefaultErrorStrategy#sync}.</p>
     *
     * @see DefaultErrorStrategy#sync
     *
     * @param \ANTLR\v4\Runtime\Parser $recognizer the parser instance
     *
     * @throws \ANTLR\v4\Runtime\Exception\RecognitionException if an error is detected by the error
     * strategy but cannot be automatically recovered at the current state in
     * the parsing process
     */
    public function sync(Parser $recognizer): void;

    /**
     * Tests whether or not {@code recognizer} is in the process of recovering
     * from an error. In error recovery mode, {@link Parser#consume} adds
     * symbols to the parse tree by calling
     * {@link Parser#createErrorNode(ParserRuleContext, Token)} then
     * {@link ParserRuleContext#addErrorNode(ErrorNode)} instead of
     * {@link Parser#createTerminalNode(ParserRuleContext, Token)}.
     *
     * @param \ANTLR\v4\Runtime\Parser recognizer the parser instance
     *
     * @return bool {@code true} if the parser is currently recovering from a parse
     * error, otherwise {@code false}
     */
    public function inErrorRecoveryMode(Parser $recognizer): bool;

    /**
     * This method is called by when the parser successfully matches an input
     * symbol.
     *
     * @param \ANTLR\v4\Runtime\Parser recognizer the parser instance
     */
    public function reportMatch(Parser $recognizer): void;

    /**
     * Report any kind of {@link RecognitionException}. This method is called by
     * the default exception handler generated for a rule method.
     *
     * @param \ANTLR\v4\Runtime\Parser recognizer the parser instance
     * @param RecognitionException $e the recognition exception to report
     */
    public function reportError(Parser $recognizer, RecognitionException $e): void;
}
