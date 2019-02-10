<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\ATN\ATNConfigSet;
use ANTLR\v4\Runtime\DFA\DFA;
use ANTLR\v4\Runtime\Exception\RecognitionException;
use ANTLR\v4\Runtime\Misc\BitSet;

/**
 * This implementation of {@link ANTLRErrorListener} dispatches all calls to a
 * collection of delegate listeners. This reduces the effort required to support multiple
 * listeners.
 */
class ProxyErrorListener implements ANTLRErrorListener
{
    /** @var \ANTLR\v4\Runtime\ANTLRErrorListener[] */
    private $delegates;

    /**
     * @param \ANTLR\v4\Runtime\ANTLRErrorListener[] $delegates
     */
    public function __construct(array $delegates)
    {
        $this->delegates = $delegates;
    }

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
        foreach ($this->delegates as $listener) {
            $listener->syntaxError($recognizer, $offendingSymbol, $line, $charPositionInLine, $msg, $e);
        }
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
        foreach ($this->delegates as $listener) {
            $listener->reportAmbiguity($recognizer, $dfa, $startIndex, $stopIndex, $exact, $ambigAlts, $configs);
        }
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
        foreach ($this->delegates as $listener) {
            $listener->reportAttemptingFullContext($recognizer, $dfa, $startIndex, $stopIndex, $conflictingAlts, $configs);
        }
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
        foreach ($this->delegates as $listener) {
            $listener->reportContextSensitivity($recognizer, $dfa, $startIndex, $stopIndex, $prediction, $configs);
        }
    }
}
