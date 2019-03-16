<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Exception;

use ANTLR\v4\Runtime\ATN\ATNConfigSet;
use ANTLR\v4\Runtime\Parser;
use ANTLR\v4\Runtime\ParserRuleContext;
use ANTLR\v4\Runtime\Token;
use ANTLR\v4\Runtime\TokenStream;

/**
 * Indicates that the parser could not decide which of two or more paths
 * to take based upon the remaining input. It tracks the starting token
 * of the offending input and also knows where the parser was
 * in the various paths when the error. Reported by reportNoViableAlternative().
 */
class NoViableAltException extends RecognitionException
{
    /**
     * Which configurations did we try at input.index() that couldn't match input.LT(1)?
     *
     * @var \ANTLR\v4\Runtime\ATN\ATNConfigSet
     */
    private $deadEndConfigs;

    /**
     * The token object at the start index; the input stream might
     * not be buffering tokens so get a reference to it. (At the
     * time the error occurred, of course the stream needs to keep a
     * buffer all of the tokens but later we might not have access to those.).
     *
     * @var \ANTLR\v4\Runtime\Token
     */
    private $startToken;

    /**
     * @param \ANTLR\v4\Runtime\Parser $recognizer
     * @param \ANTLR\v4\Runtime\TokenStream|null $input
     * @param \ANTLR\v4\Runtime\Token|null $startToken
     * @param \ANTLR\v4\Runtime\Token|null $offendingToken
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet|null $deadEndConfigs
     * @param \ANTLR\v4\Runtime\ParserRuleContext|null $ctx
     */
    public function __construct(
        Parser $recognizer,
        TokenStream $input = null,
        Token $startToken = null,
        Token $offendingToken = null,
        ATNConfigSet $deadEndConfigs = null,
        ParserRuleContext $ctx = null
    ) {
        parent::__construct($recognizer, $input ?? $recognizer->getInputStream(), $ctx);

        $this->deadEndConfigs = $deadEndConfigs;
        $this->startToken = $startToken ?? $recognizer->getCurrentToken();

        if ($offendingToken === null) {
            $offendingToken = $recognizer->getCurrentToken();
        }

        if ($offendingToken !== null) {
            $this->setOffendingToken($offendingToken);
        }
    }

    public function getStartToken(): Token
    {
        return $this->startToken;
    }

    public function getDeadEndConfigs(): ATNConfigSet
    {
        return $this->deadEndConfigs;
    }
}
