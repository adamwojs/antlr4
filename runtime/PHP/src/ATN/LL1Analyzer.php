<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Misc\BitSet;
use ANTLR\v4\Runtime\Misc\IntervalSet;
use ANTLR\v4\Runtime\RuleContext;
use ANTLR\v4\Runtime\Token;
use Ds\Set;

class LL1Analyzer extends BaseObject
{
    /**
     * Special value added to the lookahead sets to indicate that we hit
     * a predicate during analysis if {@code seeThruPreds==false}.
     *
     * @var int
     */
    public const HIT_PRED = Token::INVALID_TYPE;

    /** @var \ANTLR\v4\Runtime\ATN\ATN */
    public $atn;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATN $atn
     */
    public function __construct(ATN $atn)
    {
        $this->atn = $atn;
    }

    /**
     * Calculates the SLL(1) expected lookahead set for each outgoing transition
     * of an {@link ATNState}. The returned array has one element for each
     * outgoing transition in {@code s}. If the closure from transition
     * <em>i</em> leads to a semantic predicate before matching a symbol, the
     * element at index <em>i</em> of the result will be {@code null}.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNState $state the ATN state
     *
     * @return \ANTLR\v4\Runtime\Misc\IntervalSet[] the expected symbols for each outgoing transition of {@code s}
     */
    public function getDecisionLookahead(ATNState $state): array
    {
        /** @var \ANTLR\v4\Runtime\Misc\IntervalSet[] $look */
        $look = [];
        for ($alt = 0; $alt < $state->getNumberOfTransitions(); $alt++) {
            $look[$alt] = new IntervalSet();
            $lookBusy = new Set();
            // fail to get lookahead upon pred
            $seeThruPreds = false;

            $this->_LOOK(
                $state->transition($alt)->target,
                null,
                PredictionContext::createEmpty(),
                $look[$alt],
                $lookBusy,
                new BitSet(),
                $seeThruPreds,
                false
            );

            // Wipe out lookahead for this alternative if we found nothing
            // or we had a predicate when we !seeThruPreds
            if ($look[$alt]->size() === 0 || $look[$alt]->contains(self::HIT_PRED)) {
                $look[$alt] = null;
            }
        }

        return $look;
    }

    /**
     * Compute set of tokens that can follow {@code s} in the ATN in the
     * specified {@code ctx}.
     *
     * <p>If {@code ctx} is {@code null} and the end of the rule containing
     * {@code s} is reached, {@link Token#EPSILON} is added to the result set.
     * If {@code ctx} is not {@code null} and the end of the outermost rule is
     * reached, {@link Token#EOF} is added to the result set.</p>
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNState s the ATN state
     * @param \ANTLR\v4\Runtime\ATN\ATNState|null stopState the ATN state to stop at. This can be a
     * {@link BlockEndState} to detect epsilon paths through a closure.
     * @param \ANTLR\v4\Runtime\RuleContext|null ctx the complete parser context, or {@code null} if the context
     * should be ignored
     *
     * @return \ANTLR\v4\Runtime\Misc\IntervalSet the set of tokens that can follow {@code s} in the ATN in the
     * specified {@code ctx}
     */
    public function LOOK(ATNState $s, ?ATNState $stopState, ?RuleContext $ctx): IntervalSet
    {
        $look = new IntervalSet();
        $lookContext = null;
        if ($ctx !== null) {
            $lookContext = PredictionContext::fromRuleContext($s->atn, $ctx);
        }
        $lookBusy = new Set();
        $calledRuleStack = new BitSet();

        $this->_LOOK($s, $stopState, $lookContext, $look, $lookBusy, $calledRuleStack, true, true);

        return $look;
    }

