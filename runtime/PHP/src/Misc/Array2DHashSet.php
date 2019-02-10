<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

use ANTLR\v4\Runtime\ATN\ATNConfig;
use ANTLR\v4\Runtime\EqualityComparator;

class Array2DHashSet
{
    /** @var \ANTLR\v4\Runtime\EqualityComparator */
    protected $comparator;

    /** @var \ANTLR\v4\Runtime\ATN\ATNConfig[][] */
    protected $buckets = [];

    /** @var int */
    protected $n = 0;

    public function __construct(EqualityComparator $comparator)
    {
        $this->comparator = $comparator;
    }

    /**
     * Add {@code o} to set if not there; return existing value if already
     * there. This method performs the same operation as {@link #add} aside from
     * the return value.
     */
    public function getOrAdd(ATNConfig $o): ATNConfig
    {
        //return $this->getOrAddImpl($o);
    }

    protected function getBucket(ATNConfig $o): int
    {
        return $this->comparator->hashOf($o) & (count($this->buckets - 1));
    }
}
