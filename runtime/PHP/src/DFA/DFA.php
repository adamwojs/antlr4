<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\DFA;

use ANTLR\v4\Runtime\ATN\ATNConfigSet;
use ANTLR\v4\Runtime\ATN\DecisionState;
use ANTLR\v4\Runtime\ATN\StarLoopEntryState;
use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Exception\IllegalStateException;
use ANTLR\v4\Runtime\Vocabulary;
use ANTLR\v4\Runtime\VocabularyInterface;
use Ds\Map;

class DFA extends BaseObject
{
    /**
     * A set of all DFA states. Use {@link Map} so we can get old state back
     * ({@link Set} only allows you to see if it's there).
     *
     * @var \Ds\Map
     */
    public $states;

    /** @var \ANTLR\v4\Runtime\DFA\DFAState */
    public $s0;

    /** @var int */
    public $decision;

    /**
     * From which ATN state did we create this DFA?
     *
     * @var \ANTLR\v4\Runtime\ATN\DecisionState
     */
    public $atnStartState;

    /**
     * {@code true} if this DFA is for a precedence decision; otherwise,
     * {@code false}. This is the backing field for {@link #isPrecedenceDfa}.
     *
     * @var bool
     */
    public $precedenceDfa;

    /**
     * @param \ANTLR\v4\Runtime\ATN\DecisionState $atnStartState
     * @param int $decision
     */
    public function __construct(DecisionState $atnStartState, int $decision = 0)
    {
        $this->states = new Map();
        $this->atnStartState = $atnStartState;
        $this->decision = $decision;

        $precedenceDfa = false;
        if ($this->atnStartState instanceof StarLoopEntryState) {
            if ($this->atnStartState->isPrecedenceDecision) {
                $precedenceDfa = true;

                $precedenceState = new DFAState(new ATNConfigSet());
                $precedenceState->edges = [];
                $precedenceState->isAcceptState = false;
                $precedenceState->requiresFullContext = false;

                $this->s0 = $precedenceState;
            }
        }

        $this->precedenceDfa = $precedenceDfa;
    }

    /**
     * Gets whether this DFA is a precedence DFA. Precedence DFAs use a special
     * start state {@link #s0} which is not stored in {@link #states}. The
     * {@link DFAState#edges} array for this start state contains outgoing edges
     * supplying individual start states corresponding to specific precedence
     * values.
     *
     * @return bool {@code true} if this is a precedence DFA; otherwise,
     * {@code false}
     *
     * @see Parser#getPrecedence()
     */
    public function isPrecedenceDfa(): bool
    {
        return $this->precedenceDfa;
    }

    /**
     * Get the start state for a specific precedence value.
     *
     * @param int $precedence the current precedence
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState|null the start state corresponding to the specified precedence, or
     * {@code null} if no start state exists for the specified precedence
     *
     * @throws \ANTLR\v4\Runtime\Exception\IllegalStateException if this is not a precedence DFA
     *
     * @see #isPrecedenceDfa()
     */
    public function getPrecedenceStartState(int $precedence): ?DFAState
    {
        if (!$this->isPrecedenceDfa()) {
            throw new IllegalStateException('Only precedence DFAs may contain a precedence start state.');
        }

        // s0.edges is never null for a precedence DFA
        if ($precedence < 0 || $precedence >= count($this->s0->edges)) {
            return null;
        }

        return $this->s0->edges[$precedence];
    }

    /**
     * Set the start state for a specific precedence value.
     *
     * @param int $precedence the current precedence
     * @param \ANTLR\v4\Runtime\DFA\DFAState $startState the start state corresponding to the specified
     * precedence
     *
     * @throws \ANTLR\v4\Runtime\Exception\IllegalStateException if this is not a precedence DFA
     *
     * @see #isPrecedenceDfa()
     */
    public function setPrecedenceStartState(int $precedence, DFAState $startState): void
    {
        if (!$this->isPrecedenceDfa()) {
            throw new IllegalStateException('Only precedence DFAs may contain a precedence start state.');
        }

        if ($precedence < 0) {
            return;
        }

        // s0.edges is never null for a precedence DFA
        if ($precedence >= count($this->s0->edges)) {
            $this->s0->edges = array_pad($this->s0->edges, $precedence + 1, null);
        }

        $this->s0->edges[$precedence] = $startState;
    }

    /**
     * Return a list of all states in this DFA, ordered by state number.
     *
     * @return \ANTLR\v4\Runtime\DFA\DFAState[]
     */
    public function getStates(): array
    {
        $result = $this->states->keys();
        $result->sort(function (DFAState $o1, DFAState $o2) {
            return $o1->stateNumber - $o2->stateNumber;
        });

        return $result->toArray();
    }

    public function toLexerString(): string
    {
        if ($this->s0 === null) {
            return '';
        }

        return (string) (new LexerDFASerializer($this));
    }

    public function toString(VocabularyInterface $vocabulary = null): string
    {
        if ($vocabulary === null) {
            $vocabulary = new Vocabulary();
        }

        if ($this->s0 === null) {
            return '';
        }

        return (string) (new DFASerializer($this, $vocabulary));
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
