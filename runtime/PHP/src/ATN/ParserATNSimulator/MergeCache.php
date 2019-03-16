<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\ParserATNSimulator;

use ANTLR\v4\Runtime\ATN\PredictionContext;
use ANTLR\v4\Runtime\BaseObject;
use Ds\Map;

/**
 * Replaces DoubleKeyMap<PredictionContext, PredictionContext, PredictionContext>.
 */
final class MergeCache extends BaseObject
{
    /** @var \Ds\Map|null */
    private $data = null;

    public function __construct()
    {
        $this->data = new Map();
    }

    /**
     * Associates a key ($k1, $k2) with a value, replacing a previous association if there
     * was one.
     *
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $k1
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $k2
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $v
     *
     * @return \ANTLR\v4\Runtime\ATN\PredictionContext|null
     */
    public function put(PredictionContext $k1, PredictionContext $k2, PredictionContext $v): ?PredictionContext
    {
        $prev = null;

        $data2 = $this->data->get($k1);
        if ($data2 === null) {
            $data2 = new Map();
            $this->data->put($k1, $data2);
        } else {
            $prev = $data2->get($k2);
        }

        $data2->put($k2, $v);

        return $prev;
    }

    /**
     * Returns the value associated with a key ($k1, $k2) or null if key doesn't exists.
     *
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $k1
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $k2
     *
     * @return \ANTLR\v4\Runtime\ATN\PredictionContext
     */
    public function get(PredictionContext $k1, PredictionContext $k2): ?PredictionContext
    {
        $data2 = $this->data->get($k1, null);
        if ($data2 === null) {
            return null;
        }

        return $data2->get($k2, null);
    }
}
