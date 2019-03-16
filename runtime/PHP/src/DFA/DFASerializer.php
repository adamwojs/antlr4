<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\DFA;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\VocabularyInterface;

/**
 * A DFA walker that knows how to dump them to serialized strings.
 */
class DFASerializer extends BaseObject
{
    /** @var \ANTLR\v4\Runtime\DFA\DFA */
    private $dfa;

    /** @var \ANTLR\v4\Runtime\VocabularyInterface */
    private $vocabulary;

    /**
     * @param \ANTLR\v4\Runtime\DFA\DFA $dfa
     * @param \ANTLR\v4\Runtime\VocabularyInterface $vocabulary
     */
    public function __construct(DFA $dfa, VocabularyInterface $vocabulary)
    {
        $this->dfa = $dfa;
        $this->vocabulary = $vocabulary;
    }

    protected function getEdgeLabel(int $i): string
    {
        return $this->vocabulary->getDisplayName($i - 1);
    }

    protected function getStateString(DFAState $s): string
    {
        $str = '';

        if ($s->isAcceptState) {
            $str .= ':';
        }

        $str .= 's';
        $str .= (string)$s->stateNumber;

        if ($s->requiresFullContext) {
            $str .= '^';
        }

        if ($s->isAcceptState) {
            $str .= '=>';
            if ($s->predicates !== null) {
                $str .= implode(', ', array_map(function (PredPrediction $p) {
                    return (string)$p;
                }, $s->predicates));
            } else {
                $str .= (string)$s->predication;
            }
        }

        return $str;
    }

    public function __toString(): string
    {
        if ($this->dfa->s0 === null) {
            return '';
        }

        $output = '';
        foreach ($this->dfa->getStates() as $s) {
            if ($s->edges !== null) {
                foreach ($s->edges as $i => $t) {
                    if ($t !== null && $t->stateNumber !== PHP_INT_MAX) {
                        $output .= "{$this->getStateString($s)}-{$this->getEdgeLabel($i)}->{$this->getStateString($t)}\n";
                    }
                }
            }
        }

        return $output;
    }
}
