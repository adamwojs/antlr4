<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\ATN\LexerATNSimulator\SimState;
use ANTLR\v4\Runtime\CharStream;
use ANTLR\v4\Runtime\DFA\DFA;
use ANTLR\v4\Runtime\DFA\DFAState;
use ANTLR\v4\Runtime\Exception\LexerNoViableAltException;
use ANTLR\v4\Runtime\Exception\UnsupportedOperationException;
use ANTLR\v4\Runtime\IntStream;
use ANTLR\v4\Runtime\Lexer;
use ANTLR\v4\Runtime\Misc\Interval;
use ANTLR\v4\Runtime\Token;

class LexerATNSimulator extends ATNSimulator
{
    public const MIN_DFA_EDGE = 0;
    public const MAX_DFA_EDGE = 127; // forces unicode to stay in ATA

    public const EOL = 10;

    /** @var \ANTLR\v4\Runtime\DFA\DFA[] */
    public $decisionToDFA;

    /** @var \ANTLR\v4\Runtime\Lexer */
    protected $recog;

    /**
     * The current token's starting index into the character stream.
     * Shared across DFA to ATN simulation in case the ATN fails and the
     * DFA did not have a previous accept state. In this case, we use the
     * ATN-generated exception object.
     *
     * @var int
     */
    protected $startIndex = -1;

    /**
     * Line number 1..n within the input.
     *
     * @var int
     */
    protected $line = 1;

    /**
     * The index of the character relative to the beginning of the line 0..n-1.
     *
     * @var int
     */
    protected $charPositionInLine = 0;

    /** @var int */
    protected $mode = Lexer::DEFAULT_MODE;

    /**
     * Used during DFA/ATN exec to record the most recent accept configuration info.
     *
     * @var \ANTLR\v4\Runtime\ATN\LexerATNSimulator\SimState
     */
    protected $prevAccept;

    public function __construct(Lexer $recog, ATN $atn, array $decisionToDFA, PredictionContextCache $sharedContextCache)
    {
        parent::__construct($atn, $sharedContextCache);

        $this->decisionToDFA = $decisionToDFA;
        $this->recog = $recog;
        $this->prevAccept = new SimState();
    }

    public function copyState(self $simulator): void
    {
        // TODO: Implement copyState() method
    }

