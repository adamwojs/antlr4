<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\Misc\MurmurHash;

class ArrayPredictionContext extends PredictionContext
{
    /** @var int */
    private const INITIAL_HASH = 1;

    /**
     * Parent can be null only if full ctx mode and we make an array
     * from {@link #EMPTY} and non-empty. We merge {@link #EMPTY} by using null parent and
     * returnState == {@link #EMPTY_RETURN_STATE}.
     *
     * @var \ANTLR\v4\Runtime\ATN\PredictionContext[]
     */
    public $parents;

    /**
     * Sorted for merge, no duplicates; if present,
     * {@link #EMPTY_RETURN_STATE} is always last.
     *
     * @var int[]
     */
    public $returnStates;

    public function __construct(array $parents, array $returnStates)
    {
        parent::__construct($this->calculateHashCode($parents, $returnStates));

        assert(count($parents) > 0);
        assert(count($returnStates) > 0);

        $this->parents = $parents;
        $this->returnStates = $returnStates;
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        // since EMPTY_RETURN_STATE can only appear in the last position, we
        // don't need to verify that size==1
        return $this->returnStates[0] === self::EMPTY_RETURN_STATE;
    }

    /**
     * {@inheritdoc}
     */
    public function size(): int
    {
        return count($this->returnStates);
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(int $index): ?PredictionContext
    {
        return $this->parents[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnState(int $index): int
    {
        return $this->returnStates[$index];
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($this === $o) {
            return true;
        }

        if ($o instanceof ArrayPredictionContext) {
            if ($this->hash() !== $o->hash()) {
                // can't be same if hash is different
                return false;
            }

            return $this->parents == $o->parents && $this->returnStates == $o->returnStates;
        }

        return false;
    }

    public function __toString(): string
    {
        if ($this->isEmpty()) {
            return '[]';
        }

        $arr = [];
        foreach($this->returnStates as $i => $returnState) {
            if ($returnState === self::EMPTY_RETURN_STATE) {
                $arr[] = '$';
                continue;
            }

            if ($this->parents[$i] !== null) {
                $arr[] = "{$returnState} {$this->parents[$i]}";
            } else {
                $arr[] = "{$returnState} null";
            }
        }

        return '[' . implode(', ', $arr) . ']';
    }

    private function calculateHashCode(array $parents, array $returnStates): int
    {
        $hash = MurmurHash::initialize(self::INITIAL_HASH);

        foreach ($parents as $parent) {
            $hash = MurmurHash::update($hash, $parent->hash());
        }

        foreach ($returnStates as $returnState) {
            $hash = MurmurHash::update($hash, $returnState);
        }

        return MurmurHash::finish($hash, count($parents) + count($returnStates));
    }
}
