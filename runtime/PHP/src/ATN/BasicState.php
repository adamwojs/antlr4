<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class BasicState extends ATNState
{
    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::BASIC;
    }
}
