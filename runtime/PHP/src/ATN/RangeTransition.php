<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\Misc\IntervalSet;

final class RangeTransition extends Transition
{
    /** @var int */
    private $from;

    /** @var int */
    private $to;

    public function __construct(ATNState $target, int $from, int $to)
    {
        parent::__construct($target);

        $this->from = $from;
        $this->to = $to;
    }

    /**
     * {@inheritdoc}
     */
    public function getSerializationType(): int
    {
        return self::RANGE;
    }

    /**
     * {@inheritdoc}
     */
    public function label(): ?IntervalSet
    {
        return IntervalSet::of($this->from, $this->to);
    }

    /**
     * {@inheritdoc}
     */
    public function matches(int $symbol, int $minVocabSymbol, int $maxVocabSymbol): bool
    {
        return $symbol >= $this->from && $symbol <= $this->to;
    }

    public function __toString(): string
    {
        // TODO: Convert $this->from and $this->>to into code points
        return "'{$this->from}..{$this->to}'";
    }
}
