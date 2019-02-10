<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Exception;

use ANTLR\v4\Runtime\ATN\ATNConfigSet;
use ANTLR\v4\Runtime\CharStream;
use ANTLR\v4\Runtime\Lexer;
use ANTLR\v4\Runtime\Misc\Interval;

class LexerNoViableAltException extends RecognitionException
{
    /**
     * Matching attempted at what input index?
     *
     * @var int
     */
    private $startIndex;

    /**
     * Which configurations did we try at input.index() that couldn't match input.LA(1)?
     *
     * @var \ANTLR\v4\Runtime\ATN\ATNConfigSet
     */
    private $deadEndConfigs;

    /**
     * @param \ANTLR\v4\Runtime\Lexer $lexer
     * @param \ANTLR\v4\Runtime\CharStream $input
     * @param int $startIndex
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $deadEndConfigs
     */
    public function __construct(Lexer $lexer, CharStream $input, int $startIndex, ATNConfigSet $deadEndConfigs)
    {
        parent::__construct($lexer, $input, null);

        $this->startIndex = $startIndex;
        $this->deadEndConfigs = $deadEndConfigs;
    }

    public function getStartIndex(): int
    {
        return $this->startIndex;
    }

    public function getDeadEndConfigs(): ATNConfigSet
    {
        return $this->deadEndConfigs;
    }

    public function toString(): string
    {
        $symbol = "";

        /** @var \ANTLR\v4\Runtime\CharStream $input */
        $input = $this->getInputStream();
        if ($this->startIndex >= 0 && $this->startIndex < $input->size()) {
            $symbol = $input->getText(Interval::of($this->startIndex, $this->startIndex));
            $symbol = strtr($symbol, [
                "\n" => "\\n",
                "\t" => "\\t",
                "\r" => "\\r"
            ]);
        }

        return sprintf("%s('%s')", self::class, $symbol);
    }

    public function __toString()
    {
        return $this->toString();
    }
}
