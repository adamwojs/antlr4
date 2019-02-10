<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

/**
 * Implements the {@code mode} lexer action by calling {@link Lexer#mode} with
 * the assigned mode.
 */
final class LexerModeAction extends BaseObject implements LexerAction
{
    /** @var int */
    private $mode;

    /**
     * Constructs a new {@code mode} action with the specified mode value.
     *
     * @param int $mode The mode value to pass to {@link Lexer#mode}.
     */
    public function __construct(int $mode)
    {
        $this->mode = $mode;
    }

    /**
     * Get the lexer mode this action should transition the lexer to.
     *
     * @return int The lexer mode for this {@code mode} command.
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * {@inheritdoc}
     */
    public function getActionType(): int
    {
        return LexerActionType::MODE;
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
     *
     * <p>This action is implemented by calling {@link Lexer#mode} with the
     * value provided by {@link #getMode}.</p>
     */
    public function execute(Lexer $lexer): void
    {
        $lexer->mode($this->mode);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof LexerModeAction) {
            return $o->getMode() === $this->getMode();
        }

        return false;
    }

    public function __toString(): string
    {
        return "mode({$this->mode})";
    }
}
