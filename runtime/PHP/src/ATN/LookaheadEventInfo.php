<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\TokenStream;

/**
 * This class represents profiling event information for tracking the lookahead
 * depth required in order to make a prediction.
 */
class LookaheadEventInfo extends DecisionEventInfo
{
    /**
     * The alternative chosen by adaptivePredict(), not necessarily
     * the outermost alt shown for a rule; left-recursive rules have
     * user-level alts that differ from the rewritten rule with a (...) block
     * and a (..)* loop.
     *
     * @var int
     */
    public $predictedAlt;

    /**
     * Constructs a new instance of the {@link LookaheadEventInfo} class with
     * the specified detailed lookahead information.
     *
     * @param int $decision The decision number
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet configs The final configuration set containing the necessary
     * information to determine the result of a prediction, or {@code null} if
     * the final configuration set is not available
     * @param int $predictedAlt
     * @param \ANTLR\v4\Runtime\TokenStream $input The input token stream
     * @param int $startIndex The start index for the current prediction
     * @param int $stopIndex The index at which the prediction was finally made
     * @param bool $fullCtx {@code true} if the current lookahead is part of an LL
     * prediction; otherwise, {@code false} if the current lookahead is part of
     * an SLL prediction
     */
    public function __construct(
        int $decision,
        ATNConfigSet $configs,
        int $predictedAlt,
        TokenStream $input,
        int $startIndex,
        int $stopIndex,
        bool $fullCtx
    ) {
        parent::__construct($decision, $configs, $input, $startIndex, $stopIndex, $fullCtx);

        $this->predictedAlt = $predictedAlt;
    }
}
