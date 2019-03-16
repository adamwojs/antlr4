<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

/**
 * This class extends {@link BufferedTokenStream} with functionality to filter
 * token streams to tokens on a particular channel (tokens where
 * {@link Token#getChannel} returns a particular value).
 *
 * <p>
 * This token stream provides access to all tokens by index or when calling
 * methods like {@link #getText}. The channel filtering is only used for code
 * accessing tokens via the lookahead methods {@link #LA}, {@link #LT}, and
 * {@link #LB}.</p>
 *
 * <p>
 * By default, tokens are placed on the default channel
 * ({@link Token#DEFAULT_CHANNEL}), but may be reassigned by using the
 * {@code ->channel(HIDDEN)} lexer command, or by using an embedded action to
 * call {@link Lexer#setChannel}.
 * </p>
 *
 * <p>
 * Note: lexer rules which use the {@code ->skip} lexer command or call
 * {@link Lexer#skip} do not produce tokens at all, so input text matched by
 * such a rule will not be available as part of the token stream, regardless of
 * channel.</p>we
 */
class CommonTokenStream extends BufferedTokenStream
{
    /**
     * Specifies the channel to use for filtering tokens.
     *
     * <p>The default value is {@link Token#DEFAULT_CHANNEL}, which matches the
     * default channel assigned to tokens created by the lexer.</p>
     *
     * @var int
     */
    protected $channel = Token::DEFAULT_CHANNEL;

    /**
     * Constructs a new {@link CommonTokenStream} using the specified token
     * source and filtering tokens to the specified channel. Only tokens whose
     * {@link Token#getChannel} matches {@code channel} or have the
     * {@link Token#getType} equal to {@link Token#EOF} will be returned by the
     * token stream lookahead methods.
     *
     * @param \ANTLR\v4\Runtime\TokenSource $tokenSource the token source
     * @param int $channel the channel to use for filtering tokens
     */
    public function __construct(TokenSource $tokenSource, int $channel = Token::DEFAULT_CHANNEL)
    {
        parent::__construct($tokenSource);

        $this->channel = $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function LT(int $k): ?Token
    {
        $this->lazyInit();

        if ($k == 0) {
            return null;
        }

        if ($k < 0) {
            return $this->LB(-$k);
        }

        $i = $this->p;
        $n = 1; // we know tokens[p] is a good one

        // find k good tokens
        while ($n < $k) {
            // skip off-channel tokens, but make sure to not look past EOF
            if ($this->sync($i + 1)) {
                $i = $this->nextTokenOnChannel($i + 1, $this->channel);
            }

            $n++;
        }

        return $this->tokens[$i];
    }

    public function getNumberOfOnChannelTokens(): int
    {
        $n = 0;

        $this->fill();
        foreach ($this->tokens as $token) {
            if ($token->getChannel() === $this->channel) {
                $n++;
            }

            if ($token->getType() === Token::EOF) {
                break;
            }
        }

        return $n;
    }

    /**
     * {@inheritdoc}
     */
    protected function adjustSeekIndex(int $i): int
    {
        return $this->nextTokenOnChannel($i, $this->channel);
    }

    /**
     * {@inheritdoc}
     */
    protected function LB(int $k): ?Token
    {
        if ($k === 0 || ($this->p - $k) < 0) {
            return null;
        }

        $i = $this->p;
        $n = 1;

        // find k good tokens looking backwards
        while ($n <= $k && $i > 0) {
            // skip off-channel tokens
            $i = $this->previousTokenOnChannel($i - 1, $this->channel);
            $n++;
        }

        if ($i < 0) {
            return null;
        }

        return $this->tokens[$i];
    }
}
