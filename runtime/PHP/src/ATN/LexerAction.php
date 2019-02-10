<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\Lexer;
use Ds\Hashable;

interface LexerAction extends Hashable
{
    /**
     * Gets the serialization type of the lexer action.
     *
     * @see \ANTLR\v4\Runtime\ATN\LexerActionType
     *
     * @return int The serialization type of the lexer action.
     */
    public function getActionType(): int;

    /**
     * Gets whether the lexer action is position-dependent. Position-dependent
     * actions may have different semantics depending on the {@link CharStream}
     * index at the time the action is executed.
     *
     * <p>Many lexer commands, including {@code type}, {@code skip}, and
     * {@code more}, do not check the input index during their execution.
     * Actions like this are position-independent, and may be stored more
     * efficiently as part of the {@link LexerATNConfig#lexerActionExecutor}.</p>
     *
     * @return bool {@code true} if the lexer action semantics can be affected by the
     * position of the input {@link CharStream} at the time it is executed;
     * otherwise, {@code false}.
     */
    public function isPositionDependent(): bool;

    /**
     * Execute the lexer action in the context of the specified {@link Lexer}.
     *
     * <p>For position-dependent actions, the input stream must already be
     * positioned correctly prior to calling this method.</p>
     *
     * @param \ANTLR\v4\Runtime\Lexer lexer The lexer instance.
     */
    public function execute(Lexer $lexer): void;
}
