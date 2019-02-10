<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Exception;

use ANTLR\v4\Runtime\ATN\PredicateTransition;
use ANTLR\v4\Runtime\Parser;

/**
 * A semantic predicate failed during validation.  Validation of predicates
 * occurs when normally parsing the alternative just like matching a token.
 * Disambiguating predicate evaluation occurs when we test a predicate during
 * prediction.
 */
class FailedPredicateException extends RecognitionException
{
    /** @var int */
    private $ruleIndex = 0;

    /** @var int */
    private $predicateIndex = 0;

    /** @var string|null */
    private $predicate;

    /**
     * @param \ANTLR\v4\Runtime\Parser $recognizer
     * @param string|null $predicate
     * @param string|null $message
     */
    public function __construct(Parser $recognizer, ?string $predicate, ?string $message = null)
    {
        if ($message === null) {
            $message = sprintf("failed predicate: {%s}?", $predicate);
        }

        parent::__construct($recognizer, $recognizer->getInputStream(), $recognizer->getContext(), $message);

        $trans = $recognizer->getInterpreter()->atn->states[$recognizer->getState()]->transition(0);
        if ($trans instanceof PredicateTransition) {
            $this->ruleIndex = $trans->ruleIndex;
            $this->predicateIndex = $trans->predIndex;
        }

        $this->predicate = $predicate;
        $this->setOffendingToken($recognizer->getCurrentToken());
    }

    public function getRuleIndex(): int
    {
        return $this->ruleIndex;
    }

    public function getPredIndex(): int
    {
        return $this->predicateIndex;
    }

    public function getPredicate(): string
    {
        return $this->predicate;
    }
}
