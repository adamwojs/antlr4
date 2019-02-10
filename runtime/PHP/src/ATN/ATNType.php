<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * Represents the type of recognizer an ATN applies to.
 *
 * @author Sam Harwell
 */
final class ATNType
{
    /**
     * A lexer grammar.
     *
     * @var int
     */
    public const LEXER = 0;

    /**
     * A parser grammar.
     *
     * @var int
     */
    public const PARSER = 1;
}
