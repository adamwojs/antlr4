<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

abstract class DecisionState extends ATNState
{
    /** @var int */
    public $decision = -1;

    /** @var bool */
    public $nonGreedy = false;
}
