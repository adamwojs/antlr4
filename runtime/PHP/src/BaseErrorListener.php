<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\ATN\ATNConfigSet;
use ANTLR\v4\Runtime\DFA\DFA;
use ANTLR\v4\Runtime\Exception\RecognitionException;
use ANTLR\v4\Runtime\Misc\BitSet;

/**
 * Provides an empty default implementation of {@link ANTLRErrorListener}. The
 * default implementation of each method does nothing, but can be overridden as
 * necessary.
 */
class BaseErrorListener implements ANTLRErrorListener
{
    /**
     * {@inheritdoc}
     */
    public function syntaxError(
        Recognizer $recognizer,
        $offendingSymbol,
        int $line,
        int $charPositionInLine,
        string $msg,
        ?RecognitionException $e
    ): void {
        /* do nothing */
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
        /* do nothing */
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
        /* do nothing */
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
        /* do nothing */
    }
}
