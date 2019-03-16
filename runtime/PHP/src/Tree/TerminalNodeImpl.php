<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Misc\Interval;
use ANTLR\v4\Runtime\Parser;
use ANTLR\v4\Runtime\RuleContext;
use ANTLR\v4\Runtime\Token;

class TerminalNodeImpl extends BaseObject implements TerminalNode
{
    /** @var \ANTLR\v4\Runtime\Token */
    public $symbol;

    /** @var \ANTLR\v4\Runtime\Tree\ParseTree */
    public $parent;

    /**
     * @param \ANTLR\v4\Runtime\Token $token
     */
    public function __construct(Token $token)
    {
        $this->symbol = $token;
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
    public function getSymbol(): Token
    {
        return $this->symbol;
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
    public function setParent(RuleContext $parent): void
    {
        $this->parent = $parent;
    }

    /**
     * {@inheritdoc}
     */
    public function getPayload()
    {
        return $this->symbol;
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceInterval(): Interval
    {
        if ($this->symbol === null) {
            return Interval::getInvalid();
        }

        $index = $this->symbol->getTokenIndex();

        return new Interval($index, $index);
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
        return $visitor->visitTerminal($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getText(): ?string
    {
        return $this->symbol->getText();
    }

    /**
     * {@inheritdoc}
     */
    public function toStringTree(Parser $parser = null): string
    {
        return (string)$this;
    }

    public function __toString(): string
    {
        if ($this->symbol->getType() === Token::EOF) {
            return '<EOF>';
        }

        return $this->symbol->getText();
    }
}
