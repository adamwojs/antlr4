<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Misc\Interval;
use ANTLR\v4\Runtime\Tree\ErrorNode;
use ANTLR\v4\Runtime\Tree\ParseTreeListener;
use ANTLR\v4\Runtime\Tree\TerminalNode;
use ANTLR\v4\Runtime\Tree\Tree;

/**
 * A rule invocation record for parsing.
 *
 * Contains all of the information about the current rule not stored in the
 * RuleContext. It handles parse tree children list, Any ATN state
 * tracing, and the default values available for rule invocations:
 * start, stop, rule index, current alt number.
 *
 * Subclasses made for each rule and grammar track the parameters,
 * return values, locals, and labels specific to that rule. These
 * are the objects that are returned from rules.
 *
 * Note text is not an actual field of a rule return value; it is computed
 * from start and stop using the input stream's toString() method.  I
 * could add a ctor to this so that we can pass in and store the input
 * stream, but I'm not sure we want to do that.  It would seem to be undefined
 * to get the .text property anyway if the rule matches tokens from multiple
 * input streams.
 *
 * I do not use getters for fields of objects that are used simply to
 * group values such as this aggregate.  The getters/setters are there to
 * satisfy the superclass interface.
 */
class ParserRuleContext extends RuleContext
{
    /**
     * If we are debugging or building a parse tree for a visitor,
     * we need to track all of the tokens and rule invocations associated
     * with this rule's context. This is empty for parsing w/o tree constr.
     * operation because we don't the need to track the details about
     * how we parse this rule.
     *
     * @var \ANTLR\v4\Runtime\Tree\ParseTree[]
     */
    public $children;

    /** @var \ANTLR\v4\Runtime\Token */
    public $start;

    /** @var \ANTLR\v4\Runtime\Token */
    public $stop;

    /**
     * The exception that forced this rule to return. If the rule successfully
     * completed, this is {@code null}.
     *
     * @var \ANTLR\v4\Runtime\Exception\RecognitionException
     */
    public $exception;

    public function __construct(ParserRuleContext $parent = null, int $invokingStateNumber = -1)
    {
        parent::__construct($parent, $invokingStateNumber);
    }

    /**
     * COPY a ctx (I'm deliberately not using copy constructor) to avoid
     * confusion with creating node with parent. Does not copy children
     * (except error leaves).
     *
     * This is used in the generated parser code to flip a generic XContext
     * node for rule X to a YContext for alt label Y. In that sense, it is
     * not really a generic copy function.
     *
     * If we do an error sync() at start of a rule, we might add error nodes
     * to the generic XContext so this function must copy those nodes to
     * the YContext as well else they are lost!
     *
     * @param \ANTLR\v4\Runtime\ParserRuleContext $ctx
     */
    public function copyFrom($ctx): void
    {
        $this->parent = $ctx->parent;
        $this->invokingState = $ctx->invokingState;

        $this->start = $ctx->start;
        $this->stop = $ctx->stop;

        // copy any error nodes to alt label node
        if ($ctx->children !== null) {
            $this->children = [];

            // reset parent pointer for any error nodes
            foreach ($ctx->children as $child) {
                if ($child instanceof ErrorNode) {
                    $this->addTerminalNodeChild($child);
                }
            }
        }
    }

    // Double dispatch methods for listeners

    public function enterRule(ParseTreeListener $listener): void
    {
        return ;
    }

    public function exitRule(ParseTreeListener $listener): void
    {
        return;
    }

    /**
     * Add a parse tree node to this as a child.  Works for
     * internal and leaf nodes. Does not set parent link;
     * other add methods must do that. Other addChild methods
     * call this.
     *
     * We cannot set the parent pointer of the incoming node
     * because the existing interfaces do not have a setParent()
     * method and I don't want to break backward compatibility for this.
     *
     * TODO: Make sure it's not used anywhere and make it private
     */
    public function addAnyChild($t)
    {
        if ($this->children === null) {
            $this->children = [];
        }

        $this->children[] = $t;

        return $t;

    }

    public function addRuleContextChild(RuleContext $ruleInvocation)
    {
        return $this->addAnyChild($ruleInvocation);
    }

