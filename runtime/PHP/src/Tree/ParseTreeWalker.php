<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

use ANTLR\v4\Runtime\BaseObject;

class ParseTreeWalker extends BaseObject
{
    public function walk(ParseTreeListener $listener, ParseTree $tree): void
    {
        if ($tree instanceof ErrorNode) {
            $listener->visitErrorNode($tree);

            return;
        }

        if ($tree instanceof TerminalNode) {
            $listener->visitTerminal($tree);

            return;
        }

        $this->enterRule($listener, $tree);
        for ($i = 0; $i < $tree->getChildCount(); $i++) {
            $this->walk($listener, $tree->getChild($i));
        }
        $this->exitRule($listener, $tree);
    }

    /**
     * The discovery of a rule node, involves sending two events: the generic
     * {@link ParseTreeListener#enterEveryRule} and a
     * {@link RuleContext}-specific event. First we trigger the generic and then
     * the rule specific. We to them in reverse order upon finishing the node.
     *
     * @param \ANTLR\v4\Runtime\Tree\ParseTreeListener $listener
     * @param \ANTLR\v4\Runtime\Tree\RuleNode $node
     */
    protected function enterRule(ParseTreeListener $listener, RuleNode $node): void
    {
        $ctx = $node->getRuleContext();
        $listener->enterEveryRule($ctx);
        $ctx->enterRule($listener);
    }

    protected function exitRule(ParseTreeListener $listener, RuleNode $node): void
    {
        $ctx = $node->getRuleContext();
        $ctx->exitRule($listener);
        $listener->exitEveryRule($ctx);
    }

    public static function getDefault(): self
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new self();
        }

        return $instance;
    }
}
