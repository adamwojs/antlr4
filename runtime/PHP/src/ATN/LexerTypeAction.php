<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

/**
 * Implements the {@code type} lexer action by calling {@link Lexer#setType}
 * with the assigned type.
 */
final class LexerTypeAction extends BaseObject implements LexerAction
{
    /** @var int */
    private $type;

    /**
     * Constructs a new {@code type} action with the specified token type value.
     *
     * @param int $type the type to assign to the token using {@link Lexer#setType}
     */
    public function __construct(int $type)
    {
        $this->type = $type;
    }

    /**
     * Gets the type to assign to a token created by the lexer.
     *
     * @return int the type to assign to a token created by the lexer
     */
    public function getType(): int
    {
        return $this->type;
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
     * <p>This action is implemented by calling {@link Lexer#setType} with the
     * value provided by {@link #getType}.</p>
     */
    public function execute(Lexer $lexer): void
    {
        $lexer->setType($this->type);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof self) {
            return $o->getType() === $this->getActionType();
        }

        return false;
    }

    public function __toString(): string
    {
        return "type({$this->type})";
    }
}
