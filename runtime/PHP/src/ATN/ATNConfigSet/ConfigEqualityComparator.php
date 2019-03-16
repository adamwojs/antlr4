<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\ATNConfigSet;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\EqualityComparator;

class ConfigEqualityComparator extends BaseObject implements EqualityComparator
{
    private function __construct()
    {
    }

    /**
     * {@inheritdoc}
     *
     * @param $o \ANTLR\v4\Runtime\ATN\ATNConfig
     */
    public function hashOf(?BaseObject $o): int
    {
        $hash = 7;
        $hash = 31 * $hash + $o->state->stateNumber;
        $hash = 31 * $hash + $o->alt;
        $hash = 31 * $hash + $o->semanticContext->hash();

        return $hash;
    }

    /**
     * {@inheritdoc}
     *
     * @param $a \ANTLR\v4\Runtime\ATN\ATNConfig
     * @param $b \ANTLR\v4\Runtime\ATN\ATNConfig
     */
    public function equalsTo(?BaseObject $a, ?BaseObject $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if ($a === null || $b === null) {
            return false;
        }

        return $a->state->stateNumber === $b->state->stateNumber
            && $a->alt === $b->alt
            && $a->semanticContext->equals($b->semanticContext);
    }

    public static function getInstance(): self
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }
}
