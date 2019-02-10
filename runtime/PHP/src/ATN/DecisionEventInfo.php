<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\TokenStream;

/**
 * This is the base class for gathering detailed information about prediction
 * events which occur during parsing.
 *
 * Note that we could record the parser call stack at the time this event
 * occurred but in the presence of left recursive rules, the stack is kind of
 * meaningless. It's better to look at the individual configurations for their
 * individual stacks. Of course that is a {@link PredictionContext} object
 * not a parse tree node and so it does not have information about the extent
 * (start...stop) of the various subtrees. Examining the stack tops of all
 * configurations provide the return states for the rule invocations.
 * From there you can get the enclosing rule.
 */
class DecisionEventInfo extends BaseObject
{
    /**
     * The invoked decision number which this event is related to.
     *
     * @see ATN#decisionToState
     *
     * @var int
     */
    public $decision;

    /**
     * The configuration set containing additional information relevant to the
     * prediction state when the current event occurred, or {@code null} if no
     * additional information is relevant or available.
     *
     * @var \ANTLR\v4\Runtime\ATN\ATNConfigSet
     */
    public $configs;

    /**
     * The input token stream which is being parsed.
     *
     * @var TokenStream
     */
    public $input;

    /**
     * The token index in the input stream at which the current prediction was
     * originally invoked.
     *
     * @var int
     */
    public $startIndex;

    /**
     * The token index in the input stream at which the current event occurred.
     *
     * @var int
     */
    public $stopIndex;

    /**
     * {@code true} if the current event occurred during LL prediction;
     * otherwise, {@code false} if the input occurred during SLL prediction.
     *
     * @var bool
     */
    public $fullCtx;

    /**
     * @param int $decision
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet $configs
     * @param \ANTLR\v4\Runtime\TokenStream $input
     * @param int $startIndex
     * @param int $stopIndex
     * @param bool $fullCtx
     */
    public function __construct(
        int $decision,
        ATNConfigSet $configs,
        TokenStream $input,
        int $startIndex,
        int $stopIndex,
        bool $fullCtx
    ) {
        $this->decision = $decision;
        $this->configs = $configs;
        $this->input = $input;
        $this->startIndex = $startIndex;
        $this->stopIndex = $stopIndex;
        $this->fullCtx = $fullCtx;
    }
}
