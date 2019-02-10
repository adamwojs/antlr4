<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\ATN\ATN;
use ANTLR\v4\Runtime\Misc\Interval;
use ANTLR\v4\Runtime\Tree\ParseTreeVisitor;
use ANTLR\v4\Runtime\Tree\RuleNode;
use ANTLR\v4\Runtime\Tree\Tree;
use ANTLR\v4\Runtime\Tree\Trees;

/**
 * A rule context is a record of a single rule invocation.
 *
 * We form a stack of these context objects using the parent
 * pointer. A parent pointer of null indicates that the current
 * context is the bottom of the stack. The ParserRuleContext subclass
 * as a children list so that we can turn this data structure into a
 * tree.
 *
 * The root node always has a null pointer and invokingState of -1.
 *
 * Upon entry to parsing, the first invoked rule function creates a
 * context object (a subclass specialized for that rule such as
 * SContext) and makes it the root of a parse tree, recorded by field
 * Parser._ctx.
 *
 * public final SContext s() throws RecognitionException {
 *     SContext _localctx = new SContext(_ctx, getState()); <-- create new node
 *     enterRule(_localctx, 0, RULE_s);                     <-- push it
 *     ...
 *     exitRule();                                          <-- pop back to _localctx
 *     return _localctx;
 * }
 *
 * A subsequent rule invocation of r from the start rule s pushes a
 * new context object for r whose parent points at s and use invoking
 * state is the state with r emanating as edge label.
 *
 * The invokingState fields from a context object to the root
 * together form a stack of rule indication states where the root
 * (bottom of the stack) has a -1 sentinel value. If we invoke start
 * symbol s then call r1, which calls r2, the  would look like
 * this:
 *
 *    SContext[-1]   <- root node (bottom of the stack)
 *    R1Context[p]   <- p in rule s called r1
 *    R2Context[q]   <- q in rule r1 called r2
 *
 * So the top of the stack, _ctx, represents a call to the current
 * rule and it holds the return address from another rule that invoke
 * to this rule. To invoke a rule, we must always have a current context.
 *
 * The parent contexts are useful for computing lookahead sets and
 * getting error information.
 *
 * These objects are used during parsing and prediction.
 * For the special case of parsers, we use the subclass
 * ParserRuleContext.
 *
 * @see \ANTLR\v4\Runtime\ParserRuleContext
 */
class RuleContext extends BaseObject implements RuleNode
{
    /**
     * What context invoked this rule?
     *
     * @var \ANTLR\v4\Runtime\RuleContext
     */
    public $parent;

    /**
     * What state invoked the rule associated with this context?
     * The "return address" is the followState of invokingState
     * If parent is null, this should be -1 this context object represents
     * the start rule.
     *
     * @var int
     */
    public $invokingState = -1;

    /**
     * @param \ANTLR\v4\Runtime\RuleContext|null $parent
     * @param int $invokingState
     */
    public function __construct(?RuleContext $parent = null, int $invokingState = 1)
    {
        $this->parent = $parent;
        $this->invokingState = $invokingState;
    }

    public function depth(): int
    {
        $n = 0;
        $p = $this;
        while ($p !== null) {
            $p = $p->parent;
            $n++;
        }

        return $n;
    }

    /**
     * A context is empty if there is no invoking state; meaning nobody called
     * current context.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->invokingState === -1;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceInterval(): Interval
    {
        return Interval::getInvalid();
    }

    /**
     * {@inheritdoc}
     */
    public function getRuleContext(): RuleContext
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(): Tree
    {
        return $this->parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload()
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * Return the combined text of all child nodes. This method only considers
     * tokens which have been added to the parse tree.
     *
     * Since tokens on hidden channels (e.g. whitespace or comments) are not
     * added to the parse trees, they will not appear in the output of this
     * method.
     *
     * @return string|null
     */
    public function getText(): ?string
    {
        $text = "";

        for ($i = 0; $i < $this->getChildCount(); $i++) {
            // TODO: \ANTLR\v4\Runtime\Tree\Tree::getText doesn't exists
            $text .= $this->getChild($i)->getText();
        }

        return $text;
    }

    public function getRuleIndex(): int
    {
        return -1;
    }

    /**
     * For rule associated with this parse tree internal node, return
     * the outer alternative number used to match the input. Default
     * implementation does not compute nor store this alt num. Create
     * a subclass of ParserRuleContext with backing field and set
     * option contextSuperClass.
     * to set it.
     *
     * @return int
     */
    public function getAltNumber(): int
    {
        return ATN::INVALID_ALT_NUMBER;
    }

    /**
     * Set the outer alternative number for this context node. Default
     * implementation does nothing to avoid backing field overhead for
     * trees that don't need it. Create a subclass of ParserRuleContext
     * with backing field and set option contextSuperClass.
     *
     * @param int $altNumber
     */
    public function setAltNumber(int $altNumber): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function setParent(RuleContext $parent): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getChild(int $i): ?Tree
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getChildCount(): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function accept(ParseTreeVisitor $visitor)
    {
        return $visitor->visitChildren($this);
    }

    /**
     * {@inheritdoc}
     *
     * Print out a whole tree, not just a node, in LISP format
     * (root child1 .. childN). Print just a node if this is a leaf.
     * We have to know the recognizer so we can get rule names.
     */
    public function toStringTree(Parser $parser = null): string
    {
        return Trees::toStringTree($this, $parser);
    }

    public function toString(array $ruleNames = null, ?RuleContext $stop = null): string
    {
        $str = "";

        $p = $this;
        while ($p !== null && $p !== $stop) {
            if ($ruleNames === null) {
                if (!$p->isEmpty()) {
                    $str .= (string)$p->invokingState;
                }
            } else {
                $idx = $p->getRuleIndex();
                if ($idx >= 0 && $idx < count($ruleNames)) {
                    $str .= $ruleNames[$idx];
                } else {
                    $str .= (string)$idx;
                }
            }

            if ($p->parent !== null && ($ruleNames !== null || !$p->parent->isEmpty())) {
                $str .= " ";
            }

            $p = $p->parent;
        }

        return "[$str]";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public static function createEmpty(): RuleContext
    {
        static $empty = null;
        if ($empty === null) {
            $empty = new ParserRuleContext();
        }

        return $empty;
    }
}
