<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use Ds\Hashable;

abstract class BaseObject implements Hashable
{
    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        return $this === $o;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        return spl_object_id($this);
    }
}
