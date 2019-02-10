<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tests\Misc;

use ANTLR\v4\Runtime\Misc\IdentityHashMap;
use PHPUnit\Framework\TestCase;

class IdentityHashMapTest extends TestCase
{
    public function testMap()
    {
        $k1 = new \stdClass();
        $o1 = new \stdClass();
        $k2 = new \stdClass();
        $o2 = new \stdClass();
        $k3 = new \stdClass();

        $map = new IdentityHashMap();
        $map->put($k1, $o1);
        $map->put($k2, $o2);

        $this->assertTrue($map->containsKey($k1));
        $this->assertTrue($map->containsKey($k2));
        $this->assertFalse($map->containsKey($k3));

        $this->assertEquals($o1, $map->get($k1));
        $this->assertEquals($o2, $map->get($k2));
        $this->assertNull($map->get($k3));

        $map->remove($k1);
        $this->assertNull($map->get($k1));
        $this->assertEquals($o2, $map->get($k2));
        $this->assertFalse($map->containsKey($k1));
        $this->assertTrue($map->containsKey($k2));
    }
}
