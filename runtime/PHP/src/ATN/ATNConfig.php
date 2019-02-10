<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;

/**
 * A tuple: (ATN state, predicted alt, syntactic, semantic context).
 * The syntactic context is a graph-structured stack node whose
 * path(s) to the root is the rule invocation(s)
 * chain used to arrive at the state.  The semantic context is
 * the tree of semantic predicates encountered before reaching
 * an ATN state.
 */
class ATNConfig extends BaseObject
{
    /**
     * This field stores the bit mask for implementing the
     * {@link #isPrecedenceFilterSuppressed} property as a bit within the
     * existing {@link #reachesIntoOuterContext} field.
     *
     * @var int
     */
    private const SUPPRESS_PRECEDENCE_FILTER = 0x40000000;

    /**
     * The ATN state associated with this configuration
     *
     * @var \ANTLR\v4\Runtime\ATN\ATNState
     */
    public $state;

    /**
     * What alt (or lexer rule) is predicted by this configuration
     *
     * @var int
     */
    public $alt;

    /**
     * The stack of invoking states leading to the rule/states associated
     * with this config.  We track only those contexts pushed during
     * execution of the ATN simulator.
     *
     * @var \ANTLR\v4\Runtime\ATN\PredictionContext
     */
    public $context;

    /**
     * We cannot execute predicates dependent upon local context unless
     * we know for sure we are in the correct context. Because there is
     * no way to do this efficiently, we simply cannot evaluate
     * dependent predicates unless we are in the rule that initially
     * invokes the ATN simulator.
     *
     * <p>
     * closure() tracks the depth of how far we dip into the outer context:
     * depth &gt; 0.  Note that it may not be totally accurate depth since I
     * don't ever decrement. TODO: make it a boolean then</p>
     *
     * <p>
     * For memory efficiency, the {@link #isPrecedenceFilterSuppressed} method
     * is also backed by this field. Since the field is publicly accessible, the
     * highest bit which would not cause the value to become negative is used to
     * store this field. This choice minimizes the risk that code which only
     * compares this value to 0 would be affected by the new purpose of the
     * flag. It also ensures the performance of the existing {@link ATNConfig}
     * constructors as well as certain operations like
     * {@link ATNConfigSet#add(ATNConfig, DoubleKeyMap)} method are
     * <em>completely</em> unaffected by the change.</p>
     *
     * @var int
     */
    public $reachesIntoOuterContext;

    /**
     * @var \ANTLR\v4\Runtime\ATN\SemanticContext
     */
    public $semanticContext;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $state
     * @param int $alt
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $context
     * @param int $reachesIntoOuterContext
     * @param \ANTLR\v4\Runtime\ATN\SemanticContext $semanticContext
     */
    public function __construct(
        ATNState $state,
        int $alt,
        ?PredictionContext $context,
        int $reachesIntoOuterContext,
        ?SemanticContext $semanticContext
    ) {
        $this->state = $state;
        $this->alt = $alt;
        $this->context = $context;
        $this->reachesIntoOuterContext = $reachesIntoOuterContext;
        $this->semanticContext = $semanticContext;
    }

    /**
     * This method gets the value of the {@link #reachesIntoOuterContext} field
     * as it existed prior to the introduction of the
     * {@link #isPrecedenceFilterSuppressed} method.
     *
     * @return int
     */
    public function getOuterContextDepth(): int
    {
        return $this->reachesIntoOuterContext & ~self::SUPPRESS_PRECEDENCE_FILTER;
    }

    public function isPrecedenceFilterSuppressed(): bool
    {
        return ($this->reachesIntoOuterContext & self::SUPPRESS_PRECEDENCE_FILTER) !== 0;
    }

    public function setPrecedenceFilterSuppressed(bool $value): void
    {
        if ($value) {
            $this->reachesIntoOuterContext |= 0x40000000;
        } else {
            $this->reachesIntoOuterContext &= ~self::SUPPRESS_PRECEDENCE_FILTER;
        }
    }

    /**
     * An ATN configuration is equal to another if both have
     * the same state, they predict the same alternative, and
     * syntactic/semantic contexts are the same.
     *
     * @param mixed $o
     *
     * @return bool
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof ATNConfig) {
            return $o->state->stateNumber === $this->state->stateNumber
                && $o->alt === $this->alt
                && ($o->context === $this->context || ($this->context !== null && $this->context->equals($o->context)))
                && $o->semanticContext->equals($this->semanticContext)
                && $o->isPrecedenceFilterSuppressed() === $this->isPrecedenceFilterSuppressed();
        }

        return false;
    }

    public function __toString(): string
    {
        $str = '(';
        $str .= $this->state;

        if ($this->context !== null) {
            $str .= ',[';
            $str .= (string)$this->context;
            $str .= ']';
        }

        if ($this->semanticContext !== SemanticContext::NONE()) {
            $str .= ',';
            $str .= (string)$this->semanticContext;
        }

        if ($this->getOuterContextDepth() > 0) {
            $str .= ',up=';
            $str .= (string)$this->getOuterContextDepth();
        }

        $str .= ')';

        return $str;
    }

    /**
     * Return copy of given ATNConfig.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfig $config
     *
     * @return \ANTLR\v4\Runtime\ATN\ATNConfig
     */
    public static function copy(ATNConfig $config): self
    {
        return new self(
            $config->state,
            $config->alt,
            $config->context,
            $config->reachesIntoOuterContext,
            $config->semanticContext
        );
    }

    public static function copyWithoutSemanticContext(ATNConfig $config, SemanticContext $semanticContext): self
    {
        return new self(
            $config->state,
            $config->alt,
            $config->context,
            $config->reachesIntoOuterContext,
            $semanticContext
        );
    }
}
