<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\ATNDeserializer;

interface UnicodeDeserializer
{
    /**
     * Wrapper for readInt() or readInt32().
     *
     * @param array $data
     * @param int $p
     *
     * @return int
     */
    public function readUnicode(array &$data, int $p): int;

    /**
     * Work around Java not allowing mutation of captured variables
     * by returning amount by which to increment p after each read.
     *
     * @return int
     */
    public function size(): int;
}
