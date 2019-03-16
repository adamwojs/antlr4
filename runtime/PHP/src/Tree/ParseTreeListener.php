<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

use ANTLR\v4\Runtime\ParserRuleContext;

/**
 * This interface describes the minimal core of methods triggered
 * by {@link ParseTreeWalker}. E.g.,.
 *
 * 	ParseTreeWalker walker = new ParseTreeWalker();
 *	walker.walk(myParseTreeListener, myParseTree); <-- triggers events in your listener
 *
 * If you want to trigger events in multiple listeners during a single
 * tree walk, you can use the ParseTreeDispatcher object available at
 *
 * https://github.com/antlr/antlr4/issues/841
 */
interface ParseTreeListener
{
    public function visitTerminal(TerminalNode $node): void;

    public function visitErrorNode(ErrorNode $node): void;

    public function enterEveryRule(ParserRuleContext $ctx): void;

    public function exitEveryRule(ParserRuleContext $ctx): void;
}
