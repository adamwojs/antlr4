<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\ATN\ATNConfigSet\ConfigHashSet;
use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Exception\IllegalStateException;
use ANTLR\v4\Runtime\Exception\UnsupportedOperationException;
use ANTLR\v4\Runtime\Misc\BitSet;
use ArrayIterator;
use Traversable;

/**
 * Specialized {@link Set}{@code <}{@link ATNConfig}{@code >} that can track
 * info about the set, with support for combining similar configurations using a
 * graph-structured stack.
 */
class ATNConfigSet extends BaseObject implements \IteratorAggregate
{
    /**
     * All configs but hashed by (s, i, _, pi) not including context. Wiped out
     * when we go readonly as this set becomes a DFA state.
     *
     * @var \ANTLR\v4\Runtime\ATN\ATNConfigSet\AbstractConfigHashSet
     */
    public $configLookup;

    /**
     * Track the elements as they are added to the set; supports get(i)
     *
     * @var \ANTLR\v4\Runtime\ATN\ATNConfig[]
     */
    public $configs = [];

    /** @var int */
    public $uniqueAlt = 0;

    // Used in parser and lexer. In lexer, it indicates we hit a pred
    // while computing a closure operation. Don't make a DFA state from this.
    public $hasSemanticContext = false;
    public $dipsIntoOuterContext = false;

    /**
     * Indicates that this configuration set is part of a full context
     * LL prediction. It will be used to determine how to merge $. With SLL
     * it's a wildcard whereas it is not for LL context merge.
     *
     * @var bool
     */
    public $fullCtx;

    /**
     * Currently this is only used when we detect SLL conflict; this does
     * not necessarily represent the ambiguous alternatives. In fact,
     * I should also point out that this seems to include predicated alternatives
     * that have predicates that evaluate to false. Computed in computeTargetState().
     *
     * @var \ANTLR\v4\Runtime\Misc\BitSet
     */
    public $conflictingAlts;

    /**
     * Indicates that the set of configurations is read-only. Do not
     * allow any code to manipulate the set; DFA states will point at
     * the sets and they must not change. This does not protect the other
     * fields; in particular, conflictingAlts is set after
     * we've made this readonly.
     */
    protected $readonly = false;

    /** @var int */
    private $cachedHashCode = -1;

    public function __construct(bool $fullCtx = true)
    {
        $this->configLookup = new ConfigHashSet();
        $this->fullCtx = $fullCtx;
    }

    /**
     * Adding a new config means merging contexts with existing configs for
     * {@code (s, i, pi, _)}, where {@code s} is the
     * {@link ATNConfig#state}, {@code i} is the {@link ATNConfig#alt}, and
     * {@code pi} is the {@link ATNConfig#semanticContext}. We use
     * {@code (s,i,pi)} as key.
     *
     * <p>This method updates {@link #dipsIntoOuterContext} and
     * {@link #hasSemanticContext} when necessary.</p>
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNConfig $config
     * @param mixed $mergeCache
     *
     * @return bool
     */
    public function add(ATNConfig $config, $mergeCache = null): bool
    {
        if ($this->readonly) {
            throw new IllegalStateException("This set is readonly");
        }

        if ($config->semanticContext !== SemanticContext::NONE()) {
            $this->hasSemanticContext = true;
        }

        if ($config->getOuterContextDepth() > 0) {
            $this->dipsIntoOuterContext = true;
        }

        $existing = $this->configLookup->getOrAdd($config);
        if ($existing === $config) {
            $this->cachedHashCode = -1;
            $this->configs[] = $config; // track order here
            return true;
        }

        // a previous (s,i,pi,_), merge with it and save result
        $rootIsWildcard = !$this->fullCtx;

        $merged = PredictionContext::merge(
            $existing->context, $config->context, $rootIsWildcard, /* $mergeCache */ []
        );

        // no need to check for existing.context, config.context in cache
        // since only way to create new graphs is "call rule" and here. We
        // cache at both places.
        $existing->reachesIntoOuterContext = max(
            $existing->reachesIntoOuterContext,
            $config->reachesIntoOuterContext
        );

        // make sure to preserve the precedence filter suppression during the merge
        if ($config->isPrecedenceFilterSuppressed()) {
            $existing->setPrecedenceFilterSuppressed(true);
        }

        // replace context; no need to alt mapping
        $existing->context = $merged;

        return true;
    }

