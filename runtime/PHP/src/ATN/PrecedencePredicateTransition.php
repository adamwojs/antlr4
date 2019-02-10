<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\ATN\SemanticContext\PrecedencePredicate;

final class PrecedencePredicateTransition extends AbstractPredicateTransition
{
    /** @var int */
    public $precedence;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     * @param int $precedence
     */
    public function __construct(ATNState $target, int $precedence)
    {
        parent::__construct($target);

        $this->precedence = $precedence;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::PRECEDENCE;
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

    public function getPredicate(): PrecedencePredicate
    {
        return new PrecedencePredicate($this->precedence);
    }

    public function __toString(): string
    {
        return "{$this->precedence} >= _p";
    }
}
