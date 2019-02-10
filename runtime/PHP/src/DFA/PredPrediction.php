<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\DFA;

use ANTLR\v4\Runtime\ATN\SemanticContext;
use ANTLR\v4\Runtime\BaseObject;

/**
 * Map a predicate to a predicted alternative.
 */
class PredPrediction extends BaseObject
{
    /**
     * never null; at least SemanticContext.NONE
     *
     * @var \ANTLR\v4\Runtime\ATN\SemanticContext
     */
    public $pred;

    /** @var int */
    public $alt;

    /**
     * @param \ANTLR\v4\Runtime\ATN\SemanticContext $pred
     * @param int $alt
     */
    public function __construct(SemanticContext $pred, int $alt)
    {
        $this->pred = $pred;
        $this->alt = $alt;
    }

    public function __toString(): string
    {
        return "({$this->pred}, {$this->alt})";
    }
}
