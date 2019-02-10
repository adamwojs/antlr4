<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\ATNConfigSet;

use ANTLR\v4\Runtime\ATN\ATNConfig;
use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\EqualityComparator;
use Ds\Set;

abstract class AbstractConfigHashSet extends BaseObject
{
    /** @var \ANTLR\v4\Runtime\ATN\ATNConfig[] */
    private $configs = [];

    /** @var \ANTLR\v4\Runtime\EqualityComparator */
    private $comparator;

    /**
     * @param \ANTLR\v4\Runtime\EqualityComparator $comparator
     */
    public function __construct(EqualityComparator $comparator)
    {
        $this->comparator = $comparator;
    }

    public function getOrAdd(ATNConfig $o): ATNConfig
    {
        foreach ($this->configs as $c) {
            if ($this->comparator->equalsTo($c, $o)) {
                return $c;
            }
        }

        $this->configs[] = $o;

        return $o;
    }

    public function contains($o): bool
    {
        foreach ($this->configs as $c) {
            if ($this->comparator->equalsTo($c, $o)) {
                return true;
            }
        }

        return false;
    }

    public function isEmpty(): bool
    {
        return empty($this->buckets);
    }

    public function clear(): void
    {
        $this->configs = [];
    }
}
