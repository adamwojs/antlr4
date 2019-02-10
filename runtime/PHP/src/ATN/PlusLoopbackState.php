<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * Decision state for {@code A+} and {@code (A|B)+}.  It has two transitions:
 * one to the loop back to start of the block and one to exit.
 */
final class PlusLoopbackState extends DecisionState
{
    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::PLUS_LOOP_BACK;
    }
}
