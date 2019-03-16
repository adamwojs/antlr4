<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\Misc\BitSet;
use Ds\Pair;

/**
 * A Map that uses just the state and the stack context as the key.
 */
final class AltAndContextMap
{
    /** @var \Ds\Pair[] */
    private $entries = [];

    /** @var \ANTLR\v4\Runtime\EqualityComparator */
    private $comparator;

    public function __construct()
    {
        $this->comparator = AltAndContextConfigEqualityComparator::getInstance();
    }

    public function get(?ATNConfig $key): ?BitSet
    {
        if ($key === null) {
            return null;
        }

        foreach ($this->entries as $entry) {
            if ($this->comparator->equalsTo($entry->key, $key)) {
                return $entry->value;
            }
        }

        return null;
    }

    public function put(?ATNConfig $key, ?BitSet $value): ?BitSet
    {
        if ($key === null) {
            return null;
        }

        foreach ($this->entries as $entry) {
            if ($this->comparator->equalsTo($entry->key, $key)) {
                $prev = $entry->value;
                $entry->value = $value;

                return $prev;
            }
        }

        $this->entries[] = new Pair($key, $value);

        return null;
    }

    public function values(): array
    {
        return array_map(function (Pair $entry) {
            return $entry->value;
        }, $this->entries);
    }
}
