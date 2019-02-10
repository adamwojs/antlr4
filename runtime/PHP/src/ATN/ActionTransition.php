<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class ActionTransition extends Transition
{
    /** @var int */
    public $ruleIndex;

    /** @var int */
    public $actionIndex;

    /**
     * e.g., $i ref in action
     *
     * @var bool
     */
    public $isCtxDependent;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     * @param int $ruleIndex
     * @param int $actionIndex
     * @param bool $isCtxDependent
     */
    public function __construct(ATNState $target, int $ruleIndex, int $actionIndex = -1, bool $isCtxDependent = false)
    {
        parent::__construct($target);

        $this->ruleIndex = $ruleIndex;
        $this->actionIndex = $actionIndex;
        $this->isCtxDependent = $isCtxDependent;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::ACTION;
    }

    /**
     * {@inheritdoc}
     */
    public function isEpsilon(): bool
    {
        return true; // we are to be ignored by analysis 'cept for predicates
    }

    /**
     * {@inheritdoc}
     */
    public function matches(int $symbol, int $minVocabSymbol, int $maxVocabSymbol): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return "action_{$this->ruleIndex}:{$this->actionIndex}";
    }
}
