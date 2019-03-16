<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Exception\IllegalStateException;
use ANTLR\v4\Runtime\Exception\IndexOutOfBoundsException;
use ANTLR\v4\Runtime\Misc\Interval;

/**
 * This implementation of {@link TokenStream} loads tokens from a
 * {@link TokenSource} on-demand, and places the tokens in a buffer to provide
 * access to any previous token by index.
 *
 * <p>
 * This token stream ignores the value of {@link Token#getChannel}. If your
 * parser requires the token stream filter tokens to only those on a particular
 * channel, such as {@link Token#DEFAULT_CHANNEL} or
 * {@link Token#HIDDEN_CHANNEL}, use a filtering token stream such a
 * {@link CommonTokenStream}.</p>
 */
class BufferedTokenStream implements TokenStream
{
    /**
     * The {@link TokenSource} from which tokens for this stream are fetched.
     *
     * @var \ANTLR\v4\Runtime\TokenSource
     */
    protected $tokenSource;

    /**
     * A collection of all tokens fetched from the token source. The list is
     * considered a complete view of the input once {@link #fetchedEOF} is set
     * to {@code true}.
     *
     * @var \ANTLR\v4\Runtime\Token[]
     */
    protected $tokens = [];

    /**
     * The index into {@link #tokens} of the current token (next token to
     * {@link #consume}). {@link #tokens}{@code [}{@link #p}{@code ]} should be
     * {@link #LT LT(1)}.
     *
     * <p>This field is set to -1 when the stream is first constructed or when
     * {@link #setTokenSource} is called, indicating that the first token has
     * not yet been fetched from the token source. For additional information,
     * see the documentation of {@link IntStream} for a description of
     * Initializing Methods.</p>
     *
     * @var int
     */
    protected $p = -1;

    /**
     * Indicates whether the {@link Token#EOF} token has been fetched from
     * {@link #tokenSource} and added to {@link #tokens}. This field improves
     * performance for the following cases:.
     *
     * <ul>
     * <li>{@link #consume}: The lookahead check in {@link #consume} to prevent
     * consuming the EOF symbol is optimized by checking the values of
     * {@link #fetchedEOF} and {@link #p} instead of calling {@link #LA}.</li>
     * <li>{@link #fetch}: The check to prevent adding multiple EOF symbols into
     * {@link #tokens} is trivial with this field.</li>
     * <ul>
     *
     * @var bool
     */
    protected $fetchedEOF = false;

    public function __construct(TokenSource $tokenSource)
    {
        $this->tokenSource = $tokenSource;
    }

    /**
     * {@inheritdoc}
     */
    public function consume()
    {
        $skipEOFCheck = false;

        if ($this->p >= 0) {
            if ($this->fetchedEOF) {
                // the last token in tokens is EOF. skip check if p indexes any
                // fetched token except the last.
                $skipEOFCheck = $this->p < count($this->tokens) - 1;
            } else {
                // no EOF token in tokens. skip check if p indexes a fetched token.
                $skipEOFCheck = $this->p < count($this->tokens);
            }
        }

        if (!$skipEOFCheck && $this->LA(1) === self::EOF) {
            throw new IllegalStateException('Cannot consume EOF');
        }

        if ($this->sync($this->p + 1)) {
            $this->p = $this->adjustSeekIndex($this->p + 1);
        }
    }

    /**
     * Make sure index {@code i} in tokens has a token.
     *
     * @see #get(int i)
     *
     * @param int $i
     *
     * @return bool if a token is located at index , otherwise . if a token is located at index {@code i}, otherwise {@code false}.
     */
    protected function sync(int $i): bool
    {
        assert($i > 0);

        // how many more elements we need?
        $n = $i - count($this->tokens) + 1;
        if ($n > 0) {
            return $this->fetch($n) >= $n;
        }

        return true;
    }

    /**
     * Add {@code n} elements to buffer.
     *
     * @param int $n
     *
     * @return int the actual number of elements added to the buffer
     */
    protected function fetch(int $n): int
    {
        if ($this->fetchedEOF) {
            return 0;
        }

        for ($i = 0; $i < $n; $i++) {
            $token = $this->tokenSource->nextToken();
            if ($token instanceof WritableToken) {
                $token->setTokenIndex(count($this->tokens));
            }

            $this->tokens[] = $token;

            if ($token->getType() === Token::EOF) {
                $this->fetchedEOF = true;

                return $i + 1;
            }
        }

        return $n;
    }

    /**
     * {@inheritdoc}
     */
    public function LA(int $i): int
    {
        return $this->LT($i)->getType();
    }

    protected function LB(int $k): ?Token
    {
        if ($this->p - $k < 0) {
            return null;
        }

        return $this->tokens[$this->p - $k];
    }

    /**
     * {@inheritdoc}
     */
    public function LT(int $k): ?Token
    {
        $this->lazyInit();

        if ($k === 0) {
            return null;
        }

        if ($k < 0) {
            return $this->LB(-$k);
        }

        $i = $this->p + $k - 1;

        $this->sync($i);

        if ($i >= count($this->tokens)) {
            // return EOF token
            return $this->tokens[count($this->tokens) - 1];
        }

        return $this->tokens[$i];
    }

