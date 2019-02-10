<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Parser;

use ANTLR\v4\Runtime\Parser;
use ANTLR\v4\Runtime\ParserRuleContext;
use ANTLR\v4\Runtime\Tree\ErrorNode;
use ANTLR\v4\Runtime\Tree\ParseTreeListener;
use ANTLR\v4\Runtime\Tree\TerminalNode;

class TraceListener implements ParseTreeListener
{
    /** @var \ANTLR\v4\Runtime\Parser */
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * {@inheritdoc}
     */
    public function visitTerminal(TerminalNode $node): void
    {
        $rn = $this->getCurrentRuleName();
        $sm = (string)$node->getSymbol();

        echo "consume $sm rule $rn";
    }

    /**
     * {@inheritdoc}
     */
    public function visitErrorNode(ErrorNode $node): void
    {
        return ;
    }

    /**
     * {@inheritdoc}
     */
    public function enterEveryRule(ParserRuleContext $ctx): void
    {
        $rn = $this->getCurrentRuleName();
        $lt = $this->parser->getTokenStream()->LT(1)->getText();

        echo "enter $rn, LT(1)=$lt";
    }

    /**
     * {@inheritdoc}
     */
    public function exitEveryRule(ParserRuleContext $ctx): void
    {
        $rn = $this->getCurrentRuleName();
        $lt = $this->parser->getTokenStream()->LT(1)->getText();

        echo "exit $rn, LT(1)=$lt";
    }

    private function getCurrentRuleName(): string
    {
        return $this->parser->getRuleNames()[$this->parser->getContext()->getRuleIndex()];
    }
}
