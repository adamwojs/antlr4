<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class NotSetTransition extends SetTransition
{
    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::NOT_SET;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(int $symbol, int $minVocabSymbol, int $maxVocabSymbol): bool
    {
        return $symbol >= $minVocabSymbol
            && $symbol <= $maxVocabSymbol
            && !parent::matches($symbol, $minVocabSymbol, $maxVocabSymbol);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return '~' . parent::__toString();
    }
}
