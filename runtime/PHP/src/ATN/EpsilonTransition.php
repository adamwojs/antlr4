<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class EpsilonTransition extends Transition
{
    /** @var int */
    private $outermostPrecedenceReturn;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     * @param int $outermostPrecedenceReturn
     */
    public function __construct(ATNState $target, int $outermostPrecedenceReturn = -1)
    {
        parent::__construct($target);

        $this->outermostPrecedenceReturn = $outermostPrecedenceReturn;
    }

    /**
     * @return int the rule index of a precedence rule for which this transition is
     * returning from, where the precedence value is 0; otherwise, -1.
     *
     * @see ATNConfig#isPrecedenceFilterSuppressed()
     * @see ParserATNSimulator#applyPrecedenceFilter(ATNConfigSet)
     * @since 4.4.1
     */
    public function outermostPrecedenceReturn(): int
    {
        return $this->outermostPrecedenceReturn;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::EPSILON;
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

    public function __toString(): string
    {
        return "epsilon";
    }
}
