<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Misc\Interval;
use ANTLR\v4\Runtime\Misc\Pair;

class CommonToken extends BaseObject implements WritableToken
{
    /**
     * This is the backing field for {@link #getType} and {@link #setType}.
     *
     * @var int
     */
    protected $type = Token::INVALID_TYPE;

    /**
     * This is the backing field for {@link #getLine} and {@link #setLine}.
     *
     * @var int
     */
    protected $line = -1;

    /**
     * This is the backing field for {@link #getCharPositionInLine} and
     * {@link #setCharPositionInLine}.
     *
     * @var int
     */
    protected $charPositionInLine = -1;

    /**
     * This is the backing field for {@link #getChannel} and
     * {@link #setChannel}.
     *
     * @var int
     */
    protected $channel = self::DEFAULT_CHANNEL;

    /**
     * This is the backing field for {@link #getTokenSource} and
     * {@link #getInputStream}.
     *
     * <p>
     * These properties share a field to reduce the memory footprint of
     * {@link CommonToken}. Tokens created by a {@link CommonTokenFactory} from
     * the same source and input stream share a reference to the same
     * {@link Pair} containing these values.</p>
     *
     * @var \ANTLR\v4\Runtime\Misc\Pair
     */
    protected $source = null;

    /**
     * This is the backing field for {@link #getText} when the token text is
     * explicitly set in the constructor or via {@link #setText}.
     *
     * @see #getText()
     *
     * @var string
     */
    protected $text = null;

    /**
     * This is the backing field for {@link #getTokenIndex} and
     * {@link #setTokenIndex}.
     *
     * @var int
     */
    protected $index = -1;

    /**
     * This is the backing field for {@link #getStartIndex} and
     * {@link #setStartIndex}.
     *
     * @var int
     */
    protected $start = -1;

    /**
     * This is the backing field for {@link #getStopIndex} and
     * {@link #setStopIndex}.
     *
     * @var int
     */
    protected $stop = -1;

    /**
     * Constructs a new {@link CommonToken} with the specified token type (optional),
     * text (optional) and source.
     *
     * @param int $type the token type
     * @param string|null $text the text of the token
     * @param \ANTLR\v4\Runtime\Misc\Pair|null $source
     */
    public function __construct(int $type = Token::INVALID_TYPE, ?string $text = null, Pair $source = null)
    {
        $this->type = $type;
        $this->text = $text;
        $this->source = $source ?? $this->getEmptySource();
    }

    /**
     * {@inheritdoc}
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * {@inheritdoc}
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    /**
     * {@inheritdoc}
     */
    public function getText(): ?string
    {
        if ($this->text !== null) {
            return $this->text;
        }

        $input = $this->getInputStream();
        if ($input !== null) {
            $n = $input->size();
            if ($this->start < $n && $this->stop < $n) {
                return $input->getText(Interval::of($this->start, $this->stop));
            } else {
                return '<EOF>';
            }
        }

        return null;
    }

    /**
     * Explicitly set the text for this token. If {code text} is not
     * {@code null}, then {@link #getText} will return this value rather than
     * extracting the text from the input.
     *
     * @param string|null $text the explicit text of the token, or {@code null} if the text
     * should be obtained from the input along with the start and stop indexes
     * of the token
     */
    public function setText(?string $text): void
    {
        $this->text = $text;
    }

    /**
     * {@inheritdoc}
     */
    public function getLine(): int
    {
        return $this->line;
    }

    /**
     * {@inheritdoc}
     */
    public function setLine(int $line): void
    {
        $this->line = $line;
    }

    /**
     * {@inheritdoc}
     */
    public function getCharPositionInLine(): int
    {
        return $this->charPositionInLine;
    }

    /**
     * {@inheritdoc}
     */
    public function setCharPositionInLine(int $charPositionInLine): void
    {
        $this->charPositionInLine = $charPositionInLine;
    }

    /**
     * {@inheritdoc}
     */
    public function getChannel(): int
    {
        return $this->channel;
    }

    /**
     * {@inheritdoc}
     */
    public function setChannel(int $channel): void
    {
        $this->channel = $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function getStartIndex(): int
    {
        return $this->start;
    }

    public function setStartIndex(int $start): void
    {
        $this->start = $start;
    }

    /**
     * {@inheritdoc}
     */
    public function getStopIndex(): int
    {
        return $this->stop;
    }

    public function setStopIndex(int $stop): void
    {
        $this->stop = $stop;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenIndex(): int
    {
        return $this->index;
    }

    /**
     * {@inheritdoc}
     */
    public function setTokenIndex(int $index): void
    {
        $this->index = $index;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenSource(): TokenSource
    {
        return $this->source->a;
    }

    /**
     * {@inheritdoc}
     */
    public function getInputStream(): CharStream
    {
        return $this->source->b;
    }

    public function toString(Recognizer $recognizer = null): string
    {
        $channel = '';
        if ($this->channel > 0) {
            $channel = ", channel={$this->channel}";
        }

        $text = strtr($this->getText() ?? '<no text>', [
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
        ]);

        $type = (string)$this->type;
        if ($recognizer !== null) {
            $type = $recognizer->getVocabulary()->getDisplayName($this->type);
        }

        return vsprintf("[@%d,%d:%d='%s',<%s>%s,%d:%d]", [
            $this->getTokenIndex(),
            $this->getStartIndex(),
            $this->getStopIndex(),
            $text,
            $type,
            $channel,
            $this->getLine(),
            $this->getCharPositionInLine(),
        ]);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Constructs a new {@link CommonToken} as a copy of another {@link Token}.
     *
     * <p>
     * If {@code source} is also a {@link CommonToken} instance, the newly
     * constructed token will share a reference to the {@link #text} field and
     * the {@link Pair} stored in {@link #source}. Otherwise, {@link #text} will
     * be assigned the result of calling {@link #getText}, and {@link #source}
     * will be constructed from the result of {@link Token#getTokenSource} and
     * {@link Token#getInputStream}.</p>
     *
     * @param \ANTLR\v4\Runtime\Token $source the token to copy
     *
     * @return \ANTLR\v4\Runtime\CommonToken
     */
    public static function copy(Token $source): self
    {
        $token = new self();
        $token->type = $source->getType();
        $token->line = $source->getLine();
        $token->index = $source->getTokenIndex();
        $token->charPositionInLine = $source->getCharPositionInLine();
        $token->channel = $source->getChannel();
        $token->start = $source->getStartIndex();
        $token->stop = $source->getStopIndex();

        if ($source instanceof self) {
            $token->text = $source->text;
            $token->source = $source->source;
        } else {
            $token->text = $token->getText();
            $token->source = new Pair($source->getTokenSource(), $source->getInputStream());
        }

        return $token;
    }

    /**
     * An empty {@link Pair} which is used as the default value of
     * {@link #source} for tokens that do not have a source.
     *
     * @return \ANTLR\v4\Runtime\Misc\Pair
     */
    protected static function getEmptySource(): Pair
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Pair(null, null);
        }

        return $instance;
    }
}
