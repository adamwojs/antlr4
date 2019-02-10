<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * The start of a regular {@code (...)} block.
 */
abstract class BlockStartState extends DecisionState
{
    /** @var \ANTLR\v4\Runtime\ATN\BlockEndState */
    public $endState;
}
