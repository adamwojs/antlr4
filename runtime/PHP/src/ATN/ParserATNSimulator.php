<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\ATN\ParserATNSimulator\MergeCache;
use ANTLR\v4\Runtime\DFA\DFA;
use ANTLR\v4\Runtime\DFA\DFAState;
use ANTLR\v4\Runtime\DFA\PredPrediction;
use ANTLR\v4\Runtime\Exception\NoViableAltException;
use ANTLR\v4\Runtime\IntStream;
use ANTLR\v4\Runtime\Misc\BitSet;
use ANTLR\v4\Runtime\Misc\IntervalSet;
use ANTLR\v4\Runtime\Parser;
use ANTLR\v4\Runtime\ParserRuleContext;
use ANTLR\v4\Runtime\RuleContext;
use ANTLR\v4\Runtime\Token;
use ANTLR\v4\Runtime\TokenStream;
use Ds\Set;
use RuntimeException;

/**
 * The embodiment of the adaptive LL(*), ALL(*), parsing strategy.
 *
 * <p>
 * The basic complexity of the adaptive strategy makes it harder to understand.
 * We begin with ATN simulation to build paths in a DFA. Subsequent prediction
 * requests go through the DFA first. If they reach a state without an edge for
 * the current symbol, the algorithm fails over to the ATN simulation to
 * complete the DFA path for the current input (until it finds a conflict state
 * or uniquely predicting state).</p>
 *
 * <p>
 * All of that is done without using the outer context because we want to create
 * a DFA that is not dependent upon the rule invocation stack when we do a
 * prediction. One DFA works in all contexts. We avoid using context not
 * necessarily because it's slower, although it can be, but because of the DFA
 * caching problem. The closure routine only considers the rule invocation stack
 * created during prediction beginning in the decision rule. For example, if
 * prediction occurs without invoking another rule's ATN, there are no context
 * stacks in the configurations. When lack of context leads to a conflict, we
 * don't know if it's an ambiguity or a weakness in the strong LL(*) parsing
 * strategy (versus full LL(*)).</p>
 *
 * <p>
 * When SLL yields a configuration set with conflict, we rewind the input and
 * retry the ATN simulation, this time using full outer context without adding
 * to the DFA. Configuration context stacks will be the full invocation stacks
 * from the start rule. If we get a conflict using full context, then we can
 * definitively say we have a true ambiguity for that input sequence. If we
 * don't get a conflict, it implies that the decision is sensitive to the outer
 * context. (It is not context-sensitive in the sense of context-sensitive
 * grammars.)</p>
 *
 * <p>
 * The next time we reach this DFA state with an SLL conflict, through DFA
 * simulation, we will again retry the ATN simulation using full context mode.
 * This is slow because we can't save the results and have to "interpret" the
 * ATN each time we get that input.</p>
 *
 * <p>
 * <strong>CACHING FULL CONTEXT PREDICTIONS</strong></p>
 *
 * <p>
 * We could cache results from full context to predicted alternative easily and
 * that saves a lot of time but doesn't work in presence of predicates. The set
 * of visible predicates from the ATN start state changes depending on the
 * context, because closure can fall off the end of a rule. I tried to cache
 * tuples (stack context, semantic context, predicted alt) but it was slower
 * than interpreting and much more complicated. Also required a huge amount of
 * memory. The goal is not to create the world's fastest parser anyway. I'd like
 * to keep this algorithm simple. By launching multiple threads, we can improve
 * the speed of parsing across a large number of files.</p>
 *
 * <p>
 * There is no strict ordering between the amount of input used by SLL vs LL,
 * which makes it really hard to build a cache for full context. Let's say that
 * we have input A B C that leads to an SLL conflict with full context X. That
 * implies that using X we might only use A B but we could also use A B C D to
 * resolve conflict. Input A B C D could predict alternative 1 in one position
 * in the input and A B C E could predict alternative 2 in another position in
 * input. The conflicting SLL configurations could still be non-unique in the
 * full context prediction, which would lead us to requiring more input than the
 * original A B C.    To make a    prediction cache work, we have to track    the exact
 * input    used during the previous prediction. That amounts to a cache that maps
 * X to a specific DFA for that context.</p>
 *
 * <p>
 * Something should be done for left-recursive expression predictions. They are
 * likely LL(1) + pred eval. Easier to do the whole SLL unless error and retry
 * with full LL thing Sam does.</p>
 *
 * <p>
 * <strong>AVOIDING FULL CONTEXT PREDICTION</strong></p>
 *
 * <p>
 * We avoid doing full context retry when the outer context is empty, we did not
 * dip into the outer context by falling off the end of the decision state rule,
 * or when we force SLL mode.</p>
 *
 * <p>
 * As an example of the not dip into outer context case, consider as super
 * constructor calls versus function calls. One grammar might look like
 * this:</p>
 *
 * <pre>
 * ctorBody
 *   : '{' superCall? stat* '}'
 *   ;
 * </pre>
 *
 * <p>
 * Or, you might see something like</p>
 *
 * <pre>
 * stat
 *   : superCall ';'
 *   | expression ';'
 *   | ...
 *   ;
 * </pre>
 *
 * <p>
 * In both cases I believe that no closure operations will dip into the outer
 * context. In the first case ctorBody in the worst case will stop at the '}'.
 * In the 2nd case it should stop at the ';'. Both cases should stay within the
 * entry rule and not dip into the outer context.</p>
 *
 * <p>
 * <strong>PREDICATES</strong></p>
 *
 * <p>
 * Predicates are always evaluated if present in either SLL or LL both. SLL and
 * LL simulation deals with predicates differently. SLL collects predicates as
 * it performs closure operations like ANTLR v3 did. It delays predicate
 * evaluation until it reaches and accept state. This allows us to cache the SLL
 * ATN simulation whereas, if we had evaluated predicates on-the-fly during
 * closure, the DFA state configuration sets would be different and we couldn't
 * build up a suitable DFA.</p>
 *
 * <p>
 * When building a DFA accept state during ATN simulation, we evaluate any
 * predicates and return the sole semantically valid alternative. If there is
 * more than 1 alternative, we report an ambiguity. If there are 0 alternatives,
 * we throw an exception. Alternatives without predicates act like they have
 * true predicates. The simple way to think about it is to strip away all
 * alternatives with false predicates and choose the minimum alternative that
 * remains.</p>
 *
 * <p>
 * When we start in the DFA and reach an accept state that's predicated, we test
 * those and return the minimum semantically viable alternative. If no
 * alternatives are viable, we throw an exception.</p>
 *
 * <p>
 * During full LL ATN simulation, closure always evaluates predicates and
 * on-the-fly. This is crucial to reducing the configuration set size during
 * closure. It hits a landmine when parsing with the Java grammar, for example,
 * without this on-the-fly evaluation.</p>
 *
 * <p>
 * <strong>SHARING DFA</strong></p>
 *
 * <p>
 * All instances of the same parser share the same decision DFAs through a
 * static field. Each instance gets its own ATN simulator but they share the
 * same {@link #decisionToDFA} field. They also share a
 * {@link PredictionContextCache} object that makes sure that all
 * {@link PredictionContext} objects are shared among the DFA states. This makes
 * a big size difference.</p>
 *
 * <p>
 * <strong>THREAD SAFETY</strong></p>
 *
 * <p>
 * The {@link ParserATNSimulator} locks on the {@link #decisionToDFA} field when
 * it adds a new DFA object to that array. {@link #addDFAEdge}
 * locks on the DFA for the current decision when setting the
 * {@link DFAState#edges} field. {@link #addDFAState} locks on
 * the DFA for the current decision when looking up a DFA state to see if it
 * already exists. We must make sure that all requests to add DFA states that
 * are equivalent result in the same shared DFA object. This is because lots of
 * threads will be trying to update the DFA at once. The
 * {@link #addDFAState} method also locks inside the DFA lock
 * but this time on the shared context cache when it rebuilds the
 * configurations' {@link PredictionContext} objects using cached
 * subgraphs/nodes. No other locking occurs, even during DFA simulation. This is
 * safe as long as we can guarantee that all threads referencing
 * {@code s.edge[t]} get the same physical target {@link DFAState}, or
 * {@code null}. Once into the DFA, the DFA simulation does not reference the
 * {@link DFA#states} map. It follows the {@link DFAState#edges} field to new
 * targets. The DFA simulator will either find {@link DFAState#edges} to be
 * {@code null}, to be non-{@code null} and {@code dfa.edges[t]} null, or
 * {@code dfa.edges[t]} to be non-null. The
 * {@link #addDFAEdge} method could be racing to set the field
 * but in either case the DFA simulator works; if {@code null}, and requests ATN
 * simulation. It could also race trying to get {@code dfa.edges[t]}, but either
 * way it will work because it's not doing a test and set operation.</p>
 *
 * <p>
 * <strong>Starting with SLL then failing to combined SLL/LL (Two-Stage
 * Parsing)</strong></p>
 *
 * <p>
 * Sam pointed out that if SLL does not give a syntax error, then there is no
 * point in doing full LL, which is slower. We only have to try LL if we get a
 * syntax error. For maximum speed, Sam starts the parser set to pure SLL
 * mode with the {@link BailErrorStrategy}:</p>
 *
 * <pre>
 * parser.{@link Parser#getInterpreter() getInterpreter()}.{@link #setPredictionMode setPredictionMode}{@code (}{@link PredictionMode#SLL}{@code )};
 * parser.{@link Parser#setErrorHandler setErrorHandler}(new {@link BailErrorStrategy}());
 * </pre>
 *
 * <p>
 * If it does not get a syntax error, then we're done. If it does get a syntax
 * error, we need to retry with the combined SLL/LL strategy.</p>
 *
 * <p>
 * The reason this works is as follows. If there are no SLL conflicts, then the
 * grammar is SLL (at least for that input set). If there is an SLL conflict,
 * the full LL analysis must yield a set of viable alternatives which is a
 * subset of the alternatives reported by SLL. If the LL set is a singleton,
 * then the grammar is LL but not SLL. If the LL set is the same size as the SLL
 * set, the decision is SLL. If the LL set has size &gt; 1, then that decision
 * is truly ambiguous on the current input. If the LL set is smaller, then the
 * SLL conflict resolution might choose an alternative that the full LL would
 * rule out as a possibility based upon better context information. If that's
 * the case, then the SLL parse will definitely get an error because the full LL
 * analysis says it's not viable. If SLL conflict resolution chooses an
 * alternative within the LL set, them both SLL and LL would choose the same
 * alternative because they both choose the minimum of multiple conflicting
 * alternatives.</p>
 *
 * <p>
 * Let's say we have a set of SLL conflicting alternatives {@code {1, 2, 3}} and
 * a smaller LL set called <em>s</em>. If <em>s</em> is {@code {2, 3}}, then SLL
 * parsing will get an error because SLL will pursue alternative 1. If
 * <em>s</em> is {@code {1, 2}} or {@code {1, 3}} then both SLL and LL will
 * choose the same alternative because alternative one is the minimum of either
 * set. If <em>s</em> is {@code {2}} or {@code {3}} then SLL will get a syntax
 * error. If <em>s</em> is {@code {1}} then SLL will succeed.</p>
 *
 * <p>
 * Of course, if the input is invalid, then we will get an error for sure in
 * both SLL and LL parsing. Erroneous input will therefore require 2 passes over
 * the input.</p>
 */
