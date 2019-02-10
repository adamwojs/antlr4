<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\ATNConfigSet;

/**
 * The reason that we need this is because we don't want the hash map to use
 * the standard hash code and equals. We need all configurations with the same
 * {@code (s,i,_,semctx)} to be equal. Unfortunately, this key effectively doubles
 * the number of objects associated with ATNConfigs. The other solution is to
 * use a hash table that lets us specify the equals/hashcode operation.
 */
class ConfigHashSet extends AbstractConfigHashSet
{
    public function __construct()
    {
        parent::__construct(ConfigEqualityComparator::getInstance());
    }
}
