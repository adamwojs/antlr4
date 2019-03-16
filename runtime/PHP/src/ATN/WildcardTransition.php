<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class WildcardTransition extends Transition
{
    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     */
    public function __construct(ATNState $target)
    {
        parent::__construct($target);
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::WILDCARD;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(int $symbol, int $minVocabSymbol, int $maxVocabSymbol): bool
    {
        return $symbol >= $minVocabSymbol && $symbol <= $maxVocabSymbol;
    }

    public function __toString(): string
    {
        return '.';
    }
}