class ParserATNSimulator extends ATNSimulator
{
    /** @var \ANTLR\v4\Runtime\DFA\DFA[] */
    public $decisionToDFA;
    /** @var \ANTLR\v4\Runtime\TokenStream */
    protected $_input;
    /** @var int */
    protected $_startIndex = 0;
    /** @var \ANTLR\v4\Runtime\ParserRuleContext */
    protected $_outerContext;
    /** @var \ANTLR\v4\Runtime\DFA\DFA */
    protected $_dfa;
    /** @var \ANTLR\v4\Runtime\Parser */
    private $parser;
    /** @var int */
    private $mode = PredictionMode::LL;
    /**
     * Each prediction operation uses a cache for merge of prediction contexts.
     * Don't keep around as it wastes huge amounts of memory. DoubleKeyMap
     * isn't synchronized but we're ok since two threads shouldn't reuse same
     * parser/atnsim object because it can only handle one input at a time.
     * This maps graphs a and b to merged result c. (a,b)&rarr;c. We can avoid
     * the merge if we ever see a and b again.  Note that (b,a)&rarr;c should
     * also be examined during cache lookup.
     *
     * @var \ANTLR\v4\Runtime\ATN\ParserATNSimulator\MergeCache
     */
    private $mergeCache;

    public function __construct(Parser $parser, ATN $atn, array $decisionToDFA, PredictionContextCache $sharedContextCache)
    {
        parent::__construct($atn, $sharedContextCache);

        $this->parser = $parser;
        $this->decisionToDFA = $decisionToDFA;
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function clearDFA(): void
    {
        for ($d = 0; $d < count($this->decisionToDFA); $d++) {
            $this->decisionToDFA[$d] = new DFA($this->atn->getDecisionState($d), $d);
        }
    }

    public function adaptivePredict(TokenStream $input, int $decision, ParserRuleContext $outerContext): int
    {
        $this->_input = $input;
        $this->_startIndex = $input->index();
        $this->_outerContext = $outerContext;
        $this->_dfa = $this->decisionToDFA[$decision];

        $dfa = $this->_dfa;
        $idx = $this->_startIndex;
        $mark = $input->mark();

        $EMPTY_PARSER_RULE_CONTEXT = ParserRuleContext::createEmpty();

        // Now we are certain to have a specific decision's DFA
        // But, do we still need an initial state?
        try {
            $s0 = null;
            if ($dfa->isPrecedenceDfa()) {
                // the start state for a precedence DFA depends on the current
                // parser precedence, and is provided by a DFA method.
                $s0 = $dfa->getPrecedenceStartState($this->parser->getPrecedence());
            } else {
                // the start state for a "regular" DFA is just s0
                $s0 = $dfa->s0;
            }

            if ($s0 === null) {
                if ($outerContext === null) {
                    $outerContext = $EMPTY_PARSER_RULE_CONTEXT;
                }

                $s0_closure = $this->computeStartState($dfa->atnStartState, $EMPTY_PARSER_RULE_CONTEXT, false);
                if ($dfa->isPrecedenceDfa()) {
                    //If this is a precedence DFA, we use applyPrecedenceFilter
                    //to convert the computed start state to a precedence start
                    //state. We then use DFA.setPrecedenceStartState to set the
                    //appropriate start state for the precedence level rather
                    //than simply setting DFA.s0.
                    $dfa->s0->configs = $s0_closure; // not used for prediction but useful to know start configs anyway
                    $s0_closure = $this->applyPrecedenceFilter($s0_closure);
                    $s0 = $this->addDFAState($dfa, new DFAState($s0_closure));
                    $dfa->setPrecedenceStartState($this->parser->getPrecedence(), $s0);
                } else {
                    $s0 = $this->addDFAState($dfa, new DFAState($s0_closure));
                    $dfa->s0 = $s0;
                }
            }

            $ret = $this->execATN($dfa, $s0, $input, $idx, $outerContext);
            return $ret;
        }
        finally {
            $this->mergeCache = null;
            $this->_dfa = null;

            $input->seek($idx);
            $input->release($mark);
        }
    }

    protected function computeStartState(ATNState $p, RuleContext $ctx, bool $fullCtx): ATNConfigSet
    {
        // always at least the implicit call to start rule
        $initialContext = PredictionContext::fromRuleContext($this->atn, $ctx);

        $configs = new ATNConfigSet($fullCtx);
        for ($i = 0; $i < $p->getNumberOfTransitions(); $i++) {
            $target = $p->transition($i)->target;
            $c = new ATNConfig($target, $i + 1, $initialContext, 0, SemanticContext::NONE());
            $closureBusy = new Set();

            $this->closure($c, $configs, $closureBusy, true, $fullCtx, false);
        }

        return $configs;
    }

    /**
     * This method transforms the start state computed by
     * {@link #computeStartState} to the special start state used by a
     * precedence DFA for a particular precedence value. The transformation
     * process applies the following changes to the start state's configuration
     * set.
     *
     * <ol>
     * <li>Evaluate the precedence predicates for each configuration using
     * {@link SemanticContext#evalPrecedence}.</li>
     * <li>When {@link ATNConfig#isPrecedenceFilterSuppressed} is {@code false},
     * remove all configurations which predict an alternative greater than 1,
     * for which another configuration that predicts alternative 1 is in the
     * same ATN state with the same prediction context. This transformation is
     * valid for the following reasons:
     * <ul>
     * <li>The closure block cannot contain any epsilon transitions which bypass
     * the body of the closure, so all states reachable via alternative 1 are
     * part of the precedence alternatives of the transformed left-recursive
     * rule.</li>
     * <li>The "primary" portion of a left recursive rule cannot contain an
     * epsilon transition, so the only way an alternative other than 1 can exist
     * in a state that is also reachable via alternative 1 is by nesting calls
     * to the left-recursive rule, with the outer calls not being at the
     * preferred precedence level. The
     * {@link ATNConfig#isPrecedenceFilterSuppressed} property marks ATN
     * configurations which do not meet this condition, and therefore are not
     * eligible for elimination during the filtering process.</li>
     * </ul>
     * </li>
     * </ol>
     *
     * <p>
     * The prediction context must be considered by this filter to address
     * situations like the following.
     * </p>
     * <code>
     * <pre>
     * grammar TA;
     * prog: statement* EOF;
     * statement: letterA | statement letterA 'b' ;
     * letterA: 'a';
     * </pre>
     * </code>
     * <p>
     * If the above grammar, the ATN state immediately before the token
     * reference {@code 'a'} in {@code letterA} is reachable from the left edge
     * of both the primary and closure blocks of the left-recursive rule
     * {@code statement}. The prediction context associated with each of these
     * configurations distinguishes between them, and prevents the alternative
     * which stepped out to {@code prog} (and then back in to {@code statement}
     * from being eliminated by the filter.
     * </p>
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs The configuration set computed by
     * {@link #computeStartState} as the start state for the DFA.
     *
     * @return \ANTLR\v4\Runtime\ATN\ATNConfigSet The transformed configuration set representing the start state
     * for a precedence DFA at a particular precedence level (determined by
     * calling {@link Parser#getPrecedence}).
     */
    protected function applyPrecedenceFilter(ATNConfigSet $configs): ATNConfigSet
    {
        /** @var \ANTLR\v4\Runtime\ATN\PredictionContext[] $statesFromAlt1 */
        $statesFromAlt1 = [];

        $configSet = new ATNConfigSet($configs->fullCtx);

        foreach ($configs as $config) {
            if ($config->alt !== 1) {
                // handle alt 1 first
                continue;
            }

            $updatedContext = $config->semanticContext->evaluatePrecedence($this->parser, $this->_outerContext);
            if ($updatedContext === null) {
                // the configuration was eliminated
                continue;
            }

            $statesFromAlt1[$config->state->stateNumber] = $config->context;
            if ($updatedContext !== $config->semanticContext) {
                $configSet->add(new ATNConfig(
                    $config->state, $config->alt, $config->context, $config->reachesIntoOuterContext, $updatedContext
                ), $this->mergeCache);
            } else {
                $configSet->add($config, $this->mergeCache);
            }
        }

        foreach ($configs as $config) {
            if ($config->alt === 1) {
                // already handled
                continue;
            }

            if (!$config->isPrecedenceFilterSuppressed()) {
                // In the future, this elimination step could be updated to also
                // filter the prediction context for alternatives predicting alt>1
                // (basically a graph subtraction algorithm).
                $context = null;
                if (isset($statesFromAlt1[$config->state->stateNumber])) {
                    $context = $statesFromAlt1[$config->state->stateNumber];
                }

                if ($context !== null && $context->equals($config->context)) {
                    // eliminated
                    continue;
                }
            }

            $configSet->add($config, $this->mergeCache);
        }

        return $configSet;
    }

    /**
     * Performs ATN simulation to compute a predicted alternative based
     * upon the remaining input, but also updates the DFA cache to avoid
     * having to traverse the ATN again for the same input sequence.
     *
     * There are some key conditions we're looking for after computing a new
     * set of ATN configs (proposed DFA state):
     *  - if the set is empty, there is no viable alternative for current symbol
     *  - does the state uniquely predict an alternative?
     *  - does the state have a conflict that would prevent us from
     *    putting it on the work list?
     *
     * We also have some key operations to do:
     *  - add an edge from previous DFA state to potentially new DFA state, D,
     *    upon current symbol but only if adding to work list, which means in all
     *    cases except no viable alternative (and possibly non-greedy decisions?)
     *  - collecting predicates and adding semantic context to DFA accept states
     *  - adding rule context to context-sensitive DFA accept states
     *  - consuming an input symbol
     *  - reporting a conflict
     *  - reporting an ambiguity
     *  - reporting a context sensitivity
     *  - reporting insufficient predicates
     *
     * cover these cases:
     * - dead end
     * - single alt
     * - single alt + preds
     * - conflict
     * - conflict + preds
     */
    protected function execATN(
        DFA $dfa,
        DFAState $s0,
        TokenStream $input,
        int $startIndex,
        ParserRuleContext $outerContext
    ): int
    {
        $previousD = $s0;

        $t = $input->LA(1);

        while (true) {
            /** @var DFAState $D */
            $D = $this->getExistingTargetState($previousD, $t);
            if ($D === null) {
                $D = $this->computeTargetState($dfa, $previousD, $t);
            }

            if ($D === self::createError()) {
                // if any configs in previous dipped into outer context, that
                // means that input up to t actually finished entry rule
                // at least for SLL decision. Full LL doesn't dip into outer
                // so don't need special case.
                // We will get an error no matter what so delay until after
                // decision; better error message. Also, no reachable target
                // ATN states in SLL implies LL will also get nowhere.
                // If conflict in states that dip out, choose min since we
                // will get error no matter what.
                $e = $this->noViableAlt($input, $outerContext, $previousD->configs, $startIndex);

                $input->seek($startIndex);

                $alt = $this->getSynValidOrSemInvalidAltThatFinishedDecisionEntryRule($previousD->configs, $outerContext);
                if ($alt !== ATN::INVALID_ALT_NUMBER) {
                    return $alt;
                }

                throw $e;
            }

            if ($D->requiresFullContext && $this->mode !== PredictionMode::SLL) {
                // IF PREDS, MIGHT RESOLVE TO SINGLE ALT => SLL (or syntax error)
                $conflictingAlts = $D->configs->conflictingAlts;
                if ($D->predicates !== null) {
                    $conflictIndex = $input->index();
                    if ($conflictIndex !== $startIndex) {
                        $input->seek($startIndex);
                    }

                    $conflictingAlts = $this->evalSemanticContextForPredictions($D->predicates, $outerContext, true);
                    if ($conflictingAlts->cardinality() === 1) {
                        return $conflictingAlts->nextSetBit(0);
                    }

                    if ($conflictIndex !== $startIndex) {
                        // restore the index so reporting the fallback to full
                        // context occurs with the index at the correct spot
                        $input->seek($startIndex);
                    }
                }

                $s0_closure = $this->computeStartState($dfa->atnStartState, $outerContext, true);
                $this->reportAttemptingFullContext($dfa, $conflictingAlts, $D->configs, $startIndex, $input->index());
                return $this->execATNWithFullContext($dfa, $D, $s0_closure, $input, $startIndex, $outerContext);
            }

            if ($D->isAcceptState) {
                if ($D->predicates === null) {
                    return $D->predication;
                }

                $stopIndex = $input->index();
                $input->seek($startIndex);

                $alts = $this->evalSemanticContextForPredictions($D->predicates, $outerContext, true);
                switch ($alts->cardinality()) {
                    case 0:
                        throw $this->noViableAlt($input, $outerContext, $D->configs, $startIndex);
                    case 1:
                        return $alts->nextSetBit(0);
                    default:
                        // report ambiguity after predicate evaluation to make sure the correct
                        // set of ambig alts is reported.
                        $this->reportAmbiguity($dfa, $D, $startIndex, $stopIndex, false, $alts, $D->configs);
                        return $alts->nextSetBit(0);
                }
            }

            $previousD = $D;

            if ($t !== Token::EOF) {
                $input->consume();
                $t = $input->LA(1);
            }
        }
    }

    /**
     * Get an existing target state for an edge in the DFA. If the target state
     * for the edge has not yet been computed or is otherwise not available,
     * this method returns {@code null}.
     *
     * @param \ANTLR\v4\Runtime\DFA\DFAState $previousD The current DFA state
     * @param int $t The next input symbol
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState|null The existing target DFA state for the given input symbol
     * {@code t}, or {@code null} if the target state for this edge is not
     * already cached
     */
    protected function getExistingTargetState(DFAState $previousD, int $t): ?DFAState
    {
        $edges = $previousD->edges;
        if ($edges === null || $t + 1 < 0 || $t + 1 >= count($edges)) {
            return null;
        }

        return $edges[$t + 1];
    }

    /**
     * Compute a target state for an edge in the DFA, and attempt to add the
     * computed state and corresponding edge to the DFA.
     *
     * @param \ANTLR\v4\Runtime\DFA\DFA $dfa The DFA
     * @param \ANTLR\v4\Runtime\DFA\DFAState $previousD The current DFA state
     * @param int $t The next input symbol
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState The computed target DFA state for the given input symbol
     * {@code t}. If {@code t} does not lead to a valid DFA state, this method
     * returns {@link #ERROR}.
     */
    protected function computeTargetState(DFA $dfa, DFAState $previousD, int $t): DFAState
    {
        $reach = $this->computeReachSet($previousD->configs, $t, false);
        if ($reach === null) {
            $ERROR = self::createError();
            $this->addDFAEdge($dfa, $previousD, $t, $ERROR);
            return $ERROR;
        }

        // create new target state; we'll add to DFA after it's complete
        $D = new DFAState($reach);

        $predictedAlt = self::getUniqueAlt($reach);
        if ($predictedAlt !== ATN::INVALID_ALT_NUMBER) {
            // NO CONFLICT, UNIQUELY PREDICTED ALT
            $D->isAcceptState = true;
            $D->configs->uniqueAlt = $predictedAlt;
            $D->predication = $predictedAlt;
        } else if (PredictionMode::hasSLLConflictTerminatingPrediction($this->mode, $reach)) {
            // MORE THAN ONE VIABLE ALTERNATIVE
            $D->configs->conflictingAlts = $this->getConflictingAlts($reach);
            $D->requiresFullContext = true;
            // in SLL-only mode, we will stop at this state and return the minimum alt
            $D->isAcceptState = true;
            $D->predication = $D->configs->conflictingAlts->nextSetBit(0);
        }

        if ($D->isAcceptState && $D->configs->hasSemanticContext) {
            $this->predicateDFAState($D, $this->atn->getDecisionState($dfa->decision));
            if ($D->predicates !== null) {
                $D->predication = ATN::INVALID_ALT_NUMBER;
            }
        }

        // all adds to dfa are done after we've created full D state
        $D = $this->addDFAEdge($dfa, $previousD, $t, $D);

        return $D;
    }

    private function computeReachSet(ATNConfigSet $closure, int $t, bool $fullCtx): ?ATNConfigSet
    {
        if ($this->mergeCache === null) {
            $this->mergeCache = new MergeCache();
        }

        $intermediate = new ATNConfigSet($fullCtx);

        // Configurations already in a rule stop state indicate reaching the end
        // of the decision rule (local context) or end of the start rule (full
        // context). Once reached, these configurations are never updated by a
        // closure operation, so they are handled separately for the performance
        // advantage of having a smaller intermediate set when calling closure.
        //
        // For full-context reach operations, separate handling is required to
        // ensure that the alternative matching the longest overall sequence is
        // chosen when multiple such configurations can match the input.

        $skippedStopStates = null;

        // First figure out where we can reach on input t
        foreach ($closure as $c) {
            if ($c->state instanceof RuleStopState) {
                assert($c->context->isEmpty());

                if ($fullCtx || $t === IntStream::EOF) {
                    if ($skippedStopStates === null) {
                        $skippedStopStates = [];
                    }

                    $skippedStopStates[] = $c;
                }

                continue;
            }

            $n = $c->state->getNumberOfTransitions();
            for ($ti = 0; $ti < $n; $ti++) {
                $trans = $c->state->transition($ti);
                $target = $this->getReachableTarget($trans, $t);
                if ($target !== null) {
                    $intermediate->add(new ATNConfig(
                        $target, $c->alt, $c->context, $c->reachesIntoOuterContext, $c->semanticContext
                    ), $this->mergeCache);
                }
            }
        }

        // Now figure out where the reach operation can take us...
        $reach = null;

        // This block optimizes the reach operation for intermediate sets which
        // trivially indicate a termination state for the overall
        // adaptivePredict operation.
        //
        // The conditions assume that intermediate
        // contains all configurations relevant to the reach set, but this
        // condition is not true when one or more configurations have been
        // withheld in skippedStopStates, or when the current symbol is EOF.
        if ($skippedStopStates === null && $t !== Token::EOF) {
            if ($intermediate->size() === 1) {
                // Don't pursue the closure if there is just one state.
                // It can only have one alternative; just add to result
                // Also don't pursue the closure if there is unique alternative
                // among the configurations.
                $reach = $intermediate;
            } else if (self::getUniqueAlt($intermediate) !== ATN::INVALID_ALT_NUMBER) {
                // Also don't pursue the closure if there is unique alternative
                // among the configurations.
                $reach = $intermediate;
            }
        }

        // If the reach set could not be trivially determined, perform a closure
        // operation on the intermediate set to compute its initial value.
        if ($reach === null) {
            $reach = new ATNConfigSet($fullCtx);
            $closureBusy = new Set();
            $treatEofAsEpsilon = $t === Token::EOF;
            // (!) Ten if jest prawdopodobnie nie potrzebny
            if ($intermediate->configs !== null) {
                foreach ($intermediate as $c) {
                    $this->closure($c, $reach, $closureBusy, false, $fullCtx, $treatEofAsEpsilon);
                }
            }
        }

        if ($t === IntStream::EOF) {
            // After consuming EOF no additional input is possible, so we are
            // only interested in configurations which reached the end of the
            // decision rule (local context) or end of the start rule (full
            // context). Update reach to contain only these configurations. This
            // handles both explicit EOF transitions in the grammar and implicit
            // EOF transitions following the end of the decision or start rule.
            //
            // When reach==intermediate, no closure operation was performed. In
            // this case, removeAllConfigsNotInRuleStopState needs to check for
            // reachable rule stop states as well as configurations already in
            // a rule stop state.
            //
            // This is handled before the configurations in skippedStopStates,
            // because any configurations potentially added from that list are
            // already guaranteed to meet this condition whether or not it's
            // required.
            $reach = $this->removeAllConfigsNotInRuleStopState($reach, $reach === $intermediate);
        }

        // If skippedStopStates is not null, then it contains at least one
        // configuration. For full-context reach operations, these
        // configurations reached the end of the start rule, in which case we
        // only add them back to reach if no configuration during the current
        // closure operation reached such a state. This ensures adaptivePredict
        // chooses an alternative matching the longest overall sequence when
        // multiple alternatives are viable.
        if ($skippedStopStates !== null && (!$fullCtx || !PredictionMode::hasConfigInRuleStopState($reach))) {
            assert(!empty($skippedStopStates));
            foreach ($skippedStopStates as $c) {
                $reach->add($c, $this->mergeCache);
            }
        }

        if ($reach->isEmpty()) {
            return null;
        }

        return $reach;
    }

    protected function getReachableTarget(Transition $trans, int $ttype): ?ATNState
    {
        if ($trans->matches($ttype, 0, $this->atn->maxTokenType)) {
            return $trans->target;
        }

        return null;
    }

    /**
     * Return a configuration set containing only the configurations from
     * {@code configs} which are in a {@link RuleStopState}. If all
     * configurations in {@code configs} are already in a rule stop state, this
     * method simply returns {@code configs}.
     *
     * <p>When {@code lookToEndOfRule} is true, this method uses
     * {@link ATN#nextTokens} for each configuration in {@code configs} which is
     * not already in a rule stop state to see if a rule stop state is reachable
     * from the configuration via epsilon-only transitions.</p>
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs the configuration set to update
     * @param bool $lookToEndOfRule when true, this method checks for rule stop states
     * reachable by epsilon-only transitions from each configuration in
     * {@code configs}.
     *
     * @return {@code configs} if all configurations in {@code configs} are in a
     * rule stop state, otherwise return a new configuration set containing only
     * the configurations from {@code configs} which are in a rule stop state
     */
    protected function removeAllConfigsNotInRuleStopState(ATNConfigSet $configs, bool $lookToEndOfRule): ATNConfigSet
    {
        if (PredictionMode::allConfigsInRuleStopStates($configs)) {
            return $configs;
        }

        $result = new ATNConfigSet($configs->fullCtx);

        foreach ($configs as $c) {
            if ($c->state instanceof RuleStopState) {
                $result->add($c, $this->mergeCache);
                continue;
            }

            if ($lookToEndOfRule && $c->state->onlyHasEpsilonTransitions()) {
                $nextTokens = $this->atn->nextTokensForATNState($c->state);
                if ($nextTokens->contains(Token::EPSILON)) {
                    $endOfRuleState = $this->atn->ruleToStopState[$c->state->ruleIndex];

                    $result->add(new ATNConfig(
                        $endOfRuleState, $c->alt, $c->context, $c->reachesIntoOuterContext, $c->semanticContext
                    ), $this->mergeCache);
                }
            }
        }

        return $result;
    }

    protected function predicateDFAState(DFAState $dfaState, DecisionState $decisionState): void
    {
        // We need to test all predicates, even in DFA states that
        // uniquely predict alternative.
        $nalts = $decisionState->getNumberOfTransitions();

        // Update DFA so reach becomes accept state with (predicate,alt)
        // pairs if preds found for conflicting alts
        $altsToCollectPredsFrom = $this->getConflictingAltsOrUniqueAlt($dfaState->configs);

        $altToPred = $this->getPredsForAmbigAlts($altsToCollectPredsFrom, $dfaState->configs, $nalts);
        if ($altToPred !== null) {
            $dfaState->predicates = $this->getPredicatePredictions($altsToCollectPredsFrom, $altToPred);
            $dfaState->predication = ATN::INVALID_ALT_NUMBER; // make sure we use preds
        } else {
            // There are preds in configs but they might go away
            // when OR'd together like {p}? || NONE == NONE. If neither
            // alt has preds, resolve to min alt
            $dfaState->predication = $altsToCollectPredsFrom->nextSetBit(0);
        }
    }

    protected function execATNWithFullContext(
        DFA $dfa,
        DFAState $D,
        ATNConfigSet $s0,
        TokenStream $input,
        int $startIndex,
        ParserRuleContext $outerContext
    ): int
    {
        $fullCtx = true;
        $foundExactAmbig = false;
        $reach = null;
        $previous = $s0;
        $input->seek($startIndex);
        $t = $input->LA(1);
        $predictedAlt = 0;

        while (true) {
            $reach = $this->computeReachSet($previous, $t, $fullCtx);
            if ($reach === null) {
                // if any configs in previous dipped into outer context, that
                // means that input up to t actually finished entry rule
                // at least for LL decision. Full LL doesn't dip into outer
                // so don't need special case.
                // We will get an error no matter what so delay until after
                // decision; better error message. Also, no reachable target
                // ATN states in SLL implies LL will also get nowhere.
                // If conflict in states that dip out, choose min since we
                // will get error no matter what.
                $e = $this->noViableAlt($input, $outerContext, $previous, $startIndex);

                $input->seek($startIndex);
                $alt = $this->getSynValidOrSemInvalidAltThatFinishedDecisionEntryRule($previous, $outerContext);
                if ($alt !== ATN::INVALID_ALT_NUMBER) {
                    return $alt;
                }

                throw $e;
            }

            $altSubSets = PredictionMode::getConflictingAltSubsets($reach);

            $reach->uniqueAlt = self::getUniqueAlt($reach);
            // unique prediction?
            if ($reach->uniqueAlt !== ATN::INVALID_ALT_NUMBER) {
                $predictedAlt = $reach->uniqueAlt;
                break;
            }

            if ($this->mode !== PredictionMode::LL_EXACT_AMBIG_DETECTION) {
                $predictedAlt = PredictionMode::resolvesToJustOneViableAlt($altSubSets);
                if ($predictedAlt !== ATN::INVALID_ALT_NUMBER) {
                    break;
                }
            } else {
                // In exact ambiguity mode, we never try to terminate early.
                // Just keeps scarfing until we know what the conflict is
                if (PredictionMode::allSubsetsConflict($altSubSets) && PredictionMode::allSubsetsEqual($altSubSets)) {
                    $foundExactAmbig = true;
                    $predictedAlt = PredictionMode::getSingleViableAlt($altSubSets);
                    break;
                }
                // else there are multiple non-conflicting subsets or
                // we're not sure what the ambiguity is yet.
                // So, keep going.
            }

            $previous = $reach;
            if ($t !== IntStream::EOF) {
                $input->consume();
                $t = $input->LA(1);
            }
        }

        // If the configuration set uniquely predicts an alternative,
        // without conflict, then we know that it's a full LL decision
        // not SLL.
        if ($reach->uniqueAlt !== ATN::INVALID_ALT_NUMBER) {
            $this->reportContextSensitivity($dfa, $predictedAlt, $reach, $startIndex, $input->index());
            return $predictedAlt;
        }

        // We do not check predicates here because we have checked them
        // on-the-fly when doing full context prediction.

        // In non-exact ambiguity detection mode, we might	actually be able to
        // detect an exact ambiguity, but I'm not going to spend the cycles
        // needed to check. We only emit ambiguity warnings in exact ambiguity
        // mode.
        //
        // For example, we might know that we have conflicting configurations.
        // But, that does not mean that there is no way forward without a
        // conflict. It's possible to have nonconflicting alt subsets as in:
        //
        //   LL altSubSets=[{1, 2}, {1, 2}, {1}, {1, 2}]
        //
        // from
        //
        //   [(17,1,[5 $]), (13,1,[5 10 $]), (21,1,[5 10 $]), (11,1,[$]),
        //    (13,2,[5 10 $]), (21,2,[5 10 $]), (11,2,[$])]
        //
        // In this case, (17,1,[5 $]) indicates there is some next sequence that
        // would resolve this without conflict to alternative 1. Any other viable
        // next sequence, however, is associated with a conflict.  We stop
        // looking for input because no amount of further lookahead will alter
        // the fact that we should predict alternative 1.  We just can't say for
        // sure that there is an ambiguity without looking further.

        $this->reportAmbiguity($dfa, $D, $startIndex, $input->index(), $foundExactAmbig, $reach->getAlts(), $reach);

        return $predictedAlt;
    }

    protected function getPredsForAmbigAlts(BitSet $ambigAlts, ATNConfigSet $configs, int $nalts): ?array
    {
        // REACH=[1|1|[]|0:0, 1|2|[]|0:1]
        /* altToPred starts as an array of all null contexts. The entry at index i
         * corresponds to alternative i. altToPred[i] may have one of three values:
         *   1. null: no ATNConfig c is found such that c.alt==i
         *   2. SemanticContext.NONE: At least one ATNConfig c exists such that
         *      c.alt==i and c.semanticContext==SemanticContext.NONE. In other words,
         *      alt i has at least one unpredicated config.
         *   3. Non-NONE Semantic Context: There exists at least one, and for all
         *      ATNConfig c such that c.alt==i, c.semanticContext!=SemanticContext.NONE.
         *
         * From this, it is clear that NONE||anything==NONE.
         */
        $altToPred = array_pad([], $nalts + 1, null);

        foreach ($configs as $c) {
            if ($ambigAlts->get($c->alt)) {
                $altToPred[$c->alt] = SemanticContext::or($altToPred[$c->alt], $c->semanticContext);
            }
        }

        $nPredAlts = 0;
        for ($i = 0; $i <= $nalts; $i++) {
            if ($altToPred[$i] === null) {
                $altToPred[$i] = SemanticContext::NONE();
            } else if ($altToPred[$i] !== SemanticContext::NONE()) {
                $nPredAlts++;
            }
        }

        // nonambig alts are null in altToPred
        if ($nPredAlts === 0) {
            $altToPred = null;
        }

        return $altToPred;
    }

    protected function getPredicatePredictions(?BitSet $ambigAlts, array $altToPred): ?array
    {
        $pairs = [];

        $containsPredicate = false;
        for ($i = 0; $i < count($altToPred); $i++) {
            $pred = $altToPred[$i];

            // unpredicated is indicated by SemanticContext.NONE
            assert($pred !== null);

            if ($ambigAlts !== null && $ambigAlts->get($i)) {
                $pairs[] = new PredPrediction($pred, $i);
            }

            if ($pred !== SemanticContext::NONE()) {
                $containsPredicate = true;
            }
        }

        if (!$containsPredicate) {
            return null;
        }

        return $pairs;
    }

    /**
     * This method is used to improve the localization of error messages by
     * choosing an alternative rather than throwing a
     * {@link NoViableAltException} in particular prediction scenarios where the
     * {@link #ERROR} state was reached during ATN simulation.
     *
     * <p>
     * The default implementation of this method uses the following
     * algorithm to identify an ATN configuration which successfully parsed the
     * decision entry rule. Choosing such an alternative ensures that the
     * {@link ParserRuleContext} returned by the calling rule will be complete
     * and valid, and the syntax error will be reported later at a more
     * localized location.</p>
     *
     * <ul>
     * <li>If a syntactically valid path or paths reach the end of the decision rule and
     * they are semantically valid if predicated, return the min associated alt.</li>
     * <li>Else, if a semantically invalid but syntactically valid path exist
     * or paths exist, return the minimum associated alt.
     * </li>
     * <li>Otherwise, return {@link ATN#INVALID_ALT_NUMBER}.</li>
     * </ul>
     *
     * <p>
     * In some scenarios, the algorithm described above could predict an
     * alternative which will result in a {@link FailedPredicateException} in
     * the parser. Specifically, this could occur if the <em>only</em> configuration
     * capable of successfully parsing to the end of the decision rule is
     * blocked by a semantic predicate. By choosing this alternative within
     * {@link #adaptivePredict} instead of throwing a
     * {@link NoViableAltException}, the resulting
     * {@link FailedPredicateException} in the parser will identify the specific
     * predicate which is preventing the parser from successfully parsing the
     * decision rule, which helps developers identify and correct logic errors
     * in semantic predicates.
     * </p>
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs The ATN configurations which were valid immediately before
     * the {@link #ERROR} state was reached
     * @param \ANTLR\v4\Runtime\ParserRuleContext outerContext The is the \gamma_0 initial parser context from the paper
     * or the parser stack at the instant before prediction commences.
     *
     * @return int The value to return from {@link #adaptivePredict}, or
     * {@link ATN#INVALID_ALT_NUMBER} if a suitable alternative was not
     * identified and {@link #adaptivePredict} should report an error instead.
     */
    protected function getSynValidOrSemInvalidAltThatFinishedDecisionEntryRule(
        ATNConfigSet $configs,
        ParserRuleContext $outerContext
    ): int {
        list(
            $semValidConfigs,
            $semInvalidConfigs
        ) = $this->splitAccordingToSemanticValidity($configs, $outerContext);

        $alt = $this->getAltThatFinishedDecisionEntryRule($semValidConfigs);

        if ($alt !== ATN::INVALID_ALT_NUMBER) {
            // semantically/syntactically viable path exists
            return $alt;
        }

        // Is there a syntactically valid path with a failed pred?
        if ($semValidConfigs->size() > 0) {
            $alt = $this->getAltThatFinishedDecisionEntryRule($semInvalidConfigs);
            if ($alt !== ATN::INVALID_ALT_NUMBER) {
                return $alt;
            }
        }

        return ATN::INVALID_ALT_NUMBER;
    }

    protected function getAltThatFinishedDecisionEntryRule(ATNConfigSet $configs): int
    {
        $alts = new IntervalSet();
        foreach ($configs as $c) {
            if ($c->getOuterContextDepth() > 0 || ($c->state instanceof RuleStopState && $c->context->hasEmptyPath())) {
                $alts->add($c->alt);
            }
        }

        if ($alts->size() === 0) {
            return ATN::INVALID_ALT_NUMBER;
        }

        return $alts->getMinElement();
    }

    /**
     * Walk the list of configurations and split them according to
     * those that have preds evaluating to true/false.  If no pred, assume
     * true pred and include in succeeded set.  Returns Pair of sets.
     *
     * Create a new set so as not to alter the incoming parameter.
     *
     * Assumption: the input stream has been restored to the starting point
     * prediction, which is where predicates need to evaluate.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs
     * @param \ANTLR\v4\Runtime\ParserRuleContext $outerContext
     *
     * @return \ANTLR\v4\Runtime\ATN\ATNConfigSet[]
     */
    protected function splitAccordingToSemanticValidity(ATNConfigSet $configs, ParserRuleContext $outerContext): array
    {
        $succeeded = new ATNConfigSet($configs->fullCtx);
        $failed = new ATNConfigSet($configs->fullCtx);

        foreach ($configs as $config) {
            if ($config->semanticContext !== SemanticContext::NONE()) {
                $predicateEvaluationResult = $this->evalSemanticContext(
                    $config->semanticContext, $outerContext, $config->alt, $configs->fullCtx
                );

                if ($predicateEvaluationResult) {
                    $succeeded->add($config);
                }
                else {
                    $failed->add($config);
                }
            }
            else {
                $succeeded->add($config);
            }
        }

        return [$succeeded, $failed];
    }

    /**
     * Look through a list of predicate/alt pairs, returning alts for the
     * pairs that win. A {@code NONE} predicate indicates an alt containing an
     * unpredicated config which behaves as "always true." If !complete
     * then we stop at the first predicate that evaluates to true. This
     * includes pairs with null predicates.
     *
     * @param \ANTLR\v4\Runtime\DFA\PredPrediction[] $predPredictions
     * @param \ANTLR\v4\Runtime\ParserRuleContext $outerContext
     * @param bool $complete
     *
     * @return \ANTLR\v4\Runtime\Misc\BitSet
     */
    protected function evalSemanticContextForPredictions(
        array $predPredictions,
        ParserRuleContext $outerContext,
        bool $complete
    ): BitSet {
        $predictions = new BitSet();

        foreach ($predPredictions as $pair) {
            if ($pair->pred === SemanticContext::NONE()) {
                $predictions->set($pair->alt);
                if (!$complete) {
                    break;
                }

                continue;
            }

            $predicateEvaluationResult = $this->evalSemanticContext($pair->pred, $outerContext, $pair->alt, false);
            if ($predicateEvaluationResult) {
                $predictions->set($pair->alt);
                if (!$complete) {
                    break;
                }
            }
        }

        return $predictions;
    }

    /**
     * Evaluate a semantic context within a specific parser context.
     *
     * <p>
     * This method might not be called for every semantic context evaluated
     * during the prediction process. In particular, we currently do not
     * evaluate the following but it may change in the future:</p>
     *
     * <ul>
     * <li>Precedence predicates (represented by
     * {@link SemanticContext.PrecedencePredicate}) are not currently evaluated
     * through this method.</li>
     * <li>Operator predicates (represented by {@link SemanticContext.AND} and
     * {@link SemanticContext.OR}) are evaluated as a single semantic
     * context, rather than evaluating the operands individually.
     * Implementations which require evaluation results from individual
     * predicates should override this method to explicitly handle evaluation of
     * the operands within operator predicates.</li>
     * </ul>
     *
     * @param \ANTLR\v4\Runtime\ATN\SemanticContext $pred The semantic context to evaluate
     * @param \ANTLR\v4\Runtime\ParserRuleContext $parserCallStack The parser context in which to evaluate the
     * semantic context
     * @param int $alt The alternative which is guarded by {@code pred}
     * @param bool $fullCtx {@code true} if the evaluation is occurring during LL
     * prediction; otherwise, {@code false} if the evaluation is occurring
     * during SLL prediction
     *
     * @return bool
     */
    protected function evalSemanticContext(
        SemanticContext $pred,
        ParserRuleContext $parserCallStack,
        int $alt,
        bool $fullCtx
    ): bool {
        return $pred->evaluate($this->parser, $parserCallStack);
    }

    protected function closure(
        ATNConfig $config,
        ATNConfigSet $configs,
        Set $closureBusy,
        bool $collectPredicates,
        bool $fullCtx,
        bool $treatEofAsEpsilon
    ): void {
        $this->closureCheckingStopState(
            $config, $configs, $closureBusy, $collectPredicates, $fullCtx, 0 /* $initialDepth */, $treatEofAsEpsilon
        );

        assert(!$fullCtx || !$configs->dipsIntoOuterContext);
    }

    protected function closureCheckingStopState(
        ATNConfig $config,
        ATNConfigSet $configs,
        Set $closureBusy,
        bool $collectPredicates,
        bool $fullCtx,
        int $depth,
        bool $treatEofAsEpsilon
    ): void {
        if ($config->state instanceof RuleStopState) {
            // We hit rule end. If we have context info, use it
            // run thru all possible stack tops in ctx
            if (!$config->context->isEmpty()) {
                for ($i = 0; $i < $config->context->size(); $i++) {
                    if ($config->context->getReturnState($i) === PredictionContext::EMPTY_RETURN_STATE) {
                        if ($fullCtx) {
                            $configs->add(new ATNConfig(
                                $config->state,
                                $config->alt,
                                PredictionContext::createEmpty(),
                                $config->reachesIntoOuterContext,
                                $config->semanticContext
                            ), $this->mergeCache);
                        }
                        else {
                            // we have no context info, just chase follow links (if greedy)
                            $this->closure_(
                                $config, $configs, $closureBusy, $collectPredicates, $fullCtx, $depth, $treatEofAsEpsilon
                            );
                        }

                        continue;
                    }

                    $returnState = $this->atn->states[$config->context->getReturnState($i)];
                    $newContext  = $config->context->getParent($i); //"pop" return state

                    $c = new ATNConfig(
                        $returnState, $config->alt, $newContext, $config->reachesIntoOuterContext, $config->semanticContext
                    );
                    // While we have context to pop back from, we may have
                    // gotten that context AFTER having falling off a rule.
                    // Make sure we track that we are now out of context.
                    //
                    // This assignment also propagates the
                    // isPrecedenceFilterSuppressed() value to the new
                    // configuration.
                    $c->reachesIntoOuterContext = $config->reachesIntoOuterContext;

                    assert($depth > PHP_INT_MIN);

                    $this->closureCheckingStopState(
                        $c, $configs, $closureBusy, $collectPredicates, $fullCtx, $depth - 1, $treatEofAsEpsilon
                    );
                }

                return ;
            }
            else if ($fullCtx) {
                // reached end of start rule
                $configs->add($config, $this->mergeCache);
                return ;
            }
            else {
                // else if we have no context info, just chase follow links (if greedy)
                //trigger_error("FALLING off rule {$this->getRuleName($config->state->ruleIndex)}", E_USER_WARNING);
            }
        }

        $this->closure_($config, $configs, $closureBusy, $collectPredicates, $fullCtx, $depth, $treatEofAsEpsilon);
    }

    /** Do the actual work of walking epsilon edges */
    protected function closure_(
        ATNConfig $config,
        ATNConfigSet $configs,
        Set $closureBusy,
        bool $collectPredicates,
        bool $fullCtx,
        int $depth,
        bool $treatEofAsEpsilon
    ): void {
        $p = $config->state;
        // optimization
        if ( !$p->onlyHasEpsilonTransitions() ) {
            $configs->add($config, $this->mergeCache);
            // make sure to not return here, because EOF transitions can act as
            // both epsilon transitions and non-epsilon transitions.
        }

        for ($i = 0; $i < $p->getNumberOfTransitions(); $i++) {
            if ($i === 0 && $this->canDropLoopEntryEdgeInLeftRecursiveRule($config)) {
                continue;
            }

            $t = $p->transition($i);
            $continueCollecting = !($t instanceof ActionTransition) && $collectPredicates;
            $c = $this->getEpsilonTarget($config, $t, $continueCollecting, $depth === 0, $fullCtx, $treatEofAsEpsilon);

            if ($c !== null) {
                $newDepth = $depth;
                if ($config->state instanceof RuleStopState) {
                    assert(!$fullCtx);

                    // target fell off end of rule; mark resulting c as having dipped into outer context
                    // We can't get here if incoming config was rule stop and we had context
                    // track how far we dip into outer context.  Might
                    // come in handy and we avoid evaluating context dependent
                    // preds if this is > 0.
                    if ($this->_dfa !== null && $this->_dfa->isPrecedenceDfa()) {
                        if (!$t instanceof EpsilonTransition) {
                            throw new RuntimeException(sprintf(
                                'Expected $t to be instance of %s, %s given', EpsilonTransition::class, get_class($t)
                            ));
                        }

                        $outermostPrecedenceReturn = $t->outermostPrecedenceReturn();
                        if ($outermostPrecedenceReturn === $this->_dfa->atnStartState->ruleIndex) {
                            $c->setPrecedenceFilterSuppressed(true);
                        }
                    }

                    $c->reachesIntoOuterContext++;

                    if ($closureBusy->contains($c)) {
                        // avoid infinite recursion for right-recursive rules
                        continue;
                    }

                    $closureBusy->add($c);

                    $configs->dipsIntoOuterContext = true;
                    assert($newDepth > PHP_INT_MIN);
                    $newDepth--;
                }
                else {
                    if (!$t->isEpsilon()) {
                        if ($closureBusy->contains($c)) {
                            // avoid infinite recursion for EOF* and EOF+
                            continue;
                        }

                        $closureBusy->add($c);
                    }

                    if ($t instanceof RuleTransition) {
                        // latch when newDepth goes negative - once we step out of the entry context we can't return
                        if ($newDepth >= 0) {
                            $newDepth++;
                        }
                    }
                }

                $this->closureCheckingStopState(
                    $c, $configs, $closureBusy, $continueCollecting, $fullCtx, $newDepth, $treatEofAsEpsilon
                );
            }
        }
    }

    /**
     * Implements first-edge (loop entry) elimination as an optimization
     * during closure operations. See antlr/antlr4#1398.
     *
     * The optimization is to avoid adding the loop entry config when
     * the exit path can only lead back to the same
     * StarLoopEntryState after popping context at the rule end state
     * (traversing only epsilon edges, so we're still in closure, in
     * this same rule).
     *
     * We need to detect any state that can reach loop entry on
     * epsilon w/o exiting rule. We don't have to look at FOLLOW
     * links, just ensure that all stack tops for config refer to key
     * states in LR rule.
     *
     * To verify we are in the right situation we must first check
     * closure is at a StarLoopEntryState generated during LR removal.
     * Then we check that each stack top of context is a return state
     * from one of these cases:
     *
     *   1. 'not' expr, '(' type ')' expr. The return state points at loop entry state
     *   2. expr op expr. The return state is the block end of internal block of (...)*
     *   3. 'between' expr 'and' expr. The return state of 2nd expr reference.
     *      That state points at block end of internal block of (...)*.
     *   4. expr '?' expr ':' expr. The return state points at block end,
     *      which points at loop entry state.
     *
     * If any is true for each stack top, then closure does not add a
     * config to the current config set for edge[0], the loop entry branch.
     *
     *  Conditions fail if any context for the current config is:
     *
     *   a. empty (we'd fall out of expr to do a global FOLLOW which could
     *      even be to some weird spot in expr) or,
     *   b. lies outside of expr or,
     *   c. lies within expr but at a state not the BlockEndState
     *   generated during LR removal
     *
     * Do we need to evaluate predicates ever in closure for this case?
     *
     * No. Predicates, including precedence predicates, are only
     * evaluated when computing a DFA start state. I.e., only before
     * the lookahead (but not parser) consumes a token.
     *
     * There are no epsilon edges allowed in LR rule alt blocks or in
     * the "primary" part (ID here). If closure is in
     * StarLoopEntryState any lookahead operation will have consumed a
     * token as there are no epsilon-paths that lead to
     * StarLoopEntryState. We do not have to evaluate predicates
     * therefore if we are in the generated StarLoopEntryState of a LR
     * rule. Note that when making a prediction starting at that
     * decision point, decision d=2, compute-start-state performs
     * closure starting at edges[0], edges[1] emanating from
     * StarLoopEntryState. That means it is not performing closure on
     * StarLoopEntryState during compute-start-state.
     *
     * How do we know this always gives same prediction answer?
     *
     * Without predicates, loop entry and exit paths are ambiguous
     * upon remaining input +b (in, say, a+b). Either paths lead to
     * valid parses. Closure can lead to consuming + immediately or by
     * falling out of this call to expr back into expr and loop back
     * again to StarLoopEntryState to match +b. In this special case,
     * we choose the more efficient path, which is to take the bypass
     * path.
     *
     * The lookahead language has not changed because closure chooses
     * one path over the other. Both paths lead to consuming the same
     * remaining input during a lookahead operation. If the next token
     * is an operator, lookahead will enter the choice block with
     * operators. If it is not, lookahead will exit expr. Same as if
     * closure had chosen to enter the choice block immediately.
     *
     * Closure is examining one config (some loopentrystate, some alt,
     * context) which means it is considering exactly one alt. Closure
     * always copies the same alt to any derived configs.
     *
     * How do we know this optimization doesn't mess up precedence in
     * our parse trees?
     *
     * Looking through expr from left edge of stat only has to confirm
     * that an input, say, a+b+c; begins with any valid interpretation
     * of an expression. The precedence actually doesn't matter when
     * making a decision in stat seeing through expr. It is only when
     * parsing rule expr that we must use the precedence to get the
     * right interpretation and, hence, parse tree.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfig $config
     *
     * @return bool
     */
    protected function canDropLoopEntryEdgeInLeftRecursiveRule(ATNConfig $config): bool
    {
        $p = $config->state;
        // First check to see if we are in StarLoopEntryState generated during
        // left-recursion elimination. For efficiency, also check if
        // the context has an empty stack case. If so, it would mean
        // global FOLLOW so we can't perform optimization
        if ($p->getStateType() !== ATNState::STAR_LOOP_ENTRY ||
            !$p->isPrecedenceDecision ||                        // Are we the special loop entry/exit state?
            $config->context->isEmpty() ||                      // If SLL wildcard
            $config->context->hasEmptyPath())
        {
            return false;
        }

        // Require all return states to return back to the same rule
        // that p is in.
        $numCtxs = $config->context->size();
        for ($i = 0; $i < $numCtxs; $i++) {
            $returnState = $this->atn->states[$config->context->getReturnState($i)];
            if ($returnState->ruleIndex !== $p->ruleIndex) {
                return false;
            }
        }

        /** @var \ANTLR\v4\Runtime\ATN\BlockStartState $decisionStartState */
        $decisionStartState = $p->transition(0)->target;
        $blockEndStateNum = $decisionStartState->endState->stateNumber;
        /** @var \ANTLR\v4\Runtime\ATN\BlockEndState $blockEndState */
        $blockEndState = $this->atn->states[$blockEndStateNum];

        // Verify that the top of each stack context leads to loop entry/exit
        // state through epsilon edges and w/o leaving rule.
        for ($i = 0; $i < $numCtxs; $i++) {
            $returnStateNumber = $config->context->getReturnState($i);
            $returnState = $this->atn->states[$returnStateNumber];

            // all states must have single outgoing epsilon edge
            if ($returnState->getNumberOfTransitions() !== 1 || $returnState->transition(0)->isEpsilon()) {
                return false;
            }

            // Look for prefix op case like 'not expr', (' type ')' expr
            $returnStateTarget = $returnState->transition(0)->target;
            if ($returnState->getStateType() === ATNState::BLOCK_END && $returnStateTarget === $p) {
                continue;
            }

            // Look for 'expr op expr' or case where expr's return state is block end
            // of (...)* internal block; the block end points to loop back
            // which points to p but we don't need to check that
            if ($returnState === $blockEndState) {
                continue;
            }

            // Look for ternary expr ? expr : expr. The return state points at block end,
            // which points at loop entry state
            if ($returnStateTarget === $blockEndState) {
                continue;
            }

            // Look for complex prefix 'between expr and expr' case where 2nd expr's
            // return state points at block end state of (...)* internal block
            if ($returnStateTarget->getStateType() === ATNState::BLOCK_END &&
                $returnStateTarget->getNumberOfTransitions() === 1 &&
                $returnStateTarget->transition(0)->isEpsilon() &&
                $returnStateTarget->transition(0)->target === $p)
            {
                continue;
            }

            // anything else ain't conforming
            return false;
        }

        return true;
    }

    public function getRuleName(int $index): string
    {
        return "<rule $index>";
    }

    protected function getEpsilonTarget(
        ATNConfig $config,
        Transition $t,
        bool $collectPredicates,
        bool $inContext,
        bool $fullCtx,
        bool $treatEofAsEpsilon
    ): ?ATNConfig {
        switch ($t->getSerializationType()) {
            case Transition::RULE:
                return $this->ruleTransition($config, $t);
            case Transition::PRECEDENCE;
                return $this->precedenceTransition($config, $t, $collectPredicates, $inContext, $fullCtx);
            case Transition::PREDICATE:
                return $this->predTransition($config, $t, $collectPredicates, $inContext, $fullCtx);
            case Transition::ACTION:
                return $this->actionTransition($config, $t);
            case Transition::EPSILON:
                return new ATNConfig(
                    $t->target,
                    $config->alt,
                    $config->context,
                    $config->reachesIntoOuterContext,
                    $config->semanticContext
                );
            case Transition::ATOM:
            case Transition::RANGE:
            case Transition::SET:
                // EOF transitions act like epsilon transitions after the first EOF
                // transition is traversed
                if ($treatEofAsEpsilon) {
                    if ($t->matches(Token::EOF, 0, 1)) {
                        return new ATNConfig(
                            $t->target,
                            $config->alt,
                            $config->context,
                            $config->reachesIntoOuterContext,
                            $config->semanticContext
                        );
                    }
                }

                return null;
            default:
                return null;
        }
    }

    protected function actionTransition(ATNConfig $config, ActionTransition $t): ATNConfig
    {
        return new ATNConfig(
            $t->target,
            $config->alt,
            $config->context,
            $config->reachesIntoOuterContext,
            $config->semanticContext
        );
    }

    protected function precedenceTransition(
        ATNConfig $config,
        PrecedencePredicateTransition $pt,
        bool $collectPredicates,
        bool $inContext,
        bool $fullCtx
    ): ?ATNConfig {
        $c = null;

        if ($collectPredicates && $inContext) {
            if ($fullCtx) {
                // In full context mode, we can evaluate predicates on-the-fly
                // during closure, which dramatically reduces the size of
                // the config sets. It also obviates the need to test predicates
                // later during conflict resolution.
                $currentPosition = $this->_input->index();
                $this->_input->seek($this->_startIndex);
                $predSucceeds = $this->evalSemanticContext($pt->getPredicate(), $this->_outerContext, $config->alt, $fullCtx);
                $this->_input->seek($currentPosition);
                if ($predSucceeds) {
                    $c = new ATNConfig(
                        $pt->target,
                        $config->alt,
                        $config->context,
                        $config->reachesIntoOuterContext,
                        $config->semanticContext
                    ); // no pred context
                }
            }
            else {
                $newSemCtx = SemanticContext::and($config->semanticContext, $pt->getPredicate());
                $c = new ATNConfig(
                    $pt->target,
                    $config->alt,
                    $config->context,
                    $config->reachesIntoOuterContext,
                    $newSemCtx
                );
            }
        }
        else {
            $c = new ATNConfig(
                $pt->target,
                $config->alt,
                $config->context,
                $config->reachesIntoOuterContext,
                $config->semanticContext
            );
        }

        return $c;
    }

    protected function predTransition(
        ATNConfig $config,
        PredicateTransition $pt,
        bool $collectPredicates,
        bool $inContext,
        bool $fullCtx
    ): ?ATNConfig {
        $c = null;

        if ($collectPredicates
            && (!$pt->isCtxDependent || ($pt->isCtxDependent && $inContext)))
        {
            if ($fullCtx) {
                // In full context mode, we can evaluate predicates on-the-fly
                // during closure, which dramatically reduces the size of
                // the config sets. It also obviates the need to test predicates
                $currentPosition = $this->_input->index();
                $this->_input->seek($this->_startIndex);
                $predSucceeds = $this->evalSemanticContext($pt->getPredicate(), $this->_outerContext, $config->alt, $fullCtx);
                $this->_input->seek($currentPosition);
                if ($predSucceeds) {
                    $c = new ATNConfig(
                        $pt->target,
                        $config->alt,
                        $config->context,
                        $config->reachesIntoOuterContext,
                        $config->semanticContext
                    ); // no pred context
                }
            } else {
                $newSemCtx = SemanticContext::and($config->semanticContext, $pt->getPredicate());
                $c = new ATNConfig(
                    $pt->target,
                    $config->alt,
                    $config->context,
                    $config->reachesIntoOuterContext,
                    $newSemCtx
                );
            }
        }
        else {
            $c = new ATNConfig(
                $pt->target,
                $config->alt,
                $config->context,
                $config->reachesIntoOuterContext,
                $config->semanticContext
            );
        }

        return $c;
    }

    protected function ruleTransition(ATNConfig $config, RuleTransition $t): ?ATNConfig
    {
        $returnState = $t->followState;
        $newContext = SingletonPredictionContext::create($config->context, $returnState->stateNumber);

        return new ATNConfig(
            $t->target,
            $config->alt,
            $newContext,
            $config->reachesIntoOuterContext,
            $config->semanticContext
        );
    }

    /**
     * Gets a {@link BitSet} containing the alternatives in {@code configs}
     * which are part of one or more conflicting alternative subsets.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs The {@link ATNConfigSet} to analyze.
     *
     * @return \ANTLR\v4\Runtime\Misc\BitSet The alternatives in {@code configs} which are part of one or more
     * conflicting alternative subsets. If {@code configs} does not contain any
     * conflicting subsets, this method returns an empty {@link BitSet}.
     */
    protected function getConflictingAlts(ATNConfigSet $configs): BitSet
    {
        $altsets = PredictionMode::getConflictingAltSubsets($configs);

        return PredictionMode::getAlts($altsets);
    }

    /**
     * Sam pointed out a problem with the previous definition, v3, of
     * ambiguous states. If we have another state associated with conflicting
     * alternatives, we should keep going. For example, the following grammar
     *
     * s : (ID | ID ID?) ';' ;
     *
     * When the ATN simulation reaches the state before ';', it has a DFA
     * state that looks like: [12|1|[], 6|2|[], 12|2|[]]. Naturally
     * 12|1|[] and 12|2|[] conflict, but we cannot stop processing this node
     * because alternative to has another way to continue, via [6|2|[]].
     * The key is that we have a single state that has config's only associated
     * with a single alternative, 2, and crucially the state transitions
     * among the configurations are all non-epsilon transitions. That means
     * we don't consider any conflicts that include alternative 2. So, we
     * ignore the conflict between alts 1 and 2. We ignore a set of
     * conflicting alts when there is an intersection with an alternative
     * associated with a single alt state in the state&rarr;config-list map.
     *
     * It's also the case that we might have two conflicting configurations but
     * also a 3rd nonconflicting configuration for a different alternative:
     * [1|1|[], 1|2|[], 8|3|[]]. This can come about from grammar:
     *
     * a : A | A | A B ;
     *
     * After matching input A, we reach the stop state for rule A, state 1.
     * State 8 is the state right before B. Clearly alternatives 1 and 2
     * conflict and no amount of further lookahead will separate the two.
     * However, alternative 3 will be able to continue and so we do not
     * stop working on this state. In the previous example, we're concerned
     * with states associated with the conflicting alternatives. Here alt
     * 3 is not associated with the conflicting configs, but since we can continue
     * looking for input reasonably, I don't declare the state done. We
     * ignore a set of conflicting alts when we have an alternative
     * that we still need to pursue.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs
     *
     * @return \ANTLR\v4\Runtime\Misc\BitSet
     */
    protected function getConflictingAltsOrUniqueAlt(ATNConfigSet $configs): BitSet
    {
        if ($configs->uniqueAlt !== ATN::INVALID_ALT_NUMBER) {
            $conflictingAlts = new BitSet();
            $conflictingAlts->set($configs->uniqueAlt);

            return $conflictingAlts;
        }
        else {
            return $configs->conflictingAlts;
        }
    }

    protected function noViableAlt(
        TokenStream $input,
        ParserRuleContext $outerContext,
        ATNConfigSet $configs,
        int $startIndex
    ): NoViableAltException {
        return new NoViableAltException(
            $this->parser, $input, $input->get($startIndex), $input->LT(1), $configs, $outerContext
        );
    }

    protected static function getUniqueAlt(ATNConfigSet $configs): int
    {
        $alt = ATN::INVALID_ALT_NUMBER;

        foreach ($configs as $config) {
            if ($alt === ATN::INVALID_ALT_NUMBER) {
                $alt = $config->alt; // found first alt
                continue;
            }

            if ($config->alt !== $alt) {
                return ATN::INVALID_ALT_NUMBER;
            }
        }

        return $alt;
    }

    /**
     * Add an edge to the DFA, if possible. This method calls
     * {@link #addDFAState} to ensure the {@code to} state is present in the
     * DFA. If {@code from} is {@code null}, or if {@code t} is outside the
     * range of edges that can be represented in the DFA tables, this method
     * returns without adding the edge to the DFA.
     *
     * <p>If {@code to} is {@code null}, this method returns {@code null}.
     * Otherwise, this method returns the {@link DFAState} returned by calling
     * {@link #addDFAState} for the {@code to} state.</p>
     *
     * @param \ANTLR\v4\Runtime\DFA\DFA $dfa The DFA
     * @param \ANTLR\v4\Runtime\DFA\DFAState $from The source state for the edge
     * @param int $t The input symbol
     * @param \ANTLR\v4\Runtime\DFA\DFAState $to The target state for the edge
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState|null If {@code to} is {@code null}, this method returns {@code null};
     * otherwise this method returns the result of calling {@link #addDFAState}
     * on {@code to}
     */
    protected function addDFAEdge(DFA $dfa, ?DFAState $from, int $t, ?DFAState $to): ?DFAState
    {
        if ($to === null) {
            return null;
        }

        $to = $this->addDFAState($dfa, $to); // used existing if possible not incoming
        if ($from === null || $t < -1 || $t > $this->atn->maxTokenType) {
            return $to;
        }

        if ($from->edges === null) {
            $from->edges = array_pad([], $this->atn->maxTokenType + 1 + 1, null);
        }

        $from->edges[$t + 1] = $to; //connect

        return $to;
    }

    /**
     * Add state {@code D} to the DFA if it is not already present, and return
     * the actual instance stored in the DFA. If a state equivalent to {@code D}
     * is already in the DFA, the existing state is returned. Otherwise this
     * method returns {@code D} after adding it to the DFA.
     *
     * <p>If {@code D} is {@link #ERROR}, this method returns {@link #ERROR} and
     * does not change the DFA.</p>
     *
     * @param \ANTLR\v4\Runtime\DFA\DFA $dfa The dfa
     * @param \ANTLR\v4\Runtime\DFA\DFAState $D The DFA state to add
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState|null The state stored in the DFA. This will be either the existing
     * state if {@code D} is already in the DFA, or {@code D} itself if the
     * state was not already present.
     */
    protected function addDFAState(DFA $dfa, DFAState $D): ?DFAState
    {
        $ERROR = self::createError();
        if ($D === $ERROR) {
            return $D;
        }

        $existing = $dfa->states->get($D, null);
        if ($existing !== null) {
            return $existing;
        }

        $D->stateNumber = $dfa->states->count();
        if (!$D->configs->isReadonly()) {
            $D->configs->optimizeConfigs($this);
            $D->configs->setReadonly(true);
        }

        $dfa->states->put($D, $D);

        return $D;
    }

    protected function reportAttemptingFullContext(
        DFA $dfa,
        BitSet $conflictingAlts,
        ATNConfigSet $configs,
        int $startIndex,
        int $stopIndex
    ): void {
        if ($this->parser !== null) {
            $this->parser->getErrorListenerDispatch()->reportAttemptingFullContext(
                $this->parser, $dfa, $startIndex, $stopIndex, $conflictingAlts, $configs
            );
        }
    }

    protected function reportContextSensitivity(
        DFA $dfa,
        int $prediction,
        ATNConfigSet $configs,
        int $startIndex,
        int $stopIndex
    ): void {
        if ($this->parser !== null) {
            $this->parser->getErrorListenerDispatch()->reportContextSensitivity(
                $this->parser, $dfa, $startIndex, $stopIndex, $prediction, $configs
            );
        }
    }

    /** If context sensitive parsing, we know it's ambiguity not conflict */
    protected function reportAmbiguity(
        DFA $dfa,
        DFAState $D, // the DFA state from execATN() that had SLL conflicts
        int $startIndex,
        int $stopIndex,
        bool $exact,
        BitSet $ambigAlts,
        ATNConfigSet $configs // configs that LL not SLL considered conflicting
    ): void {
        if ($this->parser !== null) {
            $this->parser->getErrorListenerDispatch()->reportAmbiguity(
                $this->parser, $dfa, $startIndex, $stopIndex, $exact, $ambigAlts, $configs
            );
        }
    }

    public function setPredictionMode(int $mode): void
    {
        $this->mode = $mode;
    }

    public function getPredictionMode(): int
    {
        return $this->mode;
    }

    public function getParser(): Parser
    {
        return $this->parser;
    }
}
