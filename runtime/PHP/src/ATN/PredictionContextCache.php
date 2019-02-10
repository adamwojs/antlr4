<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use Ds\Map;

/**
 * Used to cache {@link PredictionContext} objects. Its used for the shared
 * context cash associated with contexts in DFA states. This cache
 * can be used for both lexers and parsers.
 */
class PredictionContextCache extends BaseObject
{
    /** @var \Ds\Map */
    protected $cache;

    public function __construct()
    {
        $this->cache = new Map();
    }

    /**
     * Add a context to the cache and return it. If the context already exists,
     * return that one instead and do not add a new context to the cache.
     * Protect shared cache from unsafe thread access.
     *
     *  TODO: Probably might not be needed for PHP
     *
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $ctx
     *
     * @return \ANTLR\v4\Runtime\ATN\PredictionContext
     */
    public function add(PredictionContext $ctx): PredictionContext
    {
        $EMPTY = PredictionContext::createEmpty();
        if ($ctx === $EMPTY) {
            return $EMPTY;
        }

        $existing = $this->cache->get($ctx, null);
        if ($existing !== null) {
            return $existing;
        }

        $this->cache->put($ctx, $ctx);

        return $ctx;
    }

    public function get(PredictionContext $ctx): ?PredictionContext
    {
        return $this->cache->get($ctx, null);
    }

    public function size(): int
    {
        return $this->cache->count();
    }
}
