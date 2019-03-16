<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

/**
 * Implements the {@code popMode} lexer action by calling {@link Lexer#popMode}.
 *
 * <p>The {@code popMode} command does not have any parameters, so this action is
 * implemented as a singleton instance exposed by {@link #INSTANCE}.</p>
 */
final class LexerPopModeAction extends BaseObject implements LexerAction
{
    /**
     * Provides a singleton instance of this parameterless lexer action.
     *
     * @var \ANTLR\v4\Runtime\ATN\LexerPopModeAction
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
        return LexerActionType::POP_MODE;
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
        $lexer->popMode();
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