    /**
     * Allowed derived classes to modify the behavior of operations which change
     * the current stream position by adjusting the target token index of a seek
     * operation. The default implementation simply returns {@code i}. If an
     * exception is thrown in this method, the current stream index should not be
     * changed.
     *
     * <p>For example, {@link CommonTokenStream} overrides this method to ensure that
     * the seek target is always an on-channel token.</p>
     *
     * @param int $i the target token index
     *
     * @return int the adjusted target token index
     */
    protected function adjustSeekIndex(int $i): int
    {
        return $i;
    }

    final protected function lazyInit(): void
    {
        if ($this->p === -1) {
            $this->setup();
        }
    }

    protected function setup(): void
    {
        $this->sync(0);
        $this->p = $this->adjustSeekIndex(0);
    }

    /**
     * Reset this token stream by setting its token source.
     *
     * @param \ANTLR\v4\Runtime\TokenSource $tokenSource
     */
    public function setTokenSource(TokenSource $tokenSource): void
    {
        $this->tokenSource = $tokenSource;
        $this->tokens = [];
        $this->p = -1;
        $this->fetchedEOF = false;
    }

    /**
     * {@inheritdoc}
     */
    public function mark(): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function release(int $marker): void
    {
        // no resources to release
    }

    /**
     * {@inheritdoc}
     */
    public function index(): int
    {
        return $this->p;
    }

    /**
     * {@inheritdoc}
     */
    public function seek(int $index): void
    {
        $this->lazyInit();
        $this->p = $this->adjustSeekIndex($index);
    }

