<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Misc\IdentityHashMap;

/**
 * Associate a property with a parse tree node. Useful with parse tree listeners
 * that need to associate values with particular tree nodes, kind of like
 * specifying a return value for the listener event method that visited a
 * particular node. Example:.
 *
 * <pre>
 * ParseTreeProperty&lt;Integer&gt; values = new ParseTreeProperty&lt;Integer&gt;();
 * values.put(tree, 36);
 * int x = values.get(tree);
 * values.removeFrom(tree);
 * </pre>
 *
 * You would make one decl (values here) in the listener and use lots of times
 * in your event methods.
 */
class ParseTreeProperty extends BaseObject
{
    /** @var \ANTLR\v4\Runtime\Misc\IdentityHashMap */
    protected $annotations;

    public function __construct()
    {
        $this->annotations = new IdentityHashMap();
    }

    public function get(ParseTree $node)
    {
        return $this->annotations->get($node);
    }

    public function put(ParseTree $node, $value): void
    {
        $this->annotations->put($node, $value);
    }

    public function removeFrom(ParseTree $node)
    {
        return $this->annotations->remove($node);
    }
}
