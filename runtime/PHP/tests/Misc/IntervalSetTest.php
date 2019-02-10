<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tests\Misc;

use ANTLR\v4\Runtime\Misc\IntervalSet;
use PHPUnit\Framework\TestCase;

class IntervalSetTest extends TestCase
{
    public function testSingleElement(): void
    {
        $set = IntervalSet::of(99);

        $this->assertEquals('99', (string)$set);
    }

    public function testAddIsolatedElements(): void
    {
        $set = new IntervalSet();
        $set->add(1);
        $set->add(3);
        $set->add(5);

        $this->assertEquals('{1, 3, 5}', (string)$set);
    }

    public function testAddDuplicatedElement(): void
    {
        $set = new IntervalSet();
        $set->add(1, 2);
        $set->add(1, 2);

        $this->assertEquals('1..2', (string)$set);
    }

    public function testAddMixedRangesAndElements(): void
    {
        $set = new IntervalSet();
        $set->add(1);
        $set->add(97, 122);
        $set->add(48, 57);

        $this->assertEquals('{1, 48..57, 97..122}', (string)$set);
    }

    public function testAddIsMergingAdjacentIntervals(): void
    {
        $set = new IntervalSet();
        $set->add(1, 3);
        $set->add(5, 7);
        $set->add(4, 5);

        $this->assertEquals('1..7', (string)$set);
    }
}
