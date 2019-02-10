<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Misc\Pair;

/**
 * The default mechanism for creating tokens. It's used by default in Lexer and
 * the error handling strategy (to create missing tokens).  Notifying the parser
 * of a new factory means that it notifies its token source and error strategy.
 */
interface TokenFactory
{
    /**
     * This is the method used to create tokens in the lexer and in the
     * error handling strategy. If text!=null, than the start and stop positions
     * are wiped to -1 in the text override is set in the CommonToken.
     *
     * @param \ANTLR\v4\Runtime\Misc\Pair $source
     * @param int $type
     * @param string|null $text
     * @param int $channel
     * @param int $start
     * @param int $stop
     * @param int $line
     * @param int $charPositionInLine
     *
     * @return \ANTLR\v4\Runtime\Token
     */
    public function create(
        Pair $source,
        int $type,
        ?string $text,
        int $channel,
        int $start,
        int $stop,
        int $line,
        int $charPositionInLine
    ): Token;

    /**
     * Generically useful.
     *
     * @param int $type
     * @param string|null $text
     *
     * @return \ANTLR\v4\Runtime\Token
     */
    public function createWithTypeAndText(int $type, ?string $text): Token;
}
