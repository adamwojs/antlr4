<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Misc\IdentityHashMap;
use ANTLR\v4\Runtime\RuleContext;
use Ds\Map;

abstract class PredictionContext extends BaseObject
{
    /**
     * Represents {@code $} in an array in full context mode, when {@code $}
     * doesn't mean wildcard: {@code $ + x = [$,x]}. Here,
     * {@code $} = {@link #EMPTY_RETURN_STATE}.
     *
     * @var int
     */
    public const EMPTY_RETURN_STATE = PHP_INT_MAX;

    /** @var int */
    public $id;

    /**
     * Stores the computed hash code of this {@link PredictionContext}
     *
     * @var int
     */
    public $cachedHashCode;

    /** @var int */
    public static $globalNodeCount = 0;

    /**
     * @param int $cachedHashCode
     */
    protected function __construct(int $cachedHashCode)
    {
        $this->id = self::$globalNodeCount++;
        $this->cachedHashCode = $cachedHashCode;
    }

    public abstract function getParent(int $index): ?self;

    /**
     * This means only the {@link #EMPTY} (wildcard? not sure) context is in set.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this === self::createEmpty();
    }

    public static function createEmpty(): EmptyPredictionContext
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new EmptyPredictionContext();
        }

        return $instance;
    }

    public function hasEmptyPath(): bool
    {
        // since EMPTY_RETURN_STATE can only appear in the last position, we check last one
        return $this->getReturnState($this->size() - 1) === self::EMPTY_RETURN_STATE;
    }

    public abstract function getReturnState(int $index): int;

    public abstract function size(): int;

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        return $this->cachedHashCode;
    }

    public static function fromRuleContext(ATN $atn, RuleContext $outerContext): self
    {
        if ($outerContext === null) {
            $outerContext = RuleContext::createEmpty();
        }

        // if we are in RuleContext of start rule, s, then PredictionContext
        // is EMPTY. Nobody called us. (if we are empty, return empty)
        if ($outerContext->parent === null || $outerContext === RuleContext::createEmpty()) {
            return self::createEmpty();
        }

        // If we have a parent, convert it to a PredictionContext graph
        $parent = PredictionContext::fromRuleContext($atn, $outerContext->parent);
        /** @var \ANTLR\v4\Runtime\ATN\RuleTransition $transition */
        $transition = $atn->states[$outerContext->invokingState]->transition(0);

