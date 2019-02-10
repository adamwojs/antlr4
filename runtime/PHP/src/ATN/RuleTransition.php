<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class RuleTransition extends Transition
{
    /**
     * Ptr to the rule definition object for this rule ref
     *
     * @var int
     */
    public $ruleIndex;

    /** @var int */
    public $precedence;

    /**
     * What node to begin computations following ref to rule
     *
     * @var \ANTLR\v4\Runtime\ATN\ATNState
     */
    public $followState;

    /**
     * @param \ANTLR\v4\Runtime\ATN\RuleStartState $ruleStart
     * @param int $ruleIndex
     * @param int $precedence
     * @param \ANTLR\v4\Runtime\ATN\ATNState $followState
     */
    public function __construct(RuleStartState $ruleStart, int $ruleIndex, int $precedence, ATNState $followState)
    {
        parent::__construct($ruleStart);

        $this->ruleIndex = $ruleIndex;
        $this->precedence = $precedence;
        $this->followState = $followState;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::RULE;
    }

    /**
     * {@inheritdoc}
     */
    public function isEpsilon(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(int $symbol, int $minVocabSymbol, int $maxVocabSymbol): bool
    {
        return false;
    }
}
