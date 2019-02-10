<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\DFA\DFAState;
use ANTLR\v4\Runtime\Exception\UnsupportedOperationException;
use ANTLR\v4\Runtime\Misc\IdentityHashMap;

abstract class ATNSimulator extends BaseObject
{
    /** @var \ANTLR\v4\Runtime\ATN\ATN */
    public $atn;

    /**
     * The context cache maps all PredictionContext objects that are equals()
     * to a single cached copy. This cache is shared across all contexts
     * in all ATNConfigs in all DFA states.  We rebuild each ATNConfigSet
     * to use only cached nodes/graphs in addDFAState(). We don't want to
     * fill this during closure() since there are lots of contexts that
     * pop up but are not used ever again. It also greatly slows down closure().
     *
     * <p>This cache makes a huge difference in memory and a little bit in speed.
     * For the Java grammar on java.*, it dropped the memory requirements
     * at the end from 25M to 16M. We don't store any of the full context
     * graphs in the DFA because they are limited to local context only,
     * but apparently there's a lot of repetition there as well. We optimize
     * the config contexts before storing the config set in the DFA states
     * by literally rebuilding them with cached subgraphs only.</p>
     *
     * <p>I tried a cache for use during closure operations, that was
     * whacked after each adaptivePredict(). It cost a little bit
     * more time I think and doesn't save on the overall footprint
     * so it's not worth the complexity.</p>
     *
     * @var \ANTLR\v4\Runtime\ATN\PredictionContextCache
     */
    protected $sharedContextCache;

    public function __construct(ATN $atn, PredictionContextCache $sharedContextCache)
    {
        $this->atn = $atn;
        $this->sharedContextCache = $sharedContextCache;
    }

    public abstract function reset(): void;

    /**
     * Clear the DFA cache used by the current instance. Since the DFA cache may
     * be shared by multiple ATN simulators, this method may affect the
     * performance (but not accuracy) of other parsers which are being used
     * concurrently.
     *
     * @throws \ANTLR\v4\Runtime\Exception\UnsupportedOperationException if the current instance does not
     * support clearing the DFA.
     */
    public function clearDFA(): void
    {
        throw new UnsupportedOperationException("This ATN simulator does not support clearing the DFA.");
    }

    public function getSharedContextCache(): PredictionContextCache
    {
        return $this->sharedContextCache;
    }

    public function getCachedContext(PredictionContext $context): PredictionContext
    {
        if ($this->sharedContextCache === null) {
            return $context;
        }

        return PredictionContext::getCachedContext(
            $context, $this->sharedContextCache, new IdentityHashMap()
        );
    }

    public function createError(): DFAState
    {
        static $error = null;

        if ($error === null) {
            $error = new DFAState(new ATNConfigSet());
            $error->stateNumber = PHP_INT_MAX;
        }

        return $error;
    }
}
