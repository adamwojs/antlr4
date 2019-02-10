<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\ATN\ATNConfigSet;
use ANTLR\v4\Runtime\DFA\DFA;
use ANTLR\v4\Runtime\Misc\BitSet;
use ANTLR\v4\Runtime\Misc\Interval;

/**
 * This implementation of {@link ANTLRErrorListener} can be used to identify
 * certain potential correctness and performance problems in grammars. "Reports"
 * are made by calling {@link Parser#notifyErrorListeners} with the appropriate
 * message.
 *
 * <ul>
 * <li><b>Ambiguities</b>: These are cases where more than one path through the
 * grammar can match the input.</li>
 * <li><b>Weak context sensitivity</b>: These are cases where full-context
 * prediction resolved an SLL conflict to a unique alternative which equaled the
 * minimum alternative of the SLL conflict.</li>
 * <li><b>Strong (forced) context sensitivity</b>: These are cases where the
 * full-context prediction resolved an SLL conflict to a unique alternative,
 * <em>and</em> the minimum alternative of the SLL conflict was found to not be
 * a truly viable alternative. Two-stage parsing cannot be used for inputs where
 * this situation occurs.</li>
 * </ul>
 */
class DiagnosticErrorListener extends BaseErrorListener
{
    /**
     * When {@code true}, only exactly known ambiguities are reported.
     */
    protected $exactOnly;

    /**
     * Initializes a new instance of {@link DiagnosticErrorListener}, specifying
     * whether all ambiguities or only exact ambiguities are reported.
     *
     * @param bool $exactOnly {@code true} to report only exact ambiguities, otherwise
     * {@code false} to report all ambiguities.
     */
    public function __construct(bool $exactOnly = true)
    {
        $this->exactOnly = $exactOnly;
    }

    /**
     * {@inheritdoc}
     */
    public function reportAmbiguity(
        Parser $recognizer,
        DFA $dfa,
        int $startIndex,
        int $stopIndex,
        bool $exact,
        BitSet $ambigAlts,
        ATNConfigSet $configs
    ): void {
        $format = "reportAmbiguity d=%s: ambigAlts=%s, input='%s'";
        $decision = $this->getDecisionDescription($recognizer, $dfa);
        $conflictingAlts = $this->getConflictingAlts($ambigAlts, $configs);
        $text = $recognizer->getTokenStream()->getTextForInterval(Interval::of($stopIndex, $stopIndex));
        $message = sprintf($format, $decision, $conflictingAlts->toString(), $text);

        $recognizer->notifyErrorListeners($message);
    }

    /**
     * {@inheritdoc}
     */
    public function reportAttemptingFullContext(
        Parser $recognizer,
        DFA $dfa,
        int $startIndex,
        int $stopIndex,
        BitSet $conflictingAlts,
        ATNConfigSet $configs
    ): void {
        $format = "reportAttemptingFullContext d=%s, input='%s'";
        $decision = $this->getDecisionDescription($recognizer, $dfa);
        $text = $recognizer->getTokenStream()->getTextForInterval(Interval::of($stopIndex, $stopIndex));
        $message = sprintf($format, $decision, $text);

        $recognizer->notifyErrorListeners($message);
    }

    /**
     * {@inheritdoc}
     */
    public function reportContextSensitivity(
        Parser $recognizer,
        DFA $dfa,
        int $startIndex,
        int $stopIndex,
        int $prediction,
        ATNConfigSet $configs
    ): void {
        $format = "reportContextSensitivity d=%s, input='%s'";
        $decision = $this->getDecisionDescription($recognizer, $dfa);
        $text = $recognizer->getTokenStream()->getTextForInterval(Interval::of($stopIndex, $stopIndex));
        $message = sprintf($format, $decision, $text);

        $recognizer->notifyErrorListeners($message);
    }

    protected function getDecisionDescription(Parser $recognizer, DFA $dfa): string
    {
        $decision = $dfa->decision;
        $ruleIndex = $dfa->atnStartState->ruleIndex;

        $ruleNames = $recognizer->getRuleNames();
        if ($ruleIndex < 0 || $ruleIndex >= count($ruleNames)) {
            return (string)$decision;
        }

        $ruleName = $ruleNames[$ruleIndex];
        if (empty($ruleName)) {
            return (string)$decision;
        }

        return "$decision ($ruleName)";
    }

    /**
     * Computes the set of conflicting or ambiguous alternatives from a
     * configuration set, if that information was not already provided by the
     * parser.
     *
     * @param \ANTLR\v4\Runtime\Misc\BitSet|null reportedAlts The set of conflicting or ambiguous alternatives, as
     * reported by the parser.
     * @param \ANTLR\v4\Runtime\ATN\ATNConfigSet configs The conflicting or ambiguous configuration set.
     *
     * @return \ANTLR\v4\Runtime\Misc\BitSet Returns {@code reportedAlts} if it is not {@code null}, otherwise
     * returns the set of alternatives represented in {@code configs}.
     */
    protected function getConflictingAlts(?BitSet $reportedAlts, ATNConfigSet $configs): BitSet
    {
        if ($reportedAlts !== null) {
            return $reportedAlts;
        }

        $result = new BitSet();
        foreach ($configs as $config) {
            $result->set($config->alt);
        }

        returN $result;
    }
}


