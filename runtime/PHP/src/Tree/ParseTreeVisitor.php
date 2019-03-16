<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

/**
 * This interface defines the basic notion of a parse tree visitor. Generated
 * visitors implement this interface and the {@code XVisitor} interface for
 * grammar {@code X}.
 */
interface ParseTreeVisitor
{
    /**
     * Visit a parse tree, and return a user-defined result of the operation.
     *
     * @param \ANTLR\v4\Runtime\Tree\ParseTree tree The {@link ParseTree} to visit
     *
     * @return mixed the result of visiting the parse tree
     */
    public function visit(ParseTree $tree);

    /**
     * Visit the children of a node, and return a user-defined result of the
     * operation.
     *
     * @param \ANTLR\v4\Runtime\Tree\RuleNode node The {@link RuleNode} whose children should be visited
     *
     * @return mixed the result of visiting the children of the node
     */
    public function visitChildren(RuleNode $node);

    /**
     * Visit a terminal node, and return a user-defined result of the operation.
     *
     * @param \ANTLR\v4\Runtime\Tree\TerminalNode node The {@link TerminalNode} to visit
     *
     * @return mixed the result of visiting the node
     */
    public function visitTerminal(TerminalNode $node);

    /**
     * Visit an error node, and return a user-defined result of the operation.
     *
     * @param \ANTLR\v4\Runtime\Tree\ErrorNode node The {@link ErrorNode} to visit
     *
     * @return mixed the result of visiting the node
     */
    public function visitErrorNode(ErrorNode $node);
}
