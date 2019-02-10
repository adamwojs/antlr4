<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

final class LexerPushModeAction extends BaseObject implements LexerAction
{
    /** @var int */
    private $mode;

    /**
     * Constructs a new {@code pushMode} action with the specified mode value.
     *
     * @param int $mode The mode value to pass to {@link Lexer#pushMode}.
     */
    public function __construct(int $mode)
    {
        $this->mode = $mode;
    }

    /**
     * Get the lexer mode this action should transition the lexer to.
     *
     * @return int The lexer mode for this {@code pushMode} command.
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
        return LexerActionType::PUSH_MODE;
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
     * <p>This action is implemented by calling {@link Lexer#pushMode} with the
     * value provided by {@link #getMode}.</p>
     */
    public function execute(Lexer $lexer): void
    {
        $lexer->pushMode($this->mode);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof LexerPushModeAction) {
            return $o->getMode() === $this->getMode();
        }

        return false;
    }

    public function __toString(): string
    {
        return "pushMode({$this->mode})";
    }
}
