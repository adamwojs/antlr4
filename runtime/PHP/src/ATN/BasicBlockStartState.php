<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class BasicBlockStartState extends BlockStartState
{
    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::BLOCK_START;
    }
}
