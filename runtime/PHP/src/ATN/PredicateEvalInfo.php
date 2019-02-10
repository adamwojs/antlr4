<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\TokenStream;

/**
 * This class represents profiling event information for semantic predicate
 * evaluations which occur during prediction.
 *
 * @see ParserATNSimulator#evalSemanticContext
 */
class PredicateEvalInfo extends DecisionEventInfo
{
    /**
     * The semantic context which was evaluated.
     *
     * @var \ANTLR\v4\Runtime\ATN\SemanticContext
     */
    public $semctx;

    /**
     * The alternative number for the decision which is guarded by the semantic
     * context {@link #semctx}. Note that other ATN
     * configurations may predict the same alternative which are guarded by
     * other semantic contexts and/or {@link SemanticContext#NONE}.
     *
     * @var int
     */
    public $predictedAlt;

    /**
     * The result of evaluating the semantic context {@link #semctx}.
     *
     * @var bool
     */
    public $evalResult;

    /**
     * Constructs a new instance of the {@link PredicateEvalInfo} class with the
     * specified detailed predicate evaluation information.
     *
     * @param int $decision The decision number
     * @param \ANTLR\v4\Runtime\TokenStream $input The input token stream
     * @param int $startIndex The start index for the current prediction
     * @param int $stopIndex The index at which the predicate evaluation was
     * triggered. Note that the input stream may be reset to other positions for
     * the actual evaluation of individual predicates.
     * @param \ANTLR\v4\Runtime\ATN\SemanticContext $semctx The semantic context which was evaluated
     * @param bool evalResult The results of evaluating the semantic context
     * @param int $predictedAlt The alternative number for the decision which is
     * guarded by the semantic context {@code semctx}. See {@link #predictedAlt}
     * for more information.
     * @param bool $fullCtx {@code true} if the semantic context was
     * evaluated during LL prediction; otherwise, {@code false} if the semantic
     * context was evaluated during SLL prediction
     *
     * @see ParserATNSimulator#evalSemanticContext(SemanticContext, ParserRuleContext, int, boolean)
     * @see SemanticContext#eval(Recognizer, RuleContext)
     */
    public function __construct(
        int $decision,
        TokenStream $input,
        int $startIndex,
        int $stopIndex,
        SemanticContext $semctx,
        bool $evalResult,
        int $predictedAlt,
        bool $fullCtx)
    {
        parent::__construct($decision, new ATNConfigSet(), $input, $startIndex, $stopIndex, $fullCtx);

        $this->semctx = $semctx;
        $this->evalResult = $evalResult;
        $this->predictedAlt = $predictedAlt;
    }
}
