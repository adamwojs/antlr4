<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * The block that begins a closure loop.
 */
final class StarBlockStartState extends BlockStartState
{
    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::STAR_BLOCK_START;
    }
}
