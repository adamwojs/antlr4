<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * Terminal node of a simple {@code (a|b|c)} block.
 */
final class BlockEndState extends ATNState
{
    /** @var \ANTLR\v4\Runtime\ATN\BlockStartState */
    public $startState;

    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::BLOCK_END;
    }
}
