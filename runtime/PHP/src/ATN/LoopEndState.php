<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * Mark the end of a * or + loop.
 */
final class LoopEndState extends ATNState
{
    /** @var \ANTLR\v4\Runtime\ATN\ATNState */
    public $loopBackState;

    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::LOOP_END;
    }
}
