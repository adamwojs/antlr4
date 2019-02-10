<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

interface CodePointBuffer
{
    public function position(?int $newPosition = null): int;

    public function remaining(): int;

    public function get(int $offset): int;

    public function arrayOffset(): int;

    public function toArray(): array;
}
