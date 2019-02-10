<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Exception;

use ANTLR\v4\Runtime\Parser;
use ANTLR\v4\Runtime\ParserRuleContext;

/**
 * This signifies any kind of mismatched input exceptions such as
 * when the current input does not match the expected token.
 */
class InputMismatchException extends RecognitionException
{
    /**
     * @param \ANTLR\v4\Runtime\Parser $recognizer
     * @param int|null $state
     * @param \ANTLR\v4\Runtime\ParserRuleContext|null $ctx
     */
    public function __construct(Parser $recognizer, ?int $state = null, ParserRuleContext $ctx = null)
    {
        if ($ctx === null) {
            $ctx = $recognizer->getContext();
        }

        parent::__construct($recognizer, $recognizer->getInputStream(), $ctx);

        if ($state !== null) {
            $this->setOffendingState($state);
        }

        $this->setOffendingToken($recognizer->getCurrentToken());
    }
}