    public function match(CharStream $input, int $mode): int
    {
        $this->mode = $mode;

        $mark = $input->mark();
        try {
            $this->startIndex = $input->index();
            $this->prevAccept->reset();

            $dfa = $this->decisionToDFA[$mode];
            if ($dfa->s0 === null) {
                return $this->matchATN($input);
            } else {
                return $this->execATN($input, $dfa->s0);
            }
        } finally {
            $input->release($mark);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->prevAccept->reset();
        $this->startIndex = -1;
        $this->line = 1;
        $this->charPositionInLine = 0;
        $this->mode = Lexer::DEFAULT_MODE;
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

    protected function matchATN(CharStream $input): int
    {
        $startState = $this->atn->modeToStartState[$this->mode];

        $s0_closure = $this->computeStartState($input, $startState);
        $suppressEdge = $s0_closure->hasSemanticContext;
        $s0_closure->hasSemanticContext = false;

        $next = $this->addDFAState($s0_closure);
        if (!$suppressEdge) {
            $this->decisionToDFA[$this->mode]->s0 = $next;
        }

        return $this->execATN($input, $next);
    }

    protected function execATN(CharStream $input, DFAState $ds0): int
    {
        if ($ds0->isAcceptState) {
            // allow zero-length tokens
            $this->captureSimState($this->prevAccept, $input, $ds0);
        }

        $t = $input->LA(1);
        $s = $ds0; // s is current/from DFA state

        while (true) {
            // As we move src->trg, src->trg, we keep track of the previous trg to
            // avoid looking up the DFA state again, which is expensive.
            // If the previous target was already part of the DFA, we might
            // be able to avoid doing a reach operation upon t. If s!=null,
            // it means that semantic predicates didn't prevent us from
            // creating a DFA state. Once we know s!=null, we check to see if
            // the DFA state has an edge already for t. If so, we can just reuse
            // it's configuration set; there's no point in re-computing it.
            // This is kind of like doing DFA simulation within the ATN
            // simulation because DFA simulation is really just a way to avoid
            // computing reach/closure sets. Technically, once we know that
            // we have a previously added DFA state, we could jump over to
            // the DFA simulator. But, that would mean popping back and forth
            // a lot and making things more complicated algorithmically.
            // This optimization makes a lot of sense for loops within DFA.
            // A character will take us back to an existing DFA state
            // that already has lots of edges out of it. e.g., .* in comments.

            $target = $this->getExistingTargetState($s, $t);
            if ($target === null) {
                $target = $this->computeTargetState($input, $s, $t);
            }

            if ($target === self::createError()) {
                break;
            }

            // If this is a consumable input element, make sure to consume before
            // capturing the accept state so the input index, line, and char
            // position accurately reflect the state of the interpreter at the
            // end of the token.
            if ($t !== IntStream::EOF) {
                $this->consume($input);
            }

            if ($target->isAcceptState) {
                $this->captureSimState($this->prevAccept, $input, $target);
                if ($t === IntStream::EOF) {
                    break;
                }
            }

            $t = $input->LA(1);
            $s = $target; // filp; current DFA target becomes new src/from state
        }

        return $this->failOrAccept($this->prevAccept, $input, $s->configs, $t);
    }

    /**
     * Get an existing target state for an edge in the DFA. If the target state
     * for the edge has not yet been computed or is otherwise not available,
     * this method returns {@code null}.
     *
     * @param \ANTLR\v4\Runtime\DFA\DFAState s The current DFA state
     * @param int t The next input symbol
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState The existing target DFA state for the given input symbol
     * {@code t}, or {@code null} if the target state for this edge is not
     * already cached
     */
    protected function getExistingTargetState(DFAState $s, int $t): ?DFAState
    {
        if ($s->edges === null || $t < self::MIN_DFA_EDGE || $t > self::MAX_DFA_EDGE) {
            return null;
        }

        if (isset($s->edges[$t - self::MIN_DFA_EDGE])) {
            return $s->edges[$t - self::MIN_DFA_EDGE];
        }

        return null;
    }

    /**
     * Compute a target state for an edge in the DFA, and attempt to add the
     * computed state and corresponding edge to the DFA.
     *
     * @param \ANTLR\v4\Runtime\CharStream $input The input stream
     * @param \ANTLR\v4\Runtime\DFA\DFAState $s The current DFA state
     * @param int $t The next input symbol
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState The computed target DFA state for the given input symbol
     * {@code t}. If {@code t} does not lead to a valid DFA state, this method
     * returns {@link #ERROR}.
     */
    protected function computeTargetState(CharStream $input, DFAState $s, int $t): DFAState
    {
        $reach = new OrderedATNConfigSet();

        // if we don't find an existing DFA state
        // Fill reach starting from closure, following t transitions
        $this->getReachableConfigSet($input, $s->configs, $reach, $t);

        if ($reach->isEmpty()) {
            // we got nowhere on t from s
            if (!$reach->hasSemanticContext) {
                // we got nowhere on t, don't throw out this knowledge; it'd
                // cause a failover from DFA later.
                $this->addDFAEdge($s, $t, self::createError());
            }

            // stop when we can't match any more char
            return self::createError();
        }

        // Add an edge from s to target DFA found/created for reach
        return $this->addDFAEdge_ATNConfigSet($s, $t, $reach);
    }

    protected function failOrAccept(
        SimState $prevAccept,
        CharStream $input,
        ATNConfigSet $reach,
        int $t
    ): int {
        if ($prevAccept->dfaState !== null) {
            $this->accept(
                $input,
                $prevAccept->dfaState->lexerActionExecutor,
                $this->startIndex,
                $prevAccept->index,
                $prevAccept->line,
                $prevAccept->charPos
            );

            return $prevAccept->dfaState->predication;
        } else {
            // if no accept and EOF is first char, return EOF
            if ($t === IntStream::EOF && $input->index() === $this->startIndex) {
                return Token::EOF;
            }

            throw new LexerNoViableAltException($this->recog, $input, $this->startIndex, $reach);
        }
    }

    /**
     * Given a starting configuration set, figure out all ATN configurations
     * we can reach upon input {@code t}. Parameter {@code reach} is a return
     * parameter.
     *
     * @param \ANTLR\v4\Runtime\CharStream $input
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $closure
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $reach
     * @param int $t
     */
    protected function getReachableConfigSet(CharStream $input, ATNConfigSet $closure, ATNConfigSet $reach, int $t): void
    {
        // this is used to skip processing for configs which have a lower priority
        // than a config that already reached an accept state for the same rule
        $skipAlt = ATN::INVALID_ALT_NUMBER;

        /** @var \ANTLR\v4\Runtime\ATN\LexerATNConfig $c */
        foreach ($closure as $c) {
            $currentAltReachedAcceptState = $c->alt === $skipAlt;
            if ($currentAltReachedAcceptState && $c->hasPassedThroughNonGreedyDecision()) {
                continue;
            }

            $n = $c->state->getNumberOfTransitions();
            for ($i = 0; $i < $n; $i++) {
                $target = $this->getReachableTarget($c->state->transition($i), $t);

                if ($target !== null) {
                    $lexerActionExecutor = $c->getLexerActionExecutor();
                    if ($lexerActionExecutor !== null) {
                        $lexerActionExecutor = $lexerActionExecutor->fixOffsetBeforeMatch($input->index() - $this->startIndex);
                    }

                    $treatEofAsEpsilon = $t === CharStream::EOF;
                    if ($this->closure($input, LexerATNConfig::createFromLexerATNConfigAndATNState($c, $target, null, $lexerActionExecutor), $reach, $currentAltReachedAcceptState, true, $treatEofAsEpsilon)) {
                        // any remaining configs for this alt have a lower priority than
                        // the one that just reached an accept state.
                        $skipAlt = $c->alt;
                        break;
                    }
                }
            }
        }
    }

    protected function accept(
        CharStream $input,
        ?LexerActionExecutor $lexerActionExecutor,
        int $startIndex,
        int $index,
        int $line,
        int $charPos
    ): void {
        // seek to after last char in token
        $input->seek($index);
        $this->line = $line;
        $this->charPositionInLine = $charPos;

        if ($lexerActionExecutor !== null && $this->recog !== null) {
            $lexerActionExecutor->execute($this->recog, $input, $startIndex);
        }
    }

    protected function getReachableTarget(Transition $transition, int $t): ?ATNState
    {
        if ($transition->matches($t, Lexer::MIN_CHAR_VALUE, Lexer::MAX_CHAR_VALUE)) {
            return $transition->target;
        }

        return null;
    }

    protected function computeStartState(CharStream $input, ATNState $p): ATNConfigSet
    {
        $configs = new OrderedATNConfigSet();

        for ($i = 0; $i < $p->getNumberOfTransitions(); $i++) {
            $this->closure(
                $input,
                LexerATNConfig::createFromATNStateAltAndPredicationContext(
                    $p->transition($i)->target, $i + 1, PredictionContext::createEmpty()
                ),
                $configs,
                false,
                false,
                false
            );
        }

        return $configs;
    }

    /**
     * Since the alternatives within any lexer decision are ordered by
     * preference, this method stops pursuing the closure as soon as an accept
     * state is reached. After the first accept state is reached by depth-first
     * search from {@code config}, all other (potentially reachable) states for
     * this rule would have a lower priority.
     *
     * @param \ANTLR\v4\Runtime\CharStream $input
     * @param \ANTLR\v4\Runtime\ATN\LexerATNConfig $config
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs
     * @param bool $currentAltReachedAcceptState
     * @param bool $speculative
     * @param bool $treatEofAsEpsilon
     *
     * @return bool {@code true} if an accept state is reached, otherwise
     * {@code false}
     */
    protected function closure(
        CharStream $input,
        LexerATNConfig $config,
        ATNConfigSet $configs,
        bool $currentAltReachedAcceptState,
        bool $speculative,
        bool $treatEofAsEpsilon
    ): bool {
        if ($config->state instanceof RuleStopState) {
            if ($config->context === null || $config->context->hasEmptyPath()) {
                if ($config->context === null || $config->context->isEmpty()) {
                    $configs->add($config);

                    return true;
                } else {
                    $configs->add(LexerATNConfig::createFromLexerATNConfigAndATNState(
                        $config, $config->state, PredictionContext::createEmpty()
                    ));
                    $currentAltReachedAcceptState = true;
                }
            }

            if ($config->context !== null && !$config->context->isEmpty()) {
                for ($i = 0; $i < $config->context->size(); $i++) {
                    if ($config->context->getReturnState($i) !== PredictionContext::EMPTY_RETURN_STATE) {
                        $newContext = $config->context->getParent($i);
                        $returnState = $this->atn->states[$config->context->getReturnState($i)];

                        $currentAltReachedAcceptState = $this->closure(
                            $input,
                            LexerATNConfig::createFromLexerATNConfigAndATNState($config, $returnState, $newContext),
                            $configs,
                            $currentAltReachedAcceptState,
                            $speculative,
                            $treatEofAsEpsilon
                        );
                    }
                }
            }

            return $currentAltReachedAcceptState;
        }

        if (!$config->state->onlyHasEpsilonTransitions()) {
            if (!$currentAltReachedAcceptState || !$config->hasPassedThroughNonGreedyDecision()) {
                $configs->add($config);
            }
        }

        $p = $config->state;
        for ($i = 0; $i < $p->getNumberOfTransitions(); $i++) {
            $t = $p->transition($i);
            $c = $this->getEpsilonTarget($input, $config, $t, $configs, $speculative, $treatEofAsEpsilon);

            if ($c !== null) {
                $currentAltReachedAcceptState = $this->closure(
                    $input,
                    $c,
                    $configs,
                    $currentAltReachedAcceptState,
                    $speculative,
                    $treatEofAsEpsilon
                );
            }
        }

        return $currentAltReachedAcceptState;
    }

    protected function getEpsilonTarget(
        CharStream $input,
        LexerATNConfig $config,
        Transition $t,
        ATNConfigSet $configs,
        bool $speculative,
        bool $treatEofAsEpsilon
    ): ?LexerATNConfig {
        switch ($t->getSerializationType()) {
            case Transition::RULE:
                return LexerATNConfig::createFromLexerATNConfigAndATNState($config, $t->target, SingletonPredictionContext::create($config->context, $t->followState->stateNumber));
                break;
            case Transition::PRECEDENCE:
                throw new UnsupportedOperationException('Precedence predicates are not supported in lexers.');
                break;
            case Transition::PREDICATE:
                // Track traversing semantic predicates. If we traverse,
                // we cannot add a DFA state for this "reach" computation
                // because the DFA would not test the predicate again in the
                // future. Rather than creating collections of semantic predicates
                // like v3 and testing them on prediction, v4 will test them on the
                // fly all the time using the ATN not the DFA. This is slower but
                // semantically it's not used that often. One of the key elements to
                // this predicate mechanism is not adding DFA states that see
                // predicates immediately afterwards in the ATN. For example,
                //
                // a : ID {p1}? | ID {p2}? ;
                //
                // should create the start state for rule 'a' (to save start state
                // competition), but should not create target of ID state. The
                // collection of ATN states the following ID references includes
                // states reached by traversing predicates. Since this is when we
                // test them, we cannot cash the DFA state target of ID.
                $configs->hasSemanticContext = true;
                if ($this->evaluatePredicate($input, $t->ruleIndex, $t->predIndex, $speculative)) {
                    return LexerATNConfig::createFromLexerATNConfigAndATNState($config, $t->target);
                }
                break;
            case Transition::ACTION:
                if ($config->context === null || $config->context->hasEmptyPath()) {
                    // execute actions anywhere in the start rule for a token.
                    //
                    // TODO: if the entry rule is invoked recursively, some
                    // actions may be executed during the recursive call. The
                    // problem can appear when hasEmptyPath() is true but
                    // isEmpty() is false. In this case, the config needs to be
                    // split into two contexts - one with just the empty path
                    // and another with everything but the empty path.
                    // Unfortunately, the current algorithm does not allow
                    // getEpsilonTarget to return two configurations, so
                    // additional modifications are needed before we can support
                    // the split operation.
                    $lexerActionExecutor = LexerActionExecutor::append(
                        $config->getLexerActionExecutor(),
                        $this->atn->lexerActions[$t->actionIndex]
                    );

                    return LexerATNConfig::createFromLexerATNConfigAndATNState($config, $t->target, null, $lexerActionExecutor);
                } else {
                    // ignore actions in referenced rules
                    return LexerATNConfig::createFromLexerATNConfigAndATNState($config, $t->target);
                }
                break;
            case Transition::EPSILON:
                return LexerATNConfig::createFromLexerATNConfigAndATNState($config, $t->target);
                break;
            case Transition::ATOM:
            case Transition::RANGE:
            case Transition::SET:
                if ($treatEofAsEpsilon) {
                    if ($t->matches(CharStream::EOF, Lexer::MIN_CHAR_VALUE, Lexer::MAX_CHAR_VALUE)) {
                        return LexerATNConfig::createFromLexerATNConfigAndATNState($config, $t->target);
                    }
                }
                break;
        }

        return null;
    }

    /**
     * Evaluate a predicate specified in the lexer.
     *
     * <p>If {@code speculative} is {@code true}, this method was called before
     * {@link #consume} for the matched character. This method should call
     * {@link #consume} before evaluating the predicate to ensure position
     * sensitive values, including {@link Lexer#getText}, {@link Lexer#getLine},
     * and {@link Lexer#getCharPositionInLine}, properly reflect the current
     * lexer state. This method should restore {@code input} and the simulator
     * to the original state before returning (i.e. undo the actions made by the
     * call to {@link #consume}.</p>
     *
     * @param \ANTLR\v4\Runtime\CharStream $input the input stream
     * @param int $ruleIndex the rule containing the predicate
     * @param int $predIndex the index of the predicate within the rule
     * @param bool $speculative {@code true} if the current index in {@code input} is
     * one character before the predicate's location
     *
     * @return bool {@code true} if the specified predicate evaluates to
     * {@code true}
     */
    protected function evaluatePredicate(CharStream $input, int $ruleIndex, int $predIndex, bool $speculative): bool
    {
        // assume true if no recognizer was provided
        if ($this->recog === null) {
            return true;
        }

        if (!$speculative) {
            return $this->recog->sempred(null, $ruleIndex, $predIndex);
        }

        $charPositionInLine = $this->charPositionInLine;
        $line = $this->line;
        $index = $input->index();
        $marker = $input->mark();

        try {
            $this->consume($input);

            return $this->recog->sempred(null, $ruleIndex, $predIndex);
        } finally {
            $this->charPositionInLine = $charPositionInLine;
            $this->line = $line;

            $input->seek($index);
            $input->release($marker);
        }
    }

    protected function captureSimState(SimState $settings, CharStream $input, DFAState $dfaState): void
    {
        $settings->index = $input->index();
        $settings->line = $this->line;
        $settings->charPos = $this->charPositionInLine;
        $settings->dfaState = $dfaState;
    }

    protected function addDFAEdge(DFAState $p, int $t, DFAState $q): void
    {
        if ($t < self::MIN_DFA_EDGE || $t > self::MAX_DFA_EDGE) {
            // Only track edges within the DFA bounds
            return;
        }

        if ($p->edges === null) {
            //  make room for tokens 1..n and -1 masquerading as index 0
            $p->edges = array_pad([], self::MAX_DFA_EDGE - self::MIN_DFA_EDGE + 1, null);
        }

        // connect
        $p->edges[$t - self::MIN_DFA_EDGE] = $q;
    }

    protected function addDFAEdge_ATNConfigSet(DFAState $p, int $t, ATNConfigSet $configs): DFAState
    {
        //leading to this call, ATNConfigSet.hasSemanticContext is used as a
        //marker indicating dynamic predicate evaluation makes this edge
        //dependent on the specific input sequence, so the static edge in the
        //DFA should be omitted. The target DFAState is still created since
        //execATN has the ability to resynchronize with the DFA state cache
        //following the predicate evaluation step.

        //TJP notes: next time through the DFA, we see a pred again and eval.
        //If that gets us to a previously created (but dangling) DFA
        //state, we can continue in pure DFA mode from there.

        $suppressEdge = $configs->hasSemanticContext;
        $configs->hasSemanticContext = false;

        $q = $this->addDFAState($configs);
        if ($suppressEdge) {
            return $q;
        }

        $this->addDFAEdge($p, $t, $q);

        return $q;
    }

    /**
     * Add a new DFA state if there isn't one with this set of
     * configurations already. This method also detects the first
     * configuration containing an ATN rule stop state. Later, when
     * traversing the DFA, we will know which rule to accept.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState
     */
    protected function addDFAState(ATNConfigSet $configs): DFAState
    {
        // The lexer evaluates predicates on-the-fly; by this point configs
        // should not contain any configurations with unevaluated predicates.
        assert(!$configs->hasSemanticContext);

        $firstConfigWithRuleStopState = null;
        foreach ($configs as $config) {
            if ($config->state instanceof RuleStopState) {
                $firstConfigWithRuleStopState = $config;
                break;
            }
        }

        $proposed = new DFAState($configs);
        if ($firstConfigWithRuleStopState instanceof LexerATNConfig) {
            $proposed->isAcceptState = true;
            $proposed->lexerActionExecutor = $firstConfigWithRuleStopState->getLexerActionExecutor();
            $proposed->predication = $this->atn->ruleToTokenType[$firstConfigWithRuleStopState->state->ruleIndex];
        }

        $dfa = $this->decisionToDFA[$this->mode];
        if (($existing = $dfa->states->get($proposed, null)) !== null) {
            return $existing;
        }

        $newState = $proposed;
        $newState->stateNumber = $dfa->states->count();
        $configs->setReadonly(true);
        $newState->configs = $configs;

        $dfa->states->put($newState, $newState);

        return $newState;
    }

    final protected function getDFA(int $mode): DFA
    {
        return $this->decisionToDFA[$mode];
    }

    /**
     * Get the text matched so far for the current token.
     *
     * @param \ANTLR\v4\Runtime\CharStream $input
     *
     * @return string
     */
    public function getText(CharStream $input): string
    {
        // index is first lookahead char, don't include.
        return $input->getText(Interval::of($this->startIndex, $input->index() - 1));
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function setLine(int $line): void
    {
        $this->line = $line;
    }

    public function getCharPositionInLine(): int
    {
        return $this->charPositionInLine;
    }

    public function setCharPositionInLine(int $charPositionInLine)
    {
        $this->charPositionInLine = $charPositionInLine;
    }

    public function consume(CharStream $input): void
    {
        $char = $input->LA(1);

        if ($char === self::EOL) {
            $this->line++;
            $this->charPositionInLine = 0;
        } else {
            $this->charPositionInLine++;
        }

        $input->consume();
    }
}
