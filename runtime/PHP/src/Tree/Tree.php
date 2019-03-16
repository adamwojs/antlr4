<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

/**
 * The basic notion of a tree has a parent, a payload, and a list of children.
 * It is the most abstract interface for all the trees used by ANTLR.
 */
interface Tree
{
    /**
     * The parent of this node. If the return value is null, then this
     * node is the root of the tree.
     *
     * @return \ANTLR\v4\Runtime\Tree\Tree
     */
    public function getParent(): self;

    /**
     * This method returns whatever object represents the data at this note. For
     * example, for parse trees, the payload can be a {@link Token} representing
     * a leaf node or a {@link RuleContext} object representing a rule
     * invocation. For abstract syntax trees (ASTs), this is a {@link Token}
     * object.
     *
     * @return mixed
     */
    public function getPayload();

    /**
     * If there are children, get the {@code i}th value indexed from 0.
     *
     * @param int $i
     *
     * @return \ANTLR\v4\Runtime\Tree\Tree|null
     */
    public function getChild(int $i): ?self;

    /**
     * How many children are there? If there is none, then this
     * node represents a leaf node.
     *
     * @return int
     */
    public function getChildCount(): int;

    /**
     * Print out a whole tree, not just a node, in LISP format
     * {@code (root child1 .. childN)}. Print just a node if this is a leaf.
     *
     * @return string
     */
    public function toStringTree(): string;
}
