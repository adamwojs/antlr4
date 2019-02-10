<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\Misc\MurmurHash;
use ANTLR\v4\Runtime\Misc\ObjectEqualityComparator;

class LexerATNConfig extends ATNConfig
{
    /** @var \ANTLR\v4\Runtime\ATN\LexerActionExecutor */
    private $lexerActionExecutor;

    /** @var bool */
    private $passedThroughNonGreedyDecision = false;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $state
     * @param int $alt
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $context
     * @param int $reachesIntoOuterContext
     * @param \ANTLR\v4\Runtime\ATN\SemanticContext $semanticContext
     * @param \ANTLR\v4\Runtime\ATN\LexerActionExecutor|null $lexerActionExecutor
     * @param bool $passedThroughNonGreedyDecision
     */
    public function __construct(
        ATNState $state,
        int $alt,
        PredictionContext $context,
        int $reachesIntoOuterContext,
        SemanticContext $semanticContext,
        ?LexerActionExecutor $lexerActionExecutor = null,
        bool $passedThroughNonGreedyDecision = false
    ) {
        parent::__construct($state, $alt, $context, $reachesIntoOuterContext, $semanticContext);

        $this->lexerActionExecutor = $lexerActionExecutor;
        $this->passedThroughNonGreedyDecision = $passedThroughNonGreedyDecision;
    }

    public function hasPassedThroughNonGreedyDecision(): bool
    {
        return $this->passedThroughNonGreedyDecision;
    }

    /**
     * Gets the {@link LexerActionExecutor} capable of executing the embedded
     * action(s) for the current configuration.
     *
     * @return \ANTLR\v4\Runtime\ATN\LexerActionExecutor|null
     */
    public function getLexerActionExecutor(): ?LexerActionExecutor
    {
        return $this->lexerActionExecutor;
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        $hash = MurmurHash::initialize(7);
        $hash = MurmurHash::update($hash, $this->state->stateNumber);
        $hash = MurmurHash::update($hash, $this->alt);
        $hash = MurmurHash::update($hash, $this->context->hash());
        $hash = MurmurHash::update($hash, $this->semanticContext->hash());
        $hash = MurmurHash::update($hash, (int)$this->passedThroughNonGreedyDecision);
        $hash = MurmurHash::finish($hash, 6);

        return $hash;
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($this === $o) {
            return true;
        }

        if(!($o instanceof LexerATNConfig)) {
            return false;
        }

        if ($this->passedThroughNonGreedyDecision !== $o->passedThroughNonGreedyDecision) {
            return false;
        }

        if (!ObjectEqualityComparator::getInstance()->equalsTo($this->lexerActionExecutor, $o->lexerActionExecutor)) {
            return false;
        }

        return parent::equals($o);
    }

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNState $state
     * @param int $alt
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext $context
     * @param \ANTLR\v4\Runtime\ATN\LexerActionExecutor|null $lexerActionExecutor
     *
     * @return \ANTLR\v4\Runtime\ATN\LexerATNConfig
     */
    public static function createFromATNStateAltAndPredicationContext(
        ATNState $state,
        int $alt,
        PredictionContext $context,
        LexerActionExecutor $lexerActionExecutor = null
    ): LexerATNConfig {
        return new LexerATNConfig($state, $alt, $context, 0, SemanticContext::NONE(), $lexerActionExecutor, false);
    }

    /**
     * @param \ANTLR\v4\Runtime\ATN\LexerATNConfig $c
     * @param \ANTLR\v4\Runtime\ATN\ATNState $state
     * @param \ANTLR\v4\Runtime\ATN\PredictionContext|null $context
     * @param \ANTLR\v4\Runtime\ATN\LexerActionExecutor|null $lexerActionExecutor
     *
     * @return \ANTLR\v4\Runtime\ATN\LexerATNConfig
     */
    public static function createFromLexerATNConfigAndATNState(
        LexerATNConfig $c,
        ATNState $state,
        PredictionContext $context = null,
        LexerActionExecutor $lexerActionExecutor = null
    ): LexerATNConfig {
        $passedThroughNonGreedyDecision = $c->passedThroughNonGreedyDecision ||
            ($state instanceof DecisionState && $state->nonGreedy);

        if ($context === null) {
            $context = $c->context;
        }

        if ($lexerActionExecutor === null) {
            $lexerActionExecutor = $c->lexerActionExecutor;
        }

        return new LexerATNConfig($state, $c->alt, $context, $c->reachesIntoOuterContext, $c->semanticContext, $lexerActionExecutor, $passedThroughNonGreedyDecision);
    }
}