    /**
     * Add a token leaf node child and force its parent to be this node.
     *
     * @param \ANTLR\v4\Runtime\Tree\TerminalNode $terminalNode
     *
     * @return \ANTLR\v4\Runtime\Tree\TerminalNode
     */
    public function addTerminalNodeChild(TerminalNode $terminalNode): TerminalNode
    {
        $terminalNode->setParent($this);

        return $this->addAnyChild($terminalNode);
    }

    /**
     * Add an error node child and force its parent to be this node.
     *
     * @param \ANTLR\v4\Runtime\Tree\ErrorNode $errorNode
     *
     * @return \ANTLR\v4\Runtime\Tree\ErrorNode
     */
    public function addErrorNode(ErrorNode $errorNode): ErrorNode
    {
        $errorNode->setParent($this);

        return $this->addAnyChild($errorNode);
    }

    /**
     * Used by enterOuterAlt to toss out a RuleContext previously added as
     * we entered a rule. If we have # label, we will need to remove
     * generic ruleContext object.
     */
    public function removeLastChild(): void
    {
        if ($this->children !== null) {
            array_pop($this->children);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getChild(int $i): ?Tree
    {
        if ($this->children !== null && $i >= 0 && $i < count($this->children)) {
            return $this->children[$i];
        }

        return null;
    }

    public function getChildOfType(string $ctxType, int $i): ?Tree
    {
        if ($this->children === null || $i < 0 || $i >= count($this->children)) {
            return null;
        }

        $j = -1;
        foreach ($this->children as $child) {
            if ($child instanceof $ctxType) {
                $j++;
                if ($j === $i) {
                    return $child;
                }
            }
        }

        return null;
    }

    public function getToken(int $ttype, int $i): ?TerminalNode
    {
        if ($this->children === null || $i < 0 || $i >= count($this->children)) {
            return null;
        }

        $j = -1;
        foreach ($this->children as $child) {
            if ($child instanceof TerminalNode) {
                if ($child->getSymbol()->getType() === $ttype) {
                    $j++;
                    if ($j === $i) {
                        return $child;
                    }
                }
            }
        }

        return null;
    }

    public function getTokens(int $ttype): array
    {
        if ($this->children === null) {
            return [];
        }

        $tokens = [];

        foreach ($this->children as $child) {
            if ($child instanceof TerminalNode) {
                if ($child->getSymbol()->getType() === $ttype) {
                    $tokens[] = $child;
                }
            }
        }

        return $tokens;
    }

    /**
     * {@inheritdoc}
     */
    public function getRuleContext(?string $ctxType = null, ?int $i = null): RuleContext
    {
        if ($ctxType === null) {
            return parent::getRuleContext();
        }

        return $this->getChildOfType($ctxType, $i);
    }

    public function getRuleContexts(string $ctxType): array
    {
        if ($this->children === null) {
            return [];
        }

        $contexts = [];

        foreach ($this->children as $child) {
            if ($child instanceof $ctxType) {
                $contexts[] = $child;
            }
        }

        return $contexts;
    }

    /**
     * {@inheritdoc}
     */
    public function getChildCount(): int
    {
        if ($this->children !== null) {
            return count($this->children);
        }

        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceInterval(): Interval
    {
        if ($this->start === null) {
            return Interval::getInvalid();
        }

        if ($this->stop === null || $this->stop->getTokenIndex() < $this->start->getTokenIndex()) {
           return Interval::of($this->start->getTokenIndex(), $this->start->getTokenIndex() - 1); // empty
        }

        return Interval::of($this->start->getTokenIndex(), $this->stop->getTokenIndex());
    }

    /**
     * Get the initial token in this context.
     * Note that the range from start to stop is inclusive, so for rules that do not consume anything
     * (for example, zero length or error productions) this token may exceed stop.
     *
     * @return \ANTLR\v4\Runtime\Token
     */
    public function getStart(): Token
    {
        return $this->start;
    }

    /**
     * Get the final token in this context.
     * Note that the range from start to stop is inclusive, so for rules that do not consume anything
     * (for example, zero length or error productions) this token may precede start.
     *
     * @return \ANTLR\v4\Runtime\Token
     */
    public function getStop(): Token
    {
        return $this->stop;
    }
}
