<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

/**
 * Implements the {@code skip} lexer action by calling {@link Lexer#skip}.
 *
 * <p>The {@code skip} command does not have any parameters, so this action is
 * implemented as a singleton instance exposed by {@link #INSTANCE}.</p>
 */
final class LexerSkipAction extends BaseObject implements LexerAction
{
    /**
     * Provides a singleton instance of this parameterless lexer action.
     *
     * @var \ANTLR\v4\Runtime\ATN\LexerSkipAction
     */
    private static $instance = null;

    /**
     * Constructs the singleton instance of the lexer {@code skip} command.
     */
    private function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getActionType(): int
    {
        return LexerActionType::SKIP;
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
     * <p>This action is implemented by calling {@link Lexer#skip}.</p>
     */
    public function execute(Lexer $lexer): void
    {
        $lexer->skip();
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        return $this === $o;
    }

    public function __toString(): string
    {
        return "skip";
    }

    public static function getInstance(): LexerSkipAction
    {
        if (self::$instance === null) {
            self::$instance = new LexerSkipAction();
        }

        return self::$instance;
    }
}
