<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\ATN\ATN;
use ANTLR\v4\Runtime\ATN\ATNSimulator;
use ANTLR\v4\Runtime\Exception\RecognitionException;
use ANTLR\v4\Runtime\Exception\UnsupportedOperationException;

abstract class Recognizer extends BaseObject
{
    public const EOF = -1;

    /** @var \ANTLR\v4\Runtime\ATN\ATNSimulator */
    protected $_interp;

    /** @var array */
    private $_listeners = [];

    /** @var int */
    private $_stateNumber = -1;

    public function __construct()
    {
        $this->_listeners[] = new ConsoleErrorListener();
    }

    abstract public function getTokenNames(): array;

    abstract public function getRuleNames(): array;

    /**
     * Get the vocabulary used by the recognizer.
     *
     * @return \ANTLR\v4\Runtime\VocabularyInterface a {@link Vocabulary} instance providing information about the
     * vocabulary used by the grammar
     */
    public function getVocabulary(): VocabularyInterface
    {
        return Vocabulary::fromTokenNames($this->getTokenNames());
    }

    /**
     * Get a map from token names to token types.
     *
     * <p>Used for XPath and tree pattern compilation.</p>
     *
     * @return array
     */
    public function getTokenTypeMap(): array
    {
        // TODO: Missing \ANTLR\v4\Runtime\Recognizer::getTokenTypeMap implementation
        return [];
    }

    /**
     * Get a map from rule names to rule indexes.
     *
     * <p>Used for XPath and tree pattern compilation.</p>
     *
     * @return array
     */
    public function getRuleIndexMap(): array
    {
        // TODO: Missing \ANTLR\v4\Runtime\Recognizer::getRuleIndexMap implementation
        return [];
    }

    public function getTokenType(string $tokenName): int
    {
        // TODO: Missing \ANTLR\v4\Runtime\Recognizer::getTokenType implementation
        return Token::INVALID_TYPE;
    }

    /**
     * If this recognizer was generated, it will have a serialized ATN
     * representation of the grammar.
     *
     * <p>For interpreters, we don't know their serialized ATN despite having
     * created the interpreter from it.</p>
     *
     * @return array|null
     */
    public function getSerializedATN(): ?array
    {
        throw new UnsupportedOperationException('There is no serialized ATN');
    }

    /**
     * For debugging and other purposes, might want the grammar name.
     * Have ANTLR generate an implementation for this method.
     *
     * @return string
     */
    abstract public function getGrammarFileName(): string;

    /**
     * Get the {@link ATN} used by the recognizer for prediction.
     *
     * @return ATN the {@link ATN} used by the recognizer for prediction
     */
    abstract public function getATN(): ATN;

    /**
     * Get the ATN interpreter used by the recognizer for prediction.
     *
     * @return \ANTLR\v4\Runtime\ATN\ATNSimulator|null the ATN interpreter used by the recognizer for prediction
     */
    public function getInterpreter(): ?ATNSimulator
    {
        return $this->_interp;
    }

    /**
     * Set the ATN interpreter used by the recognizer for prediction.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATNSimulator $interpreter the ATN interpreter used by the recognizer for
     * prediction
     */
    public function setInterpreter(ATNSimulator $interpreter): void
    {
        $this->_interp = $interpreter;
    }

    /**
     * If profiling during the parse/lex, this will return DecisionInfo records
     * for each decision in recognizer in a ParseInfo object.
     *
     * @return \ANTLR\v4\Runtime\ParseInfo
     */
    public function getParseInfo(): ParseInfo
    {
        return null;
    }

    /**
     * What is the error header, normally line/character position information?
     *
     * @param \ANTLR\v4\Runtime\Exception\RecognitionException $e
     *
     * @return string
     */
    public function getErrorHeader(RecognitionException $e): string
    {
        $token = $e->getOffendingToken();

        return "line {$token->getLine()}:{$token->getCharPositionInLine()}";
    }

    public function addErrorListener(ANTLRErrorListener $listener): void
    {
        $this->_listeners[] = $listener;
    }

    public function removeErrorListener(ANTLRErrorListener $listener): void
    {
        $idx = array_search($listener, $this->_listeners);
        if ($idx !== false) {
            unset($this->_listeners[$idx]);
        }
    }

    public function removeErrorListeners(): void
    {
        $this->_listeners = [];
    }

    public function getErrorListeners(): array
    {
        return $this->_listeners;
    }

    public function getErrorListenerDispatch(): ANTLRErrorListener
    {
        return new ProxyErrorListener($this->getErrorListeners());
    }

    // subclass needs to override these if there are sempreds or actions
    // that the ATN interp needs to execute
    public function sempred(RuleContext $param, int $ruleIndex, int $prefIndex): bool
    {
        return true;
    }

    public function precpred(RuleContext $parserCallStack, int $precedence): bool
    {
        return true;
    }

    public function action(RuleContext $_localctx, int $ruleIndex, int $actionIndex): void
    {
    }

    public function getState(): int
    {
        return $this->_stateNumber;
    }

    /**
     * Indicate that the recognizer has changed internal state that is
     * consistent with the ATN state passed in.  This way we always know
     * where we are in the ATN as the parser goes along. The rule
     * context objects form a stack that lets us see the stack of
     * invoking rules. Combine this and we have complete ATN
     * configuration information.
     *
     * @param int $atnState
     */
    public function setState(int $atnState): void
    {
        $this->_stateNumber = $atnState;
    }

    abstract public function getInputStream(): IntStream;

    abstract public function setInputStream(IntStream $input): void;

    abstract public function getTokenFactory(): TokenFactory;

    abstract public function setTokenFactory(TokenFactory $factory): void;
}
