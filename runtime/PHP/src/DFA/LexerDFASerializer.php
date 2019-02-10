<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\DFA;

use ANTLR\v4\Runtime\Vocabulary;

class LexerDFASerializer extends DFASerializer
{
    /**
     * @param \ANTLR\v4\Runtime\DFA\DFA $dfa
     */
    public function __construct(DFA $dfa)
    {
        parent::__construct($dfa, new Vocabulary());
    }

    /**
     * {@inheritdoc}
     */
    protected function getEdgeLabel(int $i): string
    {
        return "'" . mb_chr($i, mb_internal_encoding()) . "'";
    }
}
