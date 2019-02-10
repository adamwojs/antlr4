<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Exception\IllegalStateException;

class ATNDeserializationOptions extends BaseObject
{
    /** @var bool */
    private $readOnly;

    /** @var bool */
    private $verifyATN;

    /** @var bool */
    private $generateRuleBypassTransitions;

    public function __construct()
    {
        $this->verifyATN = true;
        $this->generateRuleBypassTransitions = false;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function makeReadOnly(): void
    {
        $this->readOnly = true;
    }

    public function isVerifyATN(): bool
    {
        return $this->verifyATN;
    }

    public function setVerifyATN(bool $verifyATN): void
    {
        $this->throwIfReadOnly();
        $this->verifyATN = $verifyATN;
    }

    public function isGenerateRuleBypassTransitions(): bool
    {
        return $this->generateRuleBypassTransitions;
    }

    public function setGenerateRuleBypassTransitions(bool $generateRuleBypassTransitions): void
    {
        $this->throwIfReadOnly();
        $this->generateRuleBypassTransitions = $generateRuleBypassTransitions;
    }

    private function throwIfReadOnly(): void
    {
        if ($this->isReadOnly()) {
            throw new IllegalStateException("The object is read only.");
        }
    }

    public static function getDefaultOptions(): ATNDeserializationOptions
    {
        static $defaultOptions = null;

        if ($defaultOptions === null) {
            $defaultOptions = new ATNDeserializationOptions();
            $defaultOptions->makeReadOnly();
        }

        return $defaultOptions;
    }

    public static function copy(ATNDeserializationOptions $options): ATNDeserializationOptions
    {
        $copy = new ATNDeserializationOptions();
        $copy->setVerifyATN($options->isVerifyATN());
        $copy->setGenerateRuleBypassTransitions($options->isGenerateRuleBypassTransitions());

        return $copy;
    }
}
