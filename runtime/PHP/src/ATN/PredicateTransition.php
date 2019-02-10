<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\ATN\SemanticContext\Predicate;

final class PredicateTransition extends AbstractPredicateTransition
{
    /** @var int */
    public $ruleIndex;

    /** @var int */
    public $predIndex;

    /**
     * e.g., $i ref in pred
     *
     * @var bool
     */
    public $isCtxDependent;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     * @param int $ruleIndex
     * @param int $predIndex
     * @param bool $isCtxDependent
     */
    public function __construct(ATNState $target, int $ruleIndex, int $predIndex, bool $isCtxDependent)
    {
        parent::__construct($target);

        $this->ruleIndex = $ruleIndex;
        $this->predIndex = $predIndex;
        $this->isCtxDependent = $isCtxDependent;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::PREDICATE;
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

    public function getPredicate(): Predicate
    {
        return new Predicate($this->ruleIndex, $this->predIndex, $this->isCtxDependent);
    }

    public function __toString(): string
    {
        return "pref_{$this->ruleIndex}:{$this->predIndex}";
    }
}
