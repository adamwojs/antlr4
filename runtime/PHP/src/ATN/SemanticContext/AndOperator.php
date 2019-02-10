<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\SemanticContext;

use ANTLR\v4\Runtime\ATN\SemanticContext;
use ANTLR\v4\Runtime\Misc\MurmurHash;
use ANTLR\v4\Runtime\Recognizer;
use ANTLR\v4\Runtime\RuleContext;
use Ds\Set;

/**
 * A semantic context which is true whenever none of the contained contexts
 * is false.
 */
class AndOperator extends Operator
{
    /** @var \ANTLR\v4\Runtime\ATN\SemanticContext[] */
    public $opnds;

    /**
     * @param \ANTLR\v4\Runtime\ATN\SemanticContext $a
     * @param \ANTLR\v4\Runtime\ATN\SemanticContext $b
     */
    public function __construct(SemanticContext $a, SemanticContext $b)
    {
        $operands = new Set();
        if ($a instanceof AndOperator) {
            $operands->add(...$a->opnds);
        } else {
            $operands->add($a);
        }

        if ($b instanceof AndOperator) {
            $operands->add(...$b->opnds);
        } else {
            $operands->add($b);
        }

        $precedencePredicates = self::filterPrecedencePredicates($operands);
        if (!empty($precedencePredicates)) {
            // interested in the transition with the lowest precedence
            $operands->add(PrecedencePredicate::min($precedencePredicates));
        }

        $this->opnds = $operands->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getOperands(): array
    {
        return $this->opnds;
    }

    /**
     * {@inheritdoc}
     *
     * <p>The evaluation of predicates by this context is short-circuiting, but
     * unordered.</p>
     */
    public function evaluate(Recognizer $parser, RuleContext $parserCallStack): bool
    {
        foreach ($this->opnds as $opnd) {
            if (!$opnd->evaluate($parser, $parserCallStack)) {
                return false;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluatePrecedence(Recognizer $parser, RuleContext $parserCallStack): ?SemanticContext
    {
        $differs = false;
        $operands = [];

        foreach ($this->opnds as $context) {
            $evaluated = $context->evaluatePrecedence($parser, $parserCallStack);
            $differs |= ($evaluated !== $context);

            if ($evaluated === null) {
                // The AND context is false if any element is false
                return null;
            } else if ($evaluated !== SemanticContext::NONE()) {
                // Reduce the result by skipping true elements
                $operands[] = $evaluated;
            }
        }

        if (!$differs) {
            return $this;
        }

        if (empty($operands)) {
            // all elements were true, so the AND context is true
            return SemanticContext::NONE();
        }

        $result = $operands[0];
        foreach ($operands as $operand) {
            $result = SemanticContext::and($result, $operand);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($this === $o) {
            return true;
        }

        if ($o instanceof AndOperator) {
            return $this->opnds === $o->opnds;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        return MurmurHash::hashOfArray($this->opnds);
    }

    public function __toString(): string
    {
        return implode('&&', $this->opnds);
    }
}
