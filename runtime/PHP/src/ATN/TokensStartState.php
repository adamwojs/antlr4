<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * The Tokens rule start state linking to each lexer rule start state.
 */
final class TokensStartState extends DecisionState
{
    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::TOKEN_START;
    }
}
