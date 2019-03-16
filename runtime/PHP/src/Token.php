<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

/**
 * A token has properties: text, type, line, character position in the line
 * (so we can ignore tabs), token channel, index, and source from which
 * we obtained this token.
 */
interface Token
{
    public const INVALID_TYPE = 0;

    /**
     * During lookahead operations, this "token" signifies we hit rule end ATN state
     * and did not follow it despite needing to.
     */
    public const EPSILON = -2;

    public const MIN_USER_TOKEN_TYPE = 1;

    public const EOF = IntStream::EOF;

    /**
     * All tokens go to the parser (unless skip() is called in that rule)
     * on a particular "channel".  The parser tunes to a particular channel
     * so that whitespace etc... can go to the parser on a "hidden" channel.
     */
    public const DEFAULT_CHANNEL = 0;

    /**
     * Anything on different channel than DEFAULT_CHANNEL is not parsed
     * by parser.
     */
    public const HIDDEN_CHANNEL = 1;

    /**
     * This is the minimum constant value which can be assigned to a
     * user-defined token channel.
     *
     * <p>
     * The non-negative numbers less than {@link #MIN_USER_CHANNEL_VALUE} are
     * assigned to the predefined channels {@link #DEFAULT_CHANNEL} and
     * {@link #HIDDEN_CHANNEL}.</p>
     *
     * @see Token#getChannel()
     */
    public const MIN_USER_CHANNEL_VALUE = 2;

    /**
     * Get the text of the token.
     *
     * @return string|null
     */
    public function getText(): ?string;

    /**
     * Get the token type of the token.
     *
     * @return int
     */
    public function getType(): int;

    /**
     * The line number on which the 1st character of this token was matched, line=1..n.
     *
     * @return int
     */
    public function getLine(): int;

    /**
     * The index of the first character of this token relative to the
     * beginning of the line at which it occurs, 0..n-1.
     *
     * @return int
     */
    public function getCharPositionInLine(): int;

    /**
     * Return the channel this token. Each token can arrive at the parser
     * on a different channel, but the parser only "tunes" to a single channel.
     * The parser ignores everything not on DEFAULT_CHANNEL.
     *
     * @return int
     */
    public function getChannel(): int;

    /**
     * An index from 0..n-1 of the token object in the input stream.
     * This must be valid in order to print token streams and
     * use TokenRewriteStream.
     *
     * Return -1 to indicate that this token was conjured up since
     * it doesn't have a valid index.
     *
     * @return int
     */
    public function getTokenIndex(): int;

    /**
     * The starting character index of the token
     * This method is optional; return -1 if not implemented.
     *
     * @return int
     */
    public function getStartIndex(): int;

    /**
     * The last character index of the token.
     * This method is optional; return -1 if not implemented.
     *
     * @return int
     */
    public function getStopIndex(): int;

    /**
     * Gets the {@link TokenSource} which created this token.
     *
     * @return \ANTLR\v4\Runtime\TokenSource
     */
    public function getTokenSource(): TokenSource;

    /**
     * Gets the {@link CharStream} from which this token was derived.
     */
    public function getInputStream(): CharStream;
}