        return SingletonPredictionContext::create($parent, $transition->followState->stateNumber);
    }

    public static function merge(
        PredictionContext $a,
        PredictionContext $b,
        bool $rootIsWildcard,
        array $mergeCache
    ): PredictionContext
    {
        // must be empty context, never null
        assert($a !== null && $b !== null);

        // share same graph if both same
        if ($a === $b || $a->equals($b)) {
            return $a;
        }

        if ($a instanceof SingletonPredictionContext && $b instanceof SingletonPredictionContext) {
            return self::mergeSingletons($a, $b, $rootIsWildcard, $mergeCache);
        }

        // At least one of a or b is array
        // If one is $ and rootIsWildcard, return $ as * wildcard
        if ($rootIsWildcard) {
            if ($a instanceof EmptyPredictionContext) {
                return $a;
            }

            if ($b instanceof EmptyPredictionContext) {
                return $b;
            }
        }

        // convert singleton so both are arrays to normalize
        if ($a instanceof SingletonPredictionContext) {
            $a = $a->asArrayPredicationContext();
        }

        if ($b instanceof SingletonPredictionContext) {
            $b = $b->asArrayPredicationContext();
        }

        return self::mergeArrays($a, $b, $rootIsWildcard, $mergeCache);
    }

    /**
     * Merge two {@link SingletonPredictionContext} instances.
     *
     * <p>Stack tops equal, parents merge is same; return left graph.<br>
     * <embed src="images/SingletonMerge_SameRootSamePar.svg" type="image/svg+xml"/></p>
     *
     * <p>Same stack top, parents differ; merge parents giving array node, then
     * remainders of those graphs. A new root node is created to point to the
     * merged parents.<br>
     * <embed src="images/SingletonMerge_SameRootDiffPar.svg" type="image/svg+xml"/></p>
     *
     * <p>Different stack tops pointing to same parent. Make array node for the
     * root where both element in the root point to the same (original)
     * parent.<br>
     * <embed src="images/SingletonMerge_DiffRootSamePar.svg" type="image/svg+xml"/></p>
     *
     * <p>Different stack tops pointing to different parents. Make array node for
     * the root where each element points to the corresponding original
     * parent.<br>
     * <embed src="images/SingletonMerge_DiffRootDiffPar.svg" type="image/svg+xml"/></p>
     *
     * @param \ANTLR\v4\Runtime\ATN\SingletonPredictionContext $a the first {@link SingletonPredictionContext}
     * @param \ANTLR\v4\Runtime\ATN\SingletonPredictionContext $b the second {@link SingletonPredictionContext}
     * @param bool $rootIsWildcard {@code true} if this is a local-context merge,
     * otherwise false to indicate a full-context merge
     * @param array mergeCache
     *
     * @return \ANTLR\v4\Runtime\ATN\PredictionContext
     */
    private static function mergeSingletons(
        SingletonPredictionContext $a,
        SingletonPredictionContext $b,
        bool $rootIsWildcard,
        array $mergeCache
    ): PredictionContext
    {
        $rootMerge = self::mergeRoot($a, $b, $rootIsWildcard);
        if ($rootMerge !== null) {
            return $rootMerge;
        }

        if ($a->returnState === $b->returnState) { // a == b
            $parent = self::merge($a, $b, $rootIsWildcard, $mergeCache);
            // if parent is same as existing a or b parent or reduced to a parent, return it
            if ($parent === $a->parent) {
                // ax + bx = ax, if a=b
                return $a;
            }

            if ($parent === $b->parent) {
                // ax + bx = bx, if a=b
                return $b;
            }

            // else: ax + ay = a'[x,y]
            // merge parents x and y, giving array node with x,y then remainders
            // of those graphs.  dup a, a' points at merged array
            // new joined parent so create new singleton pointing to it, a'
            $a_ = SingletonPredictionContext::create($parent, $a->returnState);
            return $a_;
        }
        else {
            // a != b payloads differ
            // see if we can collapse parents due to $+x parents if local ctx
            $singleParent = null;

            if ($a === $b || ($a->parent !== null) && $a->parent->equals($b->parent)) {
                // ax + bx = [a,b]x
                $singleParent = $a->parent;
            }

            if ($singleParent !== null) {
                // parents are same
                // sort payloads and use same parent
                $payloads = [$a->returnState, $b->returnState];
                if ($a->returnState > $b->returnState) {
                    $payloads = array_reverse($payloads);
                }

                $parents = [$singleParent, $singleParent];

                $a_ = new ArrayPredictionContext($parents, $payloads);
                return $a_;
            }

            // parents differ and can't merge them. Just pack together
            // into array; can't merge.
            // ax + by = [ax,by]
            $payloads = [$a->returnState, $b->returnState];
            $parents  = [$a->parent, $b->parent];
            if ($a->returnState > $b->returnState) {
                $payloads = array_reverse($payloads);
                $parents  = array_reverse($parents);
            }

            $a_ = new ArrayPredictionContext($parents, $payloads);
            return $a_;
        }
    }

    /**
     * Handle case where at least one of {@code a} or {@code b} is
     * {@link #EMPTY}. In the following diagrams, the symbol {@code $} is used
     * to represent {@link #EMPTY}.
     *
     * <h2>Local-Context Merges</h2>
     *
     * <p>These local-context merge operations are used when {@code rootIsWildcard}
     * is true.</p>
     *
     * <p>{@link #EMPTY} is superset of any graph; return {@link #EMPTY}.<br>
     * <embed src="images/LocalMerge_EmptyRoot.svg" type="image/svg+xml"/></p>
     *
     * <p>{@link #EMPTY} and anything is {@code #EMPTY}, so merged parent is
     * {@code #EMPTY}; return left graph.<br>
     * <embed src="images/LocalMerge_EmptyParent.svg" type="image/svg+xml"/></p>
     *
     * <p>Special case of last merge if local context.<br>
     * <embed src="images/LocalMerge_DiffRoots.svg" type="image/svg+xml"/></p>
     *
     * <h2>Full-Context Merges</h2>
     *
     * <p>These full-context merge operations are used when {@code rootIsWildcard}
     * is false.</p>
     *
     * <p><embed src="images/FullMerge_EmptyRoots.svg" type="image/svg+xml"/></p>
     *
     * <p>Must keep all contexts; {@link #EMPTY} in array is a special value (and
     * null parent).<br>
     * <embed src="images/FullMerge_EmptyRoot.svg" type="image/svg+xml"/></p>
     *
     * <p><embed src="images/FullMerge_SameRoot.svg" type="image/svg+xml"/></p>
     *
     * @param \ANTLR\v4\Runtime\ATN\SingletonPredictionContext $a the first {@link SingletonPredictionContext}
     * @param \ANTLR\v4\Runtime\ATN\SingletonPredictionContext $b the second {@link SingletonPredictionContext}
     * @param boll $rootIsWildcard {@code true} if this is a local-context merge,
     * otherwise false to indicate a full-context merge
     *
     * @return \ANTLR\v4\Runtime\ATN\PredictionContext|null
     */
    public static function mergeRoot(
        SingletonPredictionContext $a,
        SingletonPredictionContext $b,
        bool $rootIsWildcard
    ): ?PredictionContext {
        $EMPTY = self::createEmpty();

        if ($rootIsWildcard) {
            if ($a === $EMPTY) {
                // * + b = *
                return $EMPTY;
            }

            if ($b === $EMPTY) {
                // a + * = *
                return $EMPTY;
            }
        } else {
            if ($a === $EMPTY && $b === $EMPTY) {
                // $ + $ = $
                return $EMPTY;
            }

            if ($a === $EMPTY) {
                // $ + x = [x,$]
                return new ArrayPredictionContext([$b->parent, null], [$b->returnState, self::EMPTY_RETURN_STATE]);
            }

            if ($b === $EMPTY) {
                // x + $ = [x,$] ($ is always last if present)
                return new ArrayPredictionContext([$a->parent, null], [$a->returnState, self::EMPTY_RETURN_STATE]);
            }
        }

        return null;
    }

    /**
     * Merge two {@link ArrayPredictionContext} instances.
     *
     * <p>Different tops, different parents.<br>
     * <embed src="images/ArrayMerge_DiffTopDiffPar.svg" type="image/svg+xml"/></p>
     *
     * <p>Shared top, same parents.<br>
     * <embed src="images/ArrayMerge_ShareTopSamePar.svg" type="image/svg+xml"/></p>
     *
     * <p>Shared top, different parents.<br>
     * <embed src="images/ArrayMerge_ShareTopDiffPar.svg" type="image/svg+xml"/></p>
     *
     * <p>Shared top, all shared parents.<br>
     * <embed src="images/ArrayMerge_ShareTopSharePar.svg" type="image/svg+xml"/></p>
     *
     * <p>Equal tops, merge parents and reduce top to
     * {@link SingletonPredictionContext}.<br>
     * <embed src="images/ArrayMerge_EqualTop.svg" type="image/svg+xml"/></p>
     */
    private static function mergeArrays(
        ArrayPredictionContext $a,
        ArrayPredictionContext $b,
        bool $rootIsWildcard,
        array $mergeCache
    ): PredictionContext {
        if ($mergeCache !== null) {
            // TODO: Missing cache implementation
        }

        // merge sorted payloads a + b => M
        $i = 0; // walks a
        $j = 0; // walks b
        $k = 0; // walks target M array

        $mergedReturnStates = [];
        $mergedParents = [];

        // walk and merge to yield mergedParents, mergedReturnStates
        while ($i < count($a->returnStates) && $j < count($b->returnStates)) {
            $a_parent = $a->parents[$i];
            $b_parent = $b->parents[$j];

            if ($a->returnStates[$i] === $b->returnStates[$j]) {
                // same payload (stack tops are equal), must yield merged singleton
                $payload = $a->returnStates[$i];
                // $ + $ = $
                $bothE = $payload === self::EMPTY_RETURN_STATE && $a_parent === null && $b_parent === null;
                $ax_ax = ($a_parent !== null && $b_parent !== null) && $a_parent->equals($b_parent);

                if ($bothE || $ax_ax) {
                    $mergedParents[$k] = $a_parent; // choose left
                    $mergedReturnStates[$k] = $payload;
                } else {
                    // ax + ay -> a'[x,y]
                    $mergedParents[$k] = self::merge($a_parent, $b_parent, $rootIsWildcard, $mergeCache);
                    $mergedReturnStates[$k] = $payload;
                }

                $i++; // hop over left one as usual
                $j++; // but also skip one in right side since we merge
            } else if ($a->returnStates[$i] < $b->returnStates[$j]) {
                //copy $a[i] to M
                $mergedParents[$k] = $a_parent;
                $mergedReturnStates[$k] = $a->returnStates[$i];

                $i++;
            } else {
                // $b > $a, copy $b[j] to M
                $mergedParents[$k] = $b_parent;
                $mergedReturnStates[$k] = $b->returnStates[$j];

                $j++;
            }
            $k++;
        }

        // copy over any payloads remaining in either array
        if ($i < count($a->returnStates)) {
            for ($p = $i; $p < count($a->returnStates); $p++) {
                $mergedParents[$k] = $a->parents[$p];
                $mergedReturnStates[$k] = $a->returnStates[$p];
                $k++;
            }
        } else {
            for ($p = $j; $p < count($b->returnStates); $p++) {
                $mergedParents[$k] = $b->parents[$p];
                $mergedReturnStates[$k] = $b->returnStates[$p];
                $k++;
            }
        }

        // trim merged if we combined a few that had same stack tops
        if ($k < count($mergedParents)) {
            // write index < last position; trim
            if ($k === 1) {
                return SingletonPredictionContext::create($mergedParents[0], $mergedReturnStates[0]);
            }

            $mergedParents = array_pad($mergedParents, $k, null);
            $mergedReturnStates = array_pad($mergedReturnStates, $k, null);
        }

        $merged = new ArrayPredictionContext($mergedParents, $mergedReturnStates);

        // if we created same array as a or b, return that instead
        // TODO: track whether this is possible above during merge sort for speed
        if ($merged->equals($a)) {
            // TODO: Missing cache implementation
            return $a;
        }

        if ($merged->equals($b)) {
            // TODO: Missing cache implementation
            return $b;
        }

        self::combineCommonParents($merged);

        // TODO: Missing cache implementation
        return $merged;
    }

    /**
     * Make pass over all <em>M</em> {@code parents}; merge any {@code equals()}
     * ones.
     *
     * @param \ANTLR\v4\Runtime\ATN\ArrayPredictionContext $context
     */
    private static function combineCommonParents(ArrayPredictionContext $context): void
    {
        $uniqueParents = new Map();
        foreach ($context->parents as $parent) {
            if (!$uniqueParents->hasKey($parent)) {
                $uniqueParents->put($parent, $parent);
            }
        }

        foreach ($context->parents as $i => $parent) {
            $context->parents[$i] = $uniqueParents->get($parent);
        }
    }

    public static function toDOTString(): string
    {
        // TODO: Missing \ANTLR\v4\Runtime\ATN\PredictionContext::toDOTString implementation
    }

    public static function getCachedContext(
        PredictionContext $context,
        PredictionContextCache $cache,
        IdentityHashMap $visited
    ): PredictionContext {
        if ($context->isEmpty()) {
            return $context;
        }

        if (($existing = $cache->get($context)) !== null) {
            $visited->put($context, $existing);
            return $existing;
        }

        $changed = false;
        $parents = [];
        for ($i = 0; $i < $context->size(); $i++) {
            $parent = self::getCachedContext($context->getParent($i), $cache, $visited);
            if ($changed || $parent !== $context->getParent($i)) {
                if (!$changed) {
                    $parents = [];
                    for ($j = 0; $j < $context->size(); $j++) {
                        $parents[$j] = $context->getParent($j);
                    }

                    $changed = true;
                }

                $parents[$i] = $parent;
            }
        }

        if (!$changed) {
            $cache->add($context);
            $visited->put($context, $context);
            return $context;
        }

        $updated = null;
        switch(count($parents)) {
            case 0:
                $updated = self::createEmpty();
                break;
            case 1:
                $updated = SingletonPredictionContext::create($parents[0], $context->getReturnState(0));
                break;
            default: {
                if (!($context instanceof ArrayPredictionContext)) {
                    throw new \RuntimeException('Expected that $context is instance of ArrayPredictionContext');
                }

                $updated = new ArrayPredictionContext($parents, $context->returnStates);
            }
        }

        $cache->add($updated);
        $visited->put($updated, $updated);
        $visited->put($context, $updated);

        return $updated;
    }
}
