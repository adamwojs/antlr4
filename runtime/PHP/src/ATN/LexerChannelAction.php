<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

/**
 * Implements the {@code channel} lexer action by calling
 * {@link Lexer#setChannel} with the assigned channel.
 */
final class LexerChannelAction extends BaseObject implements LexerAction
{
    /** @var int */
    private $channel;

    /**
     * Constructs a new {@code channel} action with the specified channel value.
     *
     * @param int $channel The channel value to pass to {@link Lexer#setChannel}.
     */
    public function __construct(int $channel)
    {
        $this->channel = $channel;
    }

    /**
     * Gets the channel to use for the {@link Token} created by the lexer.
     *
     * @return int The channel to use for the {@link Token} created by the lexer.
     */
    public function getChannel(): int
    {
        return $this->channel;
    }

    /**
     * {@inheritdoc}
     */
    public function getActionType(): int
    {
        return LexerActionType::CHANNEL;
    }

    /**
     * {@inheritdoc}
     */
    public function isPositionDependent(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(Lexer $lexer): void
    {
        $lexer->setChannel($this->channel);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof LexerChannelAction) {
            return $this->channel === $o->channel;
        }

        return false;
    }

    public function __toString(): string
    {
        return "channel({$this->channel})";
    }
}
