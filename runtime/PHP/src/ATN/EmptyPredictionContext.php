<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

class EmptyPredictionContext extends SingletonPredictionContext
{
    public function __construct()
    {
        parent::__construct(null, self::EMPTY_RETURN_STATE);
    }

    /**
     * {@inheritdoc}
     */
    public function isEmpty(): bool
    {
        return true;
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
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getReturnState(int $index): int
    {
        return $this->returnState;
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        return $this === $o;
    }

    public function __toString(): string
    {
        return '$';
    }
}
