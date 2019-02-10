<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\Misc\IntervalSet;
use ANTLR\v4\Runtime\Token;

/**
 * A transition containing a set of values.
 */
class SetTransition extends Transition
{
    /** @var \ANTLR\v4\Runtime\Misc\IntervalSet */
    public $set;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     * @param \ANTLR\v4\Runtime\Misc\IntervalSet $set
     */
    public function __construct(ATNState $target, ?IntervalSet $set)
    {
        parent::__construct($target);

        if ($set === null) {
            $set = IntervalSet::of(Token::INVALID_TYPE);
        }

        $this->set = $set;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::SET;
    }

    /**
     * {@inheritdoc}
     */
    public function label(): ?IntervalSet
    {
        return $this->set;
    }

    /**
     * {@inheritdoc}
     */
    public function matches(int $symbol, int $minVocabSymbol, int $maxVocabSymbol): bool
    {
        return $this->set->contains($symbol);
    }

    public function __toString(): string
    {
        return (string)$this->set;
    }
}
