<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;

/**
 * The following images show the relation of states and
 * {@link ATNState#transitions} for various grammar constructs.
 *
 * <ul>
 *
 * <li>Solid edges marked with an &#0949; indicate a required
 * {@link EpsilonTransition}.</li>
 *
 * <li>Dashed edges indicate locations where any transition derived from
 * {@link Transition} might appear.</li>
 *
 * <li>Dashed nodes are place holders for either a sequence of linked
 * {@link BasicState} states or the inclusion of a block representing a nested
 * construct in one of the forms below.</li>
 *
 * <li>Nodes showing multiple outgoing alternatives with a {@code ...} support
 * any number of alternatives (one or more). Nodes without the {@code ...} only
 * support the exact number of alternatives shown in the diagram.</li>
 *
 * </ul>
 *
 * <h2>Basic Blocks</h2>
 *
 * <h3>Rule</h3>
 *
 * <embed src="images/Rule.svg" type="image/svg+xml"/>
 *
 * <h3>Block of 1 or more alternatives</h3>
 *
 * <embed src="images/Block.svg" type="image/svg+xml"/>
 *
 * <h2>Greedy Loops</h2>
 *
 * <h3>Greedy Closure: {@code (...)*}</h3>
 *
 * <embed src="images/ClosureGreedy.svg" type="image/svg+xml"/>
 *
 * <h3>Greedy Positive Closure: {@code (...)+}</h3>
 *
 * <embed src="images/PositiveClosureGreedy.svg" type="image/svg+xml"/>
 *
 * <h3>Greedy Optional: {@code (...)?}</h3>
 *
 * <embed src="images/OptionalGreedy.svg" type="image/svg+xml"/>
 *
 * <h2>Non-Greedy Loops</h2>
 *
 * <h3>Non-Greedy Closure: {@code (...)*?}</h3>
 *
 * <embed src="images/ClosureNonGreedy.svg" type="image/svg+xml"/>
 *
 * <h3>Non-Greedy Positive Closure: {@code (...)+?}</h3>
 *
 * <embed src="images/PositiveClosureNonGreedy.svg" type="image/svg+xml"/>
 *
 * <h3>Non-Greedy Optional: {@code (...)??}</h3>
 *
 * <embed src="images/OptionalNonGreedy.svg" type="image/svg+xml"/>
 */
abstract class ATNState extends BaseObject
{
    public const INITIAL_NUM_TRANSITIONS = 4;

    // constants for serialization
    public const INVALID_TYPE = 0;
    public const BASIC = 1;
    public const RULE_START = 2;
    public const BLOCK_START = 3;
    public const PLUS_BLOCK_START = 4;
    public const STAR_BLOCK_START = 5;
    public const TOKEN_START = 6;
    public const RULE_STOP = 7;
    public const BLOCK_END = 8;
    public const STAR_LOOP_BACK = 9;
    public const STAR_LOOP_ENTRY = 10;
    public const PLUS_LOOP_BACK = 11;
    public const LOOP_END = 12;

    public const SERIALIZABLE_NAMES = [
        "INVALID",
        "BASIC",
        "RULE_START",
        "BLOCK_START",
        "PLUS_BLOCK_START",
        "STAR_BLOCK_START",
        "TOKEN_START",
        "RULE_STOP",
        "BLOCK_END",
        "STAR_LOOP_BACK",
        "STAR_LOOP_ENTRY",
        "PLUS_LOOP_BACK",
        "LOOP_END"
    ];

    public const INVALID_STATE_NUMBER = -1;

    /** @var \ANTLR\v4\Runtime\ATN\ATN */
    public $atn = null;

    /** @var int */
    public $stateNumber = self::INVALID_STATE_NUMBER;

    /**
     * At runtime, we don't have Rule objects
     *
     * @var int
     */
    public $ruleIndex;

    /** @var bool */
    public $epsilonOnlyTransitions = false;

    /**
     * Used to cache lookahead during parsing, not used during construction
     *
     * @var \ANTLR\v4\Runtime\Misc\IntervalSet
     */
    public $nextTokenWithinRule;

    /**
     * Track the transitions emanating from this ATN state.
     *
     * @var \ANTLR\v4\Runtime\ATN\Transition[]
     */
    protected $transitions = [];

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o instanceof ATNState) {
            // are these states same object?
            return $this->stateNumber === $o->stateNumber;
        }

        return false;
    }

    public function isNonGreedyExitState(): bool
    {
        return false;
    }

    public function __toString(): string
    {
        return (string)$this->stateNumber;
    }

    public function getTransitions(): array
    {
        return $this->transitions;
    }

    public function getNumberOfTransitions(): int
    {
        return count($this->transitions);
    }

    public function addTransition(Transition $e, ?int $index = null): void
    {
        if ($index === null) {
            $index = $this->getNumberOfTransitions();
        }

        if (empty($this->transitions)) {
            $this->epsilonOnlyTransitions = $e->isEpsilon();
        } else if ($this->epsilonOnlyTransitions !== $e->isEpsilon()) {
            // TODO: Missing error log "ATN state {$this->stateNumber} has both epsilon and non-epsilon transitions."
            $this->epsilonOnlyTransitions = false;
        }

        $alreadyPresent = false;
        foreach ($this->transitions as $t) {
            if ($t->target->stateNumber === $e->target->stateNumber) {
                if ($t->label() !== null && $e->label() !== null && $t->label()->equals($e->label())) {
                    $alreadyPresent = true;
                    break;
                }
                else if ($t->isEpsilon() && $e->isEpsilon()) {
                    $alreadyPresent = true;
                    break;
                }
            }
        }

        if (!$alreadyPresent) {
            $this->transitions[$index] = $e;
        }
    }

    public function transition(int $i): Transition
    {
        return $this->transitions[$i];
    }

    public function setTransition(int $i, Transition $e): void
    {
        $this->transitions[$i] = $e;
    }

    public function removeTransition(int $i): Transition
    {
        $e = $this->transitions[$i];

        unset($this->transitions[$i]);
        $this->transitions = array_values($this->transitions);

        return $e;
    }

    public abstract function getStateType(): int;

    public function onlyHasEpsilonTransitions(): bool
    {
        return $this->epsilonOnlyTransitions;
    }

    public function setRuleIndex(int $ruleIndex): void
    {
        $this->ruleIndex = $ruleIndex;
    }
}
