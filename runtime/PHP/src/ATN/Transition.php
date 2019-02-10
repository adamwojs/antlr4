<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Misc\IntervalSet;

/**
 * An ATN transition between any two ATN states.  Subclasses define
 * atom, set, epsilon, action, predicate, rule transitions.
 *
 * <p>This is a one way link.  It emanates from a state (usually via a list of
 * transitions) and has a target state.</p>
 *
 * <p>Since we never have to change the ATN transitions once we construct it,
 * we can fix these transitions as specific classes. The DFA transitions
 * on the other hand need to update the labels as it adds transitions to
 * the states. We'll use the term Edge for the DFA to distinguish them from
 * ATN transitions.</p>
 */
abstract class Transition extends BaseObject
{
    // constants for serialization
    public const EPSILON = 1;
    public const RANGE = 2;
    public const RULE = 3;
    public const PREDICATE = 4; // e.g., {isType(input.LT(1))}?
    public const ATOM = 5;
    public const ACTION = 6;
    public const SET = 7; // ~(A|B) or ~atom, wildcard, which convert to next 2
    public const NOT_SET = 8;
    public const WILDCARD = 9;
    public const PRECEDENCE = 10;

    public const SERIALIZABLE_NAMES = [
        "INVALID",
        "EPSILON",
        "RANGE",
        "RULE",
        "PREDICATE",
        "ATOM",
        "ACTION",
        "SET",
        "NOT_SET",
        "WILDCARD",
        "PRECEDENCE"
    ];

    public const SERIALIZATION_TYPES = [
        EpsilonTransition::class => self::EPSILON,
        RangeTransition::class => self::RANGE,
        RuleTransition::class => self::RULE,
        PredicateTransition::class => self::PREDICATE,
        AtomTransition::class => self::ATOM,
        ActionTransition::class => self::ACTION,
        SetTransition::class => self::SET,
        NotSetTransition::class => self::NOT_SET,
        WildcardTransition::class => self::WILDCARD,
        PrecedencePredicateTransition::class => self::PRECEDENCE,
    ];

    /**
     * The target of this transition.
     *
     * @var \ANTLR\v4\Runtime\ATN\ATNState
     */
    public $target;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $target
     */
    protected function __construct(ATNState $target)
    {
        $this->target = $target;
    }

    public abstract function getSerializationType(): int;

    /**
     * Determines if the transition is an "epsilon" transition.
     *
     * <p>The default implementation returns {@code false}.</p>
     *
     * @return bool {@code true} if traversing this transition in the ATN does not
     * consume an input symbol; otherwise, {@code false} if traversing this
     * transition consumes (matches) an input symbol.
     */
    public function isEpsilon(): bool
    {
        return false;
    }

    public function label(): ?IntervalSet
    {
        return null;
    }

    public abstract function matches(int $symbol, int $minVocabSymbol, int $maxVocabSymbol): bool;
}
