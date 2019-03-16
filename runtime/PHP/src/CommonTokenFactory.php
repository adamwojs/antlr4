<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Misc\Interval;
use ANTLR\v4\Runtime\Misc\Pair;

/**
 * This default implementation of {@link TokenFactory} creates
 * {@link CommonToken} objects.
 */
class CommonTokenFactory implements TokenFactory
{
    /**
     * Indicates whether {@link CommonToken#setText} should be called after
     * constructing tokens to explicitly set the text. This is useful for cases
     * where the input stream might not be able to provide arbitrary substrings
     * of text from the input after the lexer creates a token (e.g. the
     * implementation of {@link CharStream#getText} in
     * {@link UnbufferedCharStream} throws an
     * {@link UnsupportedOperationException}). Explicitly setting the token text
     * allows {@link Token#getText} to be called at any time regardless of the
     * input stream implementation.
     *
     * <p>The default value is {@code false} to avoid the performance and memory
     * overhead of copying text for every token unless explicitly requested.</p>
     *
     * @var bool
     */
    protected $copyText;

    /**
     * Constructs a {@link CommonTokenFactory} with the specified value for
     * {@link #copyText}.
     *
     * <p>
     * When {@code copyText} is {@code false}, the {@link #DEFAULT} instance
     * should be used instead of constructing a new instance.</p>
     *
     * @param bool $copyText the value for {@link #copyText}
     */
    public function __construct(bool $copyText = false)
    {
        $this->copyText = $copyText;
    }

    /**
     * {@inheritdoc}
     */
    public function create(
        Pair $source,
        int $type,
        ?string $text,
        int $channel,
        int $start,
        int $stop,
        int $line,
        int $charPositionInLine
    ): Token {
        $token = new CommonToken($type, null, $source);
        $token->setChannel($channel);
        $token->setStartIndex($start);
        $token->setStopIndex($stop);
        $token->setLine($line);
        $token->setCharPositionInLine($charPositionInLine);
        if ($text !== null) {
            $token->setText($text);
        } elseif ($this->copyText && $source->b !== null) {
            $token->setText($source->b->getText(Interval::of($start, $stop)));
        }

        return $token;
    }

    /**
     * {@inheritdoc}
     */
    public function createWithTypeAndText(int $type, ?string $text): Token
    {
        return new CommonToken($type, $text);
    }

    /**
     * Return the default {@link CommonTokenFactory} instance.
     *
     * <p>This token factory does not explicitly copy token text when constructing
     * tokens.</p>
     *
     * @return \ANTLR\v4\Runtime\CommonTokenFactory
     */
    public static function getDefault(): self
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }
}
