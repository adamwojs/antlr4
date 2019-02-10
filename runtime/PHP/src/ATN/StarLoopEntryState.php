<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

final class StarLoopEntryState extends DecisionState
{
    /** @var \ANTLR\v4\Runtime\ATN\StarLoopbackState */
    public $loopBackState;

    /**
     * Indicates whether this state can benefit from a precedence DFA during SLL
     * decision making.
     *
     * <p>This is a computed property that is calculated during ATN deserialization
     * and stored for use in {@link ParserATNSimulator} and
     * {@link ParserInterpreter}.</p>
     *
     * @see DFA#isPrecedenceDfa()
     *
     * @var bool
     */
    public $isPrecedenceDecision;

    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::STAR_LOOP_ENTRY;
    }
}
