<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class StarLoopbackState extends ATNState
{
    public function getLoopEntryState(): StarLoopEntryState
    {
        return $this->transition(0)->target;
    }

    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::STAR_LOOP_BACK;
    }
}
