<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\EqualityComparator;
use ANTLR\v4\Runtime\Misc\MurmurHash;
use InvalidArgumentException;

class AltAndContextConfigEqualityComparator implements EqualityComparator
{
    private function __construct()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function hashOf(?BaseObject $o): int
    {
        if (!($o instanceof ATNConfig)) {
            throw new InvalidArgumentException(sprintf('Expected %s instance, got %s', ATNConfig::class, get_class($o)));
        }

        $hash = MurmurHash::initialize(7);
        $hash = MurmurHash::update($hash, $o->state->stateNumber);
        $hash = MurmurHash::update($hash, $o->context->hash());
        $hash = MurmurHash::finish($hash, 2);

        return $hash;
    }

    /**
     * {@inheritdoc}
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet|null $a
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet|null $b
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
            && $a->context->equals($b->context);
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
