<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN\SemanticContext;

use ANTLR\v4\Runtime\ATN\SemanticContext;
use ANTLR\v4\Runtime\Misc\MurmurHash;
use ANTLR\v4\Runtime\Recognizer;
use ANTLR\v4\Runtime\RuleContext;

class Predicate extends SemanticContext
{
    /** @var int */
    public $ruleIndex;

    /** @var int */
    public $prefIndex;

    /** @var bool */
    public $isCtxDependent;

    /**
     * @param int $ruleIndex
     * @param int $prefIndex
     * @param bool $isCtxDependent
     */
    public function __construct(int $ruleIndex = -1, int $prefIndex = -1, bool $isCtxDependent = false)
    {
        $this->ruleIndex = $ruleIndex;
        $this->prefIndex = $prefIndex;
        $this->isCtxDependent = $isCtxDependent;
    }

    /**
     * {@inheritdoc}
     */
    public function evaluate(Recognizer $parser, RuleContext $parserCallStack): bool
    {
        return $parser->sempred($this->isCtxDependent ? $parserCallStack : null, $this->ruleIndex, $this->prefIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        $hash = MurmurHash::initialize();
        $hash = MurmurHash::update($hash, $this->ruleIndex);
        $hash = MurmurHash::update($hash, $this->prefIndex);
        $hash = MurmurHash::update($hash, (int) $this->isCtxDependent);
        $hash = MurmurHash::finish($hash, 3);

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

        if ($o instanceof self) {
            return $this->ruleIndex === $o->ruleIndex
                && $this->prefIndex === $o->prefIndex
                && $this->isCtxDependent === $o->isCtxDependent;
        }

        return false;
    }

    public function __toString(): string
    {
        return "{{$this->ruleIndex}:{$this->prefIndex}}?";
    }
}
