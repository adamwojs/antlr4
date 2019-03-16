<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\SemanticContext;

use ANTLR\v4\Runtime\ATN\SemanticContext;

/**
 * This is the base class for semantic context "operators", which operate on
 * a collection of semantic context "operands".
 */
abstract class Operator extends SemanticContext
{
    /**
     * Gets the operands for the semantic context operator.
     *
     * @return array a collection of {@link SemanticContext} operands for the
     * operator
     */
    abstract public function getOperands(): array;
}
