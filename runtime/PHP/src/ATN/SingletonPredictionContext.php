<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\Misc\MurmurHash;

class SingletonPredictionContext extends PredictionContext
{
    /** @var int */
    private const INITIAL_HASH = 1;

    /** @var \ANTLR\v4\Runtime\ATN\PredictionContext|null */
    public $parent;

    /** @var int */
    public $returnState;

    /**
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext|null $parent
     * @param int $returnState
     */
    public function __construct(?PredictionContext $parent, int $returnState)
    {
        parent::__construct($parent !== null ? $this->calculateHashCode($parent, $returnState) : $this->calculateEmptyHashCode());

        $this->parent = $parent;
        $this->returnState = $returnState;
    }

    public static function create(?PredictionContext $parent, int $returnState): self
    {
        if ($returnState === self::EMPTY_RETURN_STATE && $parent === null) {
            // someone can pass in the bits of an array ctx that mean $
            return parent::createEmpty();
        }

        return new self($parent, $returnState);
    }

    /**
     * {@inheritdoc}
     */
    public function size(): int
    {
        return 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(int $index): ?PredictionContext
    {
        assert($index === 0);
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnState(int $index): int
    {
        assert($index === 0);
        return $this->returnState;
    }
    
    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($this === $o) {
            return true;
        }

        if ($o instanceof SingletonPredictionContext) {
            if ($this->hash() !== $o->hash()) {
                // can't be same if hash is different
                return false;
            }

            return $this->returnState === $o->returnState
                && ($this->parent !== null && $this->parent->equals($o->parent));
        }

        return false;
    }

    public function asArrayPredicationContext(): ArrayPredictionContext
    {
        return new ArrayPredictionContext([$this->parent], [$this->returnState]);
    }
    
    public function __toString(): string
    {
        if ($this->parent === null) {
            if ($this->returnState === self::EMPTY_RETURN_STATE) {
                return "$";
            }

            return (string)$this->returnState;
        }

        return (string)$this->returnState . ' ' . (string)$this->parent;
    }

    private function calculateEmptyHashCode(): int
    {
        $hash = MurmurHash::initialize(self::INITIAL_HASH);
        $hash = MurmurHash::finish($hash, 0);

        return $hash;
    }

    private function calculateHashCode(PredictionContext $parent, int $returnState): int
    {
        $hash = MurmurHash::initialize(self::INITIAL_HASH);
        $hash = MurmurHash::update($hash, $parent->hash());
        $hash = MurmurHash::update($hash, $returnState);
        $hash = MurmurHash::finish($hash, 2);

        return $hash;
    }
}
