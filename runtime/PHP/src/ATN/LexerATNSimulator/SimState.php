<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\LexerATNSimulator;

/**
 * When we hit an accept state in either the DFA or the ATN, we
 * have to notify the character stream to start buffering characters
 * via {@link IntStream#mark} and record the current state. The current sim state
 * includes the current index into the input, the current line,
 * and current character position in that line. Note that the Lexer is
 * tracking the starting line and characterization of the token. These
 * variables track the "state" of the simulator when it hits an accept state.
 *
 * <p>We track these variables separately for the DFA and ATN simulation
 * because the DFA simulation often has to fail over to the ATN
 * simulation. If the ATN simulation fails, we need the DFA to fall
 * back to its previously accepted state, if any. If the ATN succeeds,
 * then the ATN does the accept and the DFA simulator that invoked it
 * can simply return the predicted token type.</p>
 */
final class SimState
{
    /** @var int */
    public $index = -1;

    /** @var int */
    public $line = 0;

    /** @var int */
    public $charPos = -1;

    /** @var \ANTLR\v4\Runtime\DFA\DFAState */
    public $dfaState = null;

    public function reset(): void
    {
        $this->index = -1;
        $this->line = 0;
        $this->charPos = -1;
        $this->dfaState = null;
    }
}
