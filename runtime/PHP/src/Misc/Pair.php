<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

use ANTLR\v4\Runtime\BaseObject;

class Pair extends BaseObject
{
    /** @var mixed */
    public $a;

    /** @var mixed */
    public $b;

    /**
     * @param mixed $a
     * @param mixed $b
     */
    public function __construct($a, $b)
    {
        $this->a = $a;
        $this->b = $b;
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof self) {
            return ObjectEqualityComparator::getInstance()->equalsTo($this->a, $o->a)
                && ObjectEqualityComparator::getInstance()->equalsTo($this->b, $o->b);
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        $hash = MurmurHash::initialize();
        $hash = MurmurHash::update($hash, $this->a->hash());
        $hash = MurmurHash::update($hash, $this->b->hash());
        $hash = MurmurHash::finish($hash, 2);

        return $hash;
    }

    public function __toString(): string
    {
        return "({$this->a}, {$this->b})";
    }
}
