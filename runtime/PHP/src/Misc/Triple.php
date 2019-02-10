<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Misc;

use ANTLR\v4\Runtime\BaseObject;

class Triple extends BaseObject
{
    /** @var mixed */
    public $a;

    /** @var mixed */
    public $b;

    /** @var mixed */
    public $c;

    /**
     * @param mixed $a
     * @param mixed $b
     * @param mixed $c
     */
    public function __construct($a, $b, $c)
    {
        $this->a = $a;
        $this->b = $b;
        $this->c = $c;
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof Triple) {
            return ObjectEqualityComparator::getInstance()->equalsTo($this->a, $o->a)
                && ObjectEqualityComparator::getInstance()->equalsTo($this->b, $o->b)
                && ObjectEqualityComparator::getInstance()->equalsTo($this->c, $o->c);
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
        $hash = MurmurHash::update($hash, $this->c->hash());
        $hash = MurmurHash::finish($hash, 3);

        return $hash;
    }

    public function __toString(): string
    {
        return "({$this->a}, {$this->b}), {$this->c}";
    }
}
