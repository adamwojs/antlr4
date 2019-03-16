<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\SemanticContext;

use ANTLR\v4\Runtime\ATN\SemanticContext;
use ANTLR\v4\Runtime\Recognizer;
use ANTLR\v4\Runtime\RuleContext;

class PrecedencePredicate extends SemanticContext
{
    /** @var int */
    public $precedence;

    /**
     * @param int $precedence
     */
    public function __construct(int $precedence = 0)
    {
        $this->precedence = $precedence;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(Recognizer $parser, RuleContext $parserCallStack): bool
    {
        return $parser->precpred($parserCallStack, $this->precedence);
    }

    /**
     * {@inheritdoc}
     */
    public function evaluatePrecedence(Recognizer $parser, RuleContext $parserCallStack): ?SemanticContext
    {
        if ($parser->precpred($parserCallStack, $this->precedence)) {
            return SemanticContext::NONE();
        } else {
            return null;
        }
    }

    public function compareTo(self $o): int
    {
        return $this->precedence - $o->precedence;
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($this === $o) {
            return true;
        }

        if ($o instanceof self) {
            return $this->precedence === $o->precedence;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        return 31 + $this->precedence;
    }

    public function __toString(): string
    {
        // precedence >= _precedenceStack.peek()
        return "{{$this->precedence}>=prec}?";
    }

    public static function min(array $operands): self
    {
        $candidate = $operands[0];

        if (count($operands) > 1) {
            foreach ($operands as $operand) {
                if ($operand->compareTo($candidate) < 0) {
                    $candidate = $operand;
                }
            }
        }

        return $candidate;
    }

    public static function max(array $operands): self
    {
        $candidate = $operands[0];

        if (count($operands) > 1) {
            foreach ($operands as $operand) {
                if ($operand->compareTo($candidate) > 0) {
                    $candidate = $operand;
                }
            }
        }

        return $candidate;
    }
}
