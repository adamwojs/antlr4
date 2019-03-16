<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\ATN\ATNConfigSet\AbstractConfigHashSet;
use ANTLR\v4\Runtime\Misc\ObjectEqualityComparator;

class OrderedATNConfigSet extends ATNConfigSet
{
    public function __construct(bool $fullCtx = true)
    {
        parent::__construct($fullCtx);

        $this->configLookup = new class() extends AbstractConfigHashSet {
            public function __construct()
            {
                parent::__construct(ObjectEqualityComparator::getInstance());
            }
        };
    }
}