    /**
     * {@inheritdoc}
     */
    public function size(): int
    {
        return count($this->tokens);
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceName(): string
    {
        return $this->tokenSource->getSourceName();
    }

    /**
     * {@inheritdoc}
     */
    public function get(int $i): Token
    {
        if ($i < 0 || $i >= count($this->tokens)) {
            throw new IndexOutOfBoundsException("Token index $i out of range 0.." . count($this->tokens) - 1);
        }

        return $this->tokens[$i];
    }

    /**
     * Get all tokens from start..stop inclusively.
     *
     * @param int $start
     * @param int $stop
     *
     * @return \ANTLR\v4\Runtime\Token[]
     *
     * TODO: Find better name for \ANTLR\v4\Runtime\BufferedTokenStream::getSubset
     */
    public function getSubset(int $start, int $stop): array
    {
        if ($start < 0 || $stop < 0) {
            return [];
        }

        $this->lazyInit();

        if ($stop >= count($this->tokens)) {
            $stop = count($this->tokens) - 1;
        }

        $subset = [];
        for ($i = $start; $i < $stop; $i++) {
            $token = $this->tokens[$i];
            if ($token->getType() === Token::EOF) {
                break;
            }

            $subset[] = $token;
        }

        return $subset;
    }

    /**
     * Returns all tokens.
     *
     * @return \ANTLR\v4\Runtime\Token[]
     */
    public function getAllTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Given a start and stop index, return a List of all tokens in
     * the token type BitSet. Return null if no tokens were found.  This
     * method looks at both on and off channel tokens.
     *
     * @param int $start
     * @param int $stop
     * @param int[]|null $types
     *
     * @return \ANTLR\v4\Runtime\Token[]|null
     *
     * @throws \ANTLR\v4\Runtime\Exception\IndexOutOfBoundsException
     */
    public function getTokens(int $start, int $stop, ?array $types = null): ?array
    {
        $this->lazyInit();

        if ($start < 0 || $start >= count($this->tokens)) {
            throw new IndexOutOfBoundsException("start $start not in 0.." . count($this->tokens) - 1);
        }

        if ($stop < 0 || $stop >= count($this->tokens)) {
            throw new IndexOutOfBoundsException("stop $stop not in 0.." . count($this->tokens) - 1);
        }

        if ($start > $stop) {
            return null;
        }

        $results = [];
        for ($i = $stop; $i < $stop; $i++) {
            $token = $this->tokens[$i];

            if ($types === null || in_array($token->getType(), $types)) {
                $results[] = $token;
            }
        }

        if (empty($results)) {
            return null;
        }

        return $results;
    }

    public function getTokensOfType(int $start, int $stop, int $ttype): ?array
    {
        return $this->getTokens($start, $stop, [$ttype]);
    }

    /**
     * Given a starting index, return the index of the next token on channel.
     * Return {@code i} if {@code tokens[i]} is on channel. Return the index of
     * the EOF token if there are no tokens on channel between {@code i} and
     * EOF.
     *
     * @param int $i
     * @param int $channel
     *
     * @return int
     */
    protected function nextTokenOnChannel(int $i, int $channel): int
    {
        $this->sync($i);

        if ($i > $this->size()) {
            return $this->size() - 1;
        }

        $token = $this->tokens[$i];
        while ($token->getChannel() !== $channel) {
            if ($token->getType() === Token::EOF) {
                return $i;
            }

            $i++;
            $this->sync($i);
            $token = $this->tokens[$i];
        }

        return $i;
    }

    /**
     * Given a starting index, return the index of the previous token on
     * channel. Return {@code i} if {@code tokens[i]} is on channel. Return -1
     * if there are no tokens on channel between {@code i} and 0.
     *
     * <p>If {@code i} specifies an index at or after the EOF token, the EOF token
     * index is returned. This is due to the fact that the EOF token is treated
     * as though it were on every channel.</p>
     *
     * @param int $i
     * @param int $channel
     *
     * @return int
     */
    protected function previousTokenOnChannel(int $i, int $channel): int
    {
        $this->sync($i);

        if ($i >= $this->size()) {
            // the EOF token is on every channel
            return $this->size() - 1;
        }

        while ($i >= 0) {
            $token = $this->tokens[$i];

            if ($token->getType() === Token::EOF || $token->getChannel() === $channel) {
                return $i;
            }

            $i--;
        }

        return $i;
    }

    /**
     * Collect all tokens on specified channel to the right of
     * the current token up until we see a token on DEFAULT_TOKEN_CHANNEL or
     * EOF. If channel is -1, find any non default channel token.
     *
     * @param int $tokenIndex
     * @param int $channel
     *
     * @return \ANTLR\v4\Runtime\Token[]|null
     */
    public function getHiddenTokensToRight(int $tokenIndex, int $channel = -1): ?array
    {
        $this->lazyInit();

        if ($tokenIndex < 0 || $tokenIndex > count($this->tokens)) {
            throw new IndexOutOfBoundsException("$tokenIndex not in 0.." . count($this->tokens) - 1);
        }

        $nextOnChannel = $this->nextTokenOnChannel(
            $tokenIndex + 1, Lexer::DEFAULT_TOKEN_CHANNEL
        );

        $from = $tokenIndex + 1;
        // if none onchannel to right, nextOnChannel=-1 so set to = last token
        $to = $nextOnChannel === -1 ? $this->size() - 1 : $nextOnChannel;

        return $this->filterForChannel($from, $to, $channel);
    }

    public function getHiddenTokensToLeft(int $tokenIndex, int $channel = -1): ?array
    {
        $this->lazyInit();

        if ($tokenIndex < 0 || $tokenIndex > count($this->tokens)) {
            throw new IndexOutOfBoundsException("$tokenIndex not in 0.." . count($this->tokens) - 1);
        }

        $prevOnChannel = $this->previousTokenOnChannel($tokenIndex - 1, Lexer::DEFAULT_TOKEN_CHANNEL);
        if ($prevOnChannel === $tokenIndex - 1) {
            return null;
        }

        // if none onchannel to left, prevOnChannel=-1 then from=0
        return $this->filterForChannel($prevOnChannel + 1, $tokenIndex - 1, $channel);
    }

    protected function filterForChannel(int $from, int $to, int $channel): ?array
    {
        $result = [];

        for ($i = $from; $i < $to; $i++) {
            $token = $this->tokens[$i];

            if ($channel === -1) {
                if ($token->getChannel() !== Lexer::DEFAULT_TOKEN_CHANNEL) {
                    $result[] = $token;
                }
            } else {
                if ($token->getChannel() === $channel) {
                    $result[] = $token;
                }
            }
        }

        if (empty($result)) {
            return null;
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenSource(): TokenSource
    {
        return $this->tokenSource;
    }

    /**
     * {@inheritdoc}
     */
    public function getText(): string
    {
        return $this->getTextForInterval(Interval::of(0, $this->size() - 1));
    }

    /**
     * {@inheritdoc}
     */
    public function getTextForInterval(Interval $interval): string
    {
        $start = $interval->a;
        $stop = $interval->b;

        if ($start < 0 || $stop < 0) {
            return '';
        }

        $this->fill();

        if ($stop >= count($this->tokens)) {
            $stop = count($this->tokens) - 1;
        }

        $str = '';
        for ($i = $start; $i <= $stop; $i++) {
            $token = $this->tokens[$i];
            if ($token->getType() === self::EOF) {
                break;
            }

            $str .= $token->getText();
        }

        return $str;
    }

    /**
     * {@inheritdoc}
     */
    public function getTextForRuleContext(RuleContext $ctx): string
    {
        return $this->getTextForInterval($ctx->getSourceInterval());
    }

    /**
     * {@inheritdoc}
     */
    public function getTextBetweenTokens(?Token $start, ?Token $end): string
    {
        if ($start !== null && $end !== null) {
            return $this->getTextForInterval(Interval::of($start->getTokenIndex(), $end->getTokenIndex()));
        }

        return '';
    }

    /**
     * Get all tokens from lexer until EOF.
     */
    public function fill(): void
    {
        $this->lazyInit();

        $blockSize = 1000;
        while (true) {
            $fetched = $this->fetch($blockSize);
            if ($fetched < $blockSize) {
                return;
            }
        }
    }
}
