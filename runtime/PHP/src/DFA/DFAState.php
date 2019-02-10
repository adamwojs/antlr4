<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\DFA;

use ANTLR\v4\Runtime\ATN\ATNConfigSet;
use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Misc\MurmurHash;
use Ds\Set;

/**
 * A DFA state represents a set of possible ATN configurations.
 * As Aho, Sethi, Ullman p. 117 says "The DFA uses its state
 * to keep track of all possible states the ATN can be in after
 * reading each input symbol.  That is to say, after reading
 * input a1a2..an, the DFA is in a state that represents the
 * subset T of the states of the ATN that are reachable from the
 * ATN's start state along some path labeled a1a2..an."
 * In conventional NFA&rarr;DFA conversion, therefore, the subset T
 * would be a bitset representing the set of states the
 * ATN could be in.  We need to track the alt predicted by each
 * state as well, however.  More importantly, we need to maintain
 * a stack of states, tracking the closure operations as they
 * jump from rule to rule, emulating rule invocations (method calls).
 * I have to add a stack to simulate the proper lookahead sequences for
 * the underlying LL grammar from which the ATN was derived.
 *
 * <p>I use a set of ATNConfig objects not simple states.  An ATNConfig
 * is both a state (ala normal conversion) and a RuleContext describing
 * the chain of rules (if any) followed to arrive at that state.</p>
 *
 * <p>A DFA state may have multiple references to a particular state,
 * but with different ATN contexts (with same or different alts)
 * meaning that state was reached via a different set of rule invocations.</p>
 */
class DFAState extends BaseObject
{
    /** @var int */
    public $stateNumber = -1;

    /** @var \ANTLR\v4\Runtime\ATN\ATNConfigSet */
    public $configs = null;

    /** @var \ANTLR\v4\Runtime\DFA\DFAState[] */
    public $edges = null;

    /** @var bool */
    public $isAcceptState = false;

    /**
     * if accept state, what ttype do we match or alt do we predict?
     * This is set to {@link ATN#INVALID_ALT_NUMBER} when {@link #predicates}{@code !=null} or
     * {@link #requiresFullContext}.
     *
     * @var int
     */
    public $predication;

    /** @var \ANTLR\v4\Runtime\ATN\LexerActionExecutor */
    public $lexerActionExecutor;

    /**
     * Indicates that this state was created during SLL prediction that
     * discovered a conflict between the configurations in the state. Future
     * {@link ParserATNSimulator#execATN} invocations immediately jumped doing
     * full context prediction if this field is true.
     *
     * @var bool
     */
    public $requiresFullContext = false;

    /**
     * During SLL parsing, this is a list of predicates associated with the
     * ATN configurations of the DFA state. When we have predicates,
     * {@link #requiresFullContext} is {@code false} since full context prediction evaluates predicates
     * on-the-fly. If this is not null, then {@link #prediction} is
     * {@link ATN#INVALID_ALT_NUMBER}.
     *
     * <p>We only use these for non-{@link #requiresFullContext} but conflicting states. That
     * means we know from the context (it's $ or we don't dip into outer
     * context) that it's an ambiguity not a conflict.</p>
     *
     * <p>This list is computed by {@link ParserATNSimulator#predicateDFAState}.</p>
     *
     * @var \ANTLR\v4\Runtime\DFA\PredPrediction[]|null
     */
    public $predicates;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet|null $configs
     * @param int $stateNumber
     */
    public function __construct(?ATNConfigSet $configs = null, int $stateNumber = -1)
    {
        $this->configs = $configs;
        $this->stateNumber = $stateNumber;
    }

    /**
     * Get the set of all alts mentioned by all ATN configurations in this
     * DFA state.
     *
     * @return \Ds\Set|null
     */
    public function getAltSet(): ?Set
    {
        $alts = new Set();
        if ($this->configs !== null) {
            foreach ($this->configs as $config) {
                $alts->add($config->alt);
            }
        }

        if ($alts->isEmpty()) {
            return null;
        }

        return $alts;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        $hash = MurmurHash::initialize(7);
        $hash = MurmurHash::update($hash, $this->configs->hash());
        $hash = MurmurHash::finish($hash, 1);

        return $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($this === $o) {
            return true;
        }

        if ($o instanceof DFAState) {
            return $this->configs === null || $this->configs->equals($o->configs);
        }

        return false;
    }

    public function __toString(): string
    {
        $str = (string)$this->stateNumber;
        $str .= ':';
        $str .= (string)$this->configs;

        if ($this->isAcceptState) {
            $str .= '=>';
            if ($this->predicates !== null) {
                $str .= '[';
                $str .= implode(', ', array_map(function (PredPrediction $p) {
                    return (string)$p;
                }, $this->predicates));
                $str .= ']';
            } else {
                $str .= (string)$this->predication;
            }
        }

        return $str;
    }
}
