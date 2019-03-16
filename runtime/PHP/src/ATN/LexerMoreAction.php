<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

/**
 * Implements the {@code more} lexer action by calling {@link Lexer#more}.
 *
 * <p>The {@code more} command does not have any parameters, so this action is
 * implemented as a singleton instance exposed by {@link #INSTANCE}.</p>
 */
final class LexerMoreAction extends BaseObject implements LexerAction
{
    /**
     * Provides a singleton instance of this parameterless lexer action.
     *
     * @var \ANTLR\v4\Runtime\ATN\LexerMoreAction
     */
    private static $instance = null;

    /**
     * Constructs the singleton instance of the lexer {@code popMode} command.
     */
    private function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getActionType(): int
    {
        return LexerActionType::MORE;
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
        $lexer->more();
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        return $this === $o;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