    /**
     * Compute set of tokens that can follow {@code s} in the ATN in the
     * specified {@code ctx}.
     *
     * <p>If {@code ctx} is {@code null} and {@code stopState} or the end of the
     * rule containing {@code s} is reached, {@link Token#EPSILON} is added to
     * the result set. If {@code ctx} is not {@code null} and {@code addEOF} is
     * {@code true} and {@code stopState} or the end of the outermost rule is
     * reached, {@link Token#EOF} is added to the result set.</p>
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNState $s the ATN state
     * @param \ANTLR\v4\Runtime\ATN\ATNState $stopState the ATN state to stop at. This can be a
     * {@link BlockEndState} to detect epsilon paths through a closure.
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $ctx the outer context, or {@code null} if the outer context should
     * not be used
     * @param \ANTLR\v4\Runtime\Misc\IntervalSet look The result lookahead set
     * @param \ANTLR\v4\Runtime\ATN\ATNConfig[] lookBusy A set used for preventing epsilon closures in the ATN
     * from causing a stack overflow. Outside code should pass
     * {@code new HashSet<ATNConfig>} for this argument.
     * @param \ANTLR\v4\Runtime\Misc\BitSet calledRuleStack A set used for preventing left recursion in the
     * ATN from causing a stack overflow. Outside code should pass
     * {@code new BitSet()} for this argument.
     * @param bool seeThruPreds {@code true} to true semantic predicates as
     * implicitly {@code true} and "see through them", otherwise {@code false}
     * to treat semantic predicates as opaque and add {@link #HIT_PRED} to the
     * result if one is encountered
     * @param bool $addEOF Add {@link Token#EOF} to the result if the end of the
     * outermost context is reached. This parameter has no effect if {@code ctx}
     * is {@code null}.
     */
    protected function _LOOK(
        ATNState $s,
        ?ATNState $stopState,
        ?PredictionContext $ctx,
        IntervalSet $look,
        Set $lookBusy,
        BitSet $calledRuleStack,
        bool $seeThruPreds,
        bool $addEOF
    ): void {
        $c = new ATNConfig($s, 0, $ctx, 0, SemanticContext::NONE());

        if ($lookBusy->contains($c)) {
            return;
        }

        $lookBusy->add($c);

        if ($s === $stopState) {
            if ($ctx === null) {
                $look->add(Token::EPSILON);

                return;
            }

            if ($ctx->isEmpty() && $addEOF) {
                $look->add(Token::EOF);

                return;
            }
        }

        if ($s instanceof RuleStopState) {
            if ($ctx === null) {
                $look->add(Token::EPSILON);

                return;
            } elseif ($ctx->isEmpty() && $addEOF) {
                $look->add(Token::EOF);

                return;
            }

            if ($ctx !== PredictionContext::createEmpty()) {
                // run thru all possible stack tops in ctx
                $removed = $calledRuleStack->get($s->ruleIndex);
                try {
                    $calledRuleStack->clear($s->ruleIndex);
                    for ($i = 0; $i < $ctx->size(); $i++) {
                        $returnState = $this->atn->states[$ctx->getReturnState($i)];

                        $this->_LOOK($returnState, $stopState, $ctx->getParent($i), $look, $lookBusy, $calledRuleStack, $seeThruPreds, $addEOF);
                    }
                } finally {
                    if ($removed) {
                        $calledRuleStack->set($s->ruleIndex);
                    }
                }

                return;
            }
        }

        $n = $s->getNumberOfTransitions();
        for ($i = 0; $i < $n; $i++) {
            $t = $s->transition($i);

            if ($t instanceof RuleTransition) {
                if ($calledRuleStack->get($t->target->ruleIndex)) {
                    continue;
                }

                $newContext = SingletonPredictionContext::create($ctx, $t->followState->stateNumber);
                try {
                    $calledRuleStack->set($t->target->ruleIndex);
                    $this->_LOOK($t->target, $stopState, $newContext, $look, $lookBusy, $calledRuleStack, $seeThruPreds, $addEOF);
                } finally {
                    $calledRuleStack->clear($t->target->ruleIndex);
                }
            } elseif ($t instanceof AbstractPredicateTransition) {
                if ($seeThruPreds) {
                    $this->_LOOK($t->target, $stopState, $ctx, $look, $lookBusy, $calledRuleStack, $seeThruPreds, $addEOF);
                } else {
                    $look->add(self::HIT_PRED);
                }
            } elseif ($t->isEpsilon()) {
                $this->_LOOK($t->target, $stopState, $ctx, $look, $lookBusy, $calledRuleStack, $seeThruPreds, $addEOF);
            } elseif ($t instanceof WildcardTransition) {
                $look->addAll(IntervalSet::of(Token::MIN_USER_TOKEN_TYPE, $this->atn->maxTokenType));
            } else {
                $set = $t->label();
                if ($set !== null) {
                    if ($t instanceof NotSetTransition) {
                        $set = $set->complement(IntervalSet::of(Token::MIN_USER_TOKEN_TYPE, $this->atn->maxTokenType));
                    }
                    $look->addAll($set);
                }
            }
        }
    }
}
