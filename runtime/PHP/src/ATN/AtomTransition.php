<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\Misc\IntervalSet;

final class AtomTransition extends Transition
{
    /**
     * The token type or character value; or, signifies special label.
     *
     * @var int
     */
    public $label;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     * @param int $label
     */
    public function __construct(ATNState $target, int $label)
    {
        parent::__construct($target);

        $this->label = $label;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::ATOM;
    }

    /**
     * {@inheritdoc}
     */
    public function label(): ?IntervalSet
    {
        return IntervalSet::of($this->label);
    }

    /**
     * {@inheritdoc}
     */
    public function matches(int $symbol, int $minVocabSymbol, int $maxVocabSymbol): bool
    {
        return $this->label === $symbol;
    }

    public function __toString(): string
    {
        return (string) $this->label;
    }
}
