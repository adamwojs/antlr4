<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Exception;

use ANTLR\v4\Runtime\IntStream;
use ANTLR\v4\Runtime\Misc\IntervalSet;
use ANTLR\v4\Runtime\ParserRuleContext;
use ANTLR\v4\Runtime\Recognizer;
use ANTLR\v4\Runtime\RuleContext;
use ANTLR\v4\Runtime\Token;
use RuntimeException;
use Throwable;

/**
 * The root of the ANTLR exception hierarchy. In general, ANTLR tracks just
 * 3 kinds of errors: prediction errors, failed predicate errors, and
 * mismatched input errors. In each case, the parser knows where it is
 * in the input, where it is in the ATN, the rule invocation stack,
 * and what kind of problem occurred.
 */
class RecognitionException extends RuntimeException
{
    /**
     * The {@link Recognizer} where this exception originated.
     *
     * @var \ANTLR\v4\Runtime\Recognizer|null
     */
    private $recognizer;

    /** @var \ANTLR\v4\Runtime\RuleContext */
    private $ctx;

    /** @var \ANTLR\v4\Runtime\IntStream */
    private $input;

    /**
     * The current {@link Token} when an error occurred. Since not all streams
     * support accessing symbols by index, we have to track the {@link Token}
     * instance itself.
     *
     * @var \ANTLR\v4\Runtime\Token
     */
    private $offendingToken;

    /** @var int */
    private $offendingState = -1;

    public function __construct(
        ?Recognizer $recognizer,
        IntStream $input,
        ?ParserRuleContext $ctx,
        string $message = '',
        int $code = 0,
        Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->recognizer = $recognizer;
        $this->input = $input;
        $this->ctx = $ctx;

        if ($recognizer !== null) {
            $this->offendingState = $recognizer->getState();
        }
    }

    /**
     * Get the ATN state number the parser was in at the time the error
     * occurred. For {@link NoViableAltException} and
     * {@link LexerNoViableAltException} exceptions, this is the
     * {@link DecisionState} number. For others, it is the state whose outgoing
     * edge we couldn't match.
     *
     * <p>If the state number is not known, this method returns -1.</p>
     *
     * @return int
     */
    public function getOffendingState(): int
    {
        return $this->offendingState;
    }

    public function getOffendingToken(): Token
    {
        return $this->offendingToken;
    }

    /**
     * Gets the set of input symbols which could potentially follow the
     * previously matched symbol at the time this exception was thrown.
     *
     * <p>If the set of expected tokens is not known and could not be computed,
     * this method returns {@code null}.</p>
     *
     * @return \ANTLR\v4\Runtime\Misc\IntervalSet the set of token types that could potentially follow the current
     * state in the ATN, or {@code null} if the information is not available
     */
    public function getExpectedTokens(): IntervalSet
    {
        if ($this->recognizer !== null) {
            return $this->recognizer->getATN()->getExpectedTokens($this->offendingState, $this->ctx);
        }

        return null;
    }

    /**
     * Gets the {@link RuleContext} at the time this exception was thrown.
     *
     * <p>If the context is not available, this method returns {@code null}.</p>
     *
     * @return \ANTLR\v4\Runtime\RuleContext|null The {@link RuleContext} at the time this exception was thrown.
     * If the context is not available, this method returns {@code null}.
     */
    public function getCtx(): ?RuleContext
    {
        return $this->ctx;
    }

    /**
     * Gets the input stream which is the symbol source for the recognizer where
     * this exception was thrown.
     *
     * <p>If the input stream is not available, this method returns {@code null}.</p>
     *
     * @return \ANTLR\v4\Runtime\IntStream the input stream which is the symbol source for the recognizer
     * where this exception was thrown, or {@code null} if the stream is not
     * available
     */
    public function getInputStream(): IntStream
    {
        return $this->input;
    }

    /**
     * Gets the {@link Recognizer} where this exception occurred.
     *
     * <p>If the recognizer is not available, this method returns {@code null}.</p>
     *
     * @return \ANTLR\v4\Runtime\Recognizer the recognizer where this exception occurred, or {@code null} if
     * the recognizer is not available
     */
    public function getRecognizer(): Recognizer
    {
        return $this->recognizer;
    }

    final protected function setOffendingState(int $offendingState): void
    {
        $this->offendingState = $offendingState;
    }

    final protected function setOffendingToken(Token $offendingToken): void
    {
        $this->offendingToken = $offendingToken;
    }
}
