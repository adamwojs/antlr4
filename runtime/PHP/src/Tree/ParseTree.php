<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

use ANTLR\v4\Runtime\Parser;
use ANTLR\v4\Runtime\RuleContext;

/**
 * An interface to access the tree of {@link RuleContext} objects created
 * during a parse that makes the data structure look like a simple parse tree.
 * This node represents both internal nodes, rule invocations,
 * and leaf nodes, token matches.
 *
 * <p>The payload is either a {@link Token} or a {@link RuleContext} object.</p>
 */
interface ParseTree extends SyntaxTree
{
    /**
     * Set the parent for this node.
     *
     * This is not backward compatible as it changes
     * the interface but no one was able to create custom
     * nodes anyway so I'm adding as it improves internal
     * code quality.
     *
     * One could argue for a restructuring of
     * the class/interface hierarchy so that
     * setParent, addChild are moved up to Tree
     * but that's a major change. So I'll do the
     * minimal change, which is to add this method.
     *
     * @param \ANTLR\v4\Runtime\RuleContext $parent
     *
     * @return mixed
     */
    public function setParent(RuleContext $parent): void;

    /**
     * The {@link ParseTreeVisitor} needs a double dispatch method.
     *
     * @param \ANTLR\v4\Runtime\Tree\ParseTreeVisitor $visitor
     *
     * @return mixed
     */
    public function accept(ParseTreeVisitor $visitor);

    /**
     * Return the combined text of all leaf nodes. Does not get any
     * off-channel tokens (if any) so won't return whitespace and
     * comments if they are sent to parser on hidden channel.
     *
     * @return string|null
     */
    public function getText(): ?string;

    /**
     * Specialize toStringTree so that it can print out more information
     * based upon the parser.
     *
     * @param \ANTLR\v4\Runtime\Parser $parser
     *
     * @return string
     */
    public function toStringTree(Parser $parser = null): string;
}
