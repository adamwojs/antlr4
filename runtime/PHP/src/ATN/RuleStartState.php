<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class RuleStartState extends ATNState
{
    /** @var \ANTLR\v4\Runtime\ATN\RuleStopState */
    public $stopState;

    /** @var bool */
    public $isLeftRecursiveRule;

    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::RULE_START;
    }
}