    public function addAll(iterable $collection): bool
    {
        foreach ($collection as $item) {
            $this->add($item);
        }

        return false;
    }

    /**
     * Return a List holding list of configs
     *
     * @return \ANTLR\v4\Runtime\ATN\ATNConfig
     */
    public function getConfigs(): array
    {
        return $this->configs;
    }

    public function getStates(): array
    {
        return array_map(function(ATNConfig $config) {
            return $config->state;
        }, $this->configs);
    }

    /**
     * Return a List holding list of configs
     *
     * @return \ANTLR\v4\Runtime\ATN\ATNConfig[]
     */
    public function elements(): array
    {
        return $this->configs;
    }

    /**
     * Gets the complete set of represented alternatives for the configuration
     * set.
     *
     * @return \ANTLR\v4\Runtime\Misc\BitSet the set of represented alternatives in this configuration set
     */
    public function getAlts(): BitSet
    {
        $alts = new BitSet();

        foreach ($this->configs as $config) {
            $alts->set($config->alt);
        }

        return $alts;
    }

    public function getPredicates(): array
    {
        $preds = [];

        foreach ($this->configs as $config) {
            if ($config->semanticContext !== SemanticContext::NONE()) {
                $preds[] = $config->semanticContext;
            }
        }

        return $preds;
    }

    public function get(int $i): ATNConfig
    {
        return $this->configs[$i];
    }

    public function optimizeConfigs(ATNSimulator $interpreter): void
    {
        if ($this->readonly) {
            throw new IllegalStateException("This set is readonly");
        }

        if ($this->configLookup->isEmpty()) {
            return ;
        }

        foreach ($this->configs as $config) {
            $config->context = $interpreter->getCachedContext($config->context);
        }
    }

    public function size(): int
    {
        return count($this->configs);
    }

    public function isEmpty(): bool
    {
        return empty($this->configs);
    }

    public function contains($o): bool
    {
        if ($this->configLookup === null) {
            throw new UnsupportedOperationException("This method is not implemented for readonly sets.");
        }

        return $this->configLookup->contains($o);
    }

    public function iterator(): iterable
    {
        return new ArrayIterator($this->configs);
    }

    public function clear(): void
    {
        if ($this->readonly) {
            throw new IllegalStateException("This set is readonly");
        }

        $this->configs = [];
        $this->cachedHashCode = -1;
        $this->configLookup->clear();
    }

    public function isReadonly(): bool
    {
        return $this->readonly;
    }

    public function setReadonly(bool $readonly): void
    {
        $this->readonly = $readonly;
        // can't mod, no need for lookup cache
        $this->configLookup = null;
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->configs);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof ATNConfigSet) {
            if (count($this->configs) !== count($o->configs)) {
                return false;
            }

            foreach ($this->configs as $i => $config) {
                if (!$config->equals($o->configs[$i])) {
                    return false;
                }
            }

            return $this->fullCtx === $o->fullCtx
                && $this->uniqueAlt === $o->uniqueAlt
                && $this->conflictingAlts === $o->conflictingAlts
                && $this->hasSemanticContext === $o->hasSemanticContext
                && $this->dipsIntoOuterContext === $o->dipsIntoOuterContext;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        if ($this->isReadonly()) {
            if ($this->cachedHashCode === -1) {
                $this->cachedHashCode = $this->calculateHash();
            }

            return $this->cachedHashCode;
        }

        return $this->calculateHash();
    }

    private function calculateHash(): int
    {
        $hash = 1;
        foreach ($this->configs as $config) {
            $hash = 31 * $hash + ($config === null ? 0 : $config->hash());
        }

        return $hash;
    }

    public function __toString(): string
    {
        // TODO: Missing \ANTLR\v4\Runtime\ATN\ATNConfigSet::__toString implementation
        return "";
    }

    public static function copy(ATNConfigSet $old): ATNConfigSet
    {
        $configSet = new ATNConfigSet($old->fullCtx);
        $configSet->addAll(new ArrayIterator($old->configs));
        $configSet->uniqueAlt = $old->uniqueAlt;
        $configSet->conflictingAlts = $old->conflictingAlts;
        $configSet->hasSemanticContext = $old->hasSemanticContext;
        $configSet->dipsIntoOuterContext = $old->dipsIntoOuterContext;

        return $configSet;
    }
}
