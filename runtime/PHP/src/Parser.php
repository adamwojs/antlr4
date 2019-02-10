<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\ATN\ATN;
use ANTLR\v4\Runtime\Exception\RecognitionException;
use ANTLR\v4\Runtime\Misc\IntervalSet;
use ANTLR\v4\Runtime\Parser\TraceListener;
use ANTLR\v4\Runtime\Tree\ErrorNode;
use ANTLR\v4\Runtime\Tree\ErrorNodeImpl;
use ANTLR\v4\Runtime\Tree\ParseTreeListener;
use ANTLR\v4\Runtime\Tree\TerminalNode;
use ANTLR\v4\Runtime\Tree\TerminalNodeImpl;

/**
 * This is all the parsi ng support code essentially; most of it is error recovery stuff.
 */
abstract class Parser extends Recognizer
{
    /**
     * The error handling strategy for the parser. The default value is a new
     * instance of {@link DefaultErrorStrategy}.
     *
     * @see #getErrorHandler
     * @see #setErrorHandler
     *
     * @var \ANTLR\v4\Runtime\ANTLRErrorStrategy
     */
    protected $_errHandler;

    /**
     * The input stream.
     *
     * @see #getInputStream
     * @see #setInputStream
     *
     * @var \ANTLR\v4\Runtime\TokenStream
     */
    protected $_input;

    /** @var int[] */
    protected $_precedenceStack = [ 0 ];

    /**
     * The {@link ParserRuleContext} object for the currently executing rule.
     * This is always non-null during the parsing process.
     *
     * @var \ANTLR\v4\Runtime\ParserRuleContext
     */
    protected $_ctx;

    /**
     * Specifies whether or not the parser should construct a parse tree during
     * he parsing process. The default value is {@code true}.
     *
     * @see #getBuildParseTree
     * @see #setBuildParseTree
     *
     * @var bool
     */
    protected $_buildParseTrees = true;

    /**
     * When {@link #setTrace}{@code (true)} is called, a reference to the
     * {@link TraceListener} is stored here so it can be easily removed in a
     * later call to {@link #setTrace}{@code (false)}. The listener itself is
     * implemented as a parser listener so this field is not directly used by
     * other parser methods.
     *
     * @var \ANTLR\v4\Runtime\Parser\TraceListener
     */
    protected $_tracer;

    /**
     * The list of {@link ParseTreeListener} listeners registered to receive
     * events during the parse.
     *
     * @see #addParseListener
     *
     * @var \ANTLR\v4\Runtime\Tree\ParseTreeListener[]
     */
    protected $_parseListeners = [];

    /**
     * The number of syntax errors reported during parsing. This value is
     * incremented each time {@link #notifyErrorListeners} is called.
     *
     * @var int
     */
    protected $_syntaxErrors;

    /**
     * Indicates parser has match()ed EOF token. See {@link #exitRule()}.
     *
     * @var bool
     */
    protected $matchedEOF;

    public function __construct(TokenStream $input)
    {
        parent::__construct();

        $this->_errHandler = new DefaultErrorStrategy();
        $this->setInputStream($input);
    }

    // ...

    /**
     * Reset the parser's state
     */
    public function reset(): void
    {
        if (($input = $this->_input) !== null) {
            $input->seek(0);
        }

        $this->_errHandler->reset($this);
        $this->_ctx = null;
        $this->_syntaxErrors = 0;
        $this->matchedEOF = false;
        $this->setTrace(false);
        $this->_precedenceStack = [0];

        if (($interpreter = $this->getInterpreter()) !== null) {
            $interpreter->reset();
        }
    }

    /**
     * Match current input symbol against {@code ttype}. If the symbol type
     * matches, {@link ANTLRErrorStrategy#reportMatch} and {@link #consume} are
     * called to complete the match process.
     *
     * <p>If the symbol type does not match,
     * {@link ANTLRErrorStrategy#recoverInline} is called on the current error
     * strategy to attempt recovery. If {@link #getBuildParseTree} is
     * {@code true} and the token index of the symbol returned by
     * {@link ANTLRErrorStrategy#recoverInline} is -1, the symbol is added to
     * the parse tree by calling {@link #createErrorNode(ParserRuleContext, Token)} then
     * {@link ParserRuleContext#addErrorNode(ErrorNode)}.</p>
     *
     * @param int $ttype the token type to match
     *
     * @return \ANTLR\v4\Runtime\Token the matched symbol
     *
     * @throws \ANTLR\v4\Runtime\Exception\RecognitionException if the current input symbol did not match
     * {@code ttype} and the error strategy could not recover from the
     * mismatched symbol
     */
    public function match(int $ttype): Token
    {
        $t = $this->getCurrentToken();

        if ($t->getType() === $ttype) {
            if ($ttype === Token::EOF) {
                $this->matchedEOF = true;
            }

            $this->_errHandler->reportMatch($this);
            $this->consume();
        }
        else {
            $t = $this->_errHandler->recoverInline($this);
            if ($this->_buildParseTrees && $t->getTokenIndex() === -1) {
                // we must have conjured up a new token during single token insertion
                // if it's not the current symbol
                $this->_ctx->addErrorNode($this->createErrorNode($this->_ctx, $t));
            }
        }

        return $t;
    }

    /**
     * Match current input symbol as a wildcard. If the symbol type matches
     * (i.e. has a value greater than 0), {@link ANTLRErrorStrategy#reportMatch}
     * and {@link #consume} are called to complete the match process.
     *
     * <p>If the symbol type does not match,
     * {@link ANTLRErrorStrategy#recoverInline} is called on the current error
     * strategy to attempt recovery. If {@link #getBuildParseTree} is
     * {@code true} and the token index of the symbol returned by
     * {@link ANTLRErrorStrategy#recoverInline} is -1, the symbol is added to
     * the parse tree by calling {@link Parser#createErrorNode(ParserRuleContext, Token)}. then
     * {@link ParserRuleContext#addErrorNode(ErrorNode)}</p>
     *
     * @return \ANTLR\v4\Runtime\Token the matched symbol
     *
     * @throws \ANTLR\v4\Runtime\Exception\RecognitionException if the current input symbol did not match
     * a wildcard and the error strategy could not recover from the mismatched
     * symbol
     */
    public function matchWildcard(): Token
    {
        $t = $this->getCurrentToken();
        if ($t->getType() > 0) {
            $this->_errHandler->reportMatch($this);
            $this->consume();
        }
        else {
            $t = $this->_errHandler->recoverInline($this);
            if ($this->_buildParseTrees && $t->getTokenIndex() === -1) {
                // we must have conjured up a new token during single token insertion
                // if it's not the current symbol
                $this->_ctx->addErrorNode($this->createErrorNode($this->_ctx, $t));
            }
        }

        return $t;
    }

    /**
     * Gets whether or not a complete parse tree will be constructed while
     * parsing. This property is {@code true} for a newly constructed parser.
     *
     * @return bool {@code true} if a complete parse tree will be constructed while
     * parsing, otherwise {@code false}
     */
    public function getBuildParseTree(): bool
    {
        return $this->_buildParseTrees;
    }

    /**
     * Track the {@link ParserRuleContext} objects during the parse and hook
     * them up using the {@link ParserRuleContext#children} list so that it
     * forms a parse tree. The {@link ParserRuleContext} returned from the start
     * rule represents the root of the parse tree.
     *
     * <p>Note that if we are not building parse trees, rule contexts only point
     * upwards. When a rule exits, it returns the context but that gets garbage
     * collected if nobody holds a reference. It points upwards but nobody
     * points at it.</p>
     *
     * <p>When we build parse trees, we are adding all of these contexts to
     * {@link ParserRuleContext#children} list. Contexts are then not candidates
     * for garbage collection.</p>
     *
     * @param bool $buildParseTrees
     */
    public function setBuildParseTree(bool $buildParseTrees): void
    {
        $this->_buildParseTrees = $buildParseTrees;
    }

    /**
     * @return bool {@code true} if the {@link ParserRuleContext#children} list is trimmed
     * using the default {@link Parser.TrimToSizeListener} during the parse process.
     */
    public function getTrimParseTree(): bool
    {
        return false;
    }

    /**
     * Trim the internal lists of the parse tree during parsing to conserve memory.
     * This property is set to {@code false} by default for a newly constructed parser.
     *
     * @param bool $trimParseTrees {@code true} to trim the capacity of the {@link ParserRuleContext#children}
     * list to its size after a rule is parsed.
     */
    public function setTrimParseTree(bool $trimParseTrees): void
    {
        return ;
    }

    public function getParseListeners(): array
    {
        return $this->_parseListeners;
    }

    /**
     * Registers {@code listener} to receive events during the parsing process.
     *
     * <p>To support output-preserving grammar transformations (including but not
     * limited to left-recursion removal, automated left-factoring, and
     * optimized code generation), calls to listener methods during the parse
     * may differ substantially from calls made by
     * {@link ParseTreeWalker#DEFAULT} used after the parse is complete. In
     * particular, rule entry and exit events may occur in a different order
     * during the parse than after the parser. In addition, calls to certain
     * rule entry methods may be omitted.</p>
     *
     * <p>With the following specific exceptions, calls to listener events are
     * <em>deterministic</em>, i.e. for identical input the calls to listener
     * methods will be the same.</p>
     *
     * <ul>
     * <li>Alterations to the grammar used to generate code may change the
     * behavior of the listener calls.</li>
     * <li>Alterations to the command line options passed to ANTLR 4 when
     * generating the parser may change the behavior of the listener calls.</li>
     * <li>Changing the version of the ANTLR Tool used to generate the parser
     * may change the behavior of the listener calls.</li>
     * </ul>
     *
     * @param \ANTLR\v4\Runtime\Tree\ParseTreeListener listener the listener to add
     */
    public function addParseListener(ParseTreeListener $listener): void
    {
        $this->_parseListeners[] = $listener;
    }

    /**
     * Remove {@code listener} from the list of parse listeners.
     *
     * <p>If {@code listener} is {@code null} or has not been added as a parse
     * listener, this method does nothing.</p>
     *
     * @see #addParseListener
     *
     * @param \ANTLR\v4\Runtime\Tree\ParseTreeListener|null $listener the listener to remove
     */
    public function removeParseListener(?ParseTreeListener $listener): void
    {
        $idx = array_search($listener, $this->_parseListeners);
        if ($idx !== false) {
            unset($this->_parseListeners[$idx]);
        }
    }

    /**
     * Remove all parse listeners.
     *
     * @see #addParseListener
     */
    public function removeParseListeners(): void
    {
        $this->_parseListeners = [];
    }

    /**
     * Notify any parse listeners of an enter rule event.
     *
     * @see #addParseListener
     */
    protected function triggerEnterRuleEvent(): void
    {
        foreach ($this->_parseListeners as $listener) {
            $listener->enterEveryRule($this->_ctx);
            $this->_ctx->enterRule($listener);
        }
    }

    /**
     * Notify any parse listeners of an exit rule event.
     *
     * @see #addParseListener
     */
    protected function triggerExitRuleEvent(): void
    {
        for ($i = count($this->_parseListeners) - 1; $i >= 0; $i--) {
            $listener = $this->_parseListeners[$i];
            $this->_ctx->exitRule($listener);
            $listener->exitEveryRule($this->_ctx);
        }
    }

    /**
     * Gets the number of syntax errors reported during parsing. This value is
     * incremented each time {@link #notifyErrorListeners} is called.
     *
     * @see #notifyErrorListeners
     *
     * @return int
     */
    public function getNumberOfSyntaxErrors(): int
    {
        return $this->_syntaxErrors;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenFactory(): TokenFactory
    {
        return $this->_input->getTokenSource()->getTokenFactory();
    }

    /**
     * {@inheritdoc}
     */
    public function setTokenFactory(TokenFactory $factory): void
    {
        $this->_input->getTokenSource()->setTokenFactory($factory);
    }

    public function getErrorHandler(): ANTLRErrorStrategy
    {
        return $this->_errHandler;
    }

    public function setErrorHandler(ANTLRErrorStrategy $handler): void
    {
        $this->_errHandler = $handler;
    }

    /**
     * {@inheritdoc}
     */
    public function getInputStream(): IntStream
    {
        return $this->getTokenStream();
    }

    /**
     * {@inheritdoc}
     */
    public function setInputStream(IntStream $input): void
    {
        $this->setTokenStream($input);
    }

    public function getTokenStream(): TokenStream
    {
        return $this->_input;
    }

    /**
     * Set the token stream and reset the parser.
     *
     * @param \ANTLR\v4\Runtime\TokenStream $input
     */
    public function setTokenStream(TokenStream $input): void
    {
        $this->_input = null;
        $this->reset();
        $this->_input = $input;
    }

    /**
     * Match needs to return the current input symbol, which gets put
     * into the label for the associated token ref; e.g., x=ID.
     *
     * @return \ANTLR\v4\Runtime\Token
     */
    public function getCurrentToken(): Token
    {
        return $this->_input->LT(1);
    }

    public function notifyErrorListeners(string $msg, ?Token $offendingToken = null, ?RecognitionException $e = null): void
    {
        if ($offendingToken === null) {
            $offendingToken = $this->getCurrentToken();
        }

        $this->_syntaxErrors++;
        $this->getErrorListenerDispatch()->syntaxError(
            $this,
            $offendingToken,
            $offendingToken->getLine(),
            $offendingToken->getCharPositionInLine(),
            $msg,
            $e
        );
    }

    /**
     * Consume and return the {@linkplain #getCurrentToken current symbol}.
     *
     * <p>E.g., given the following input with {@code A} being the current
     * lookahead symbol, this function moves the cursor to {@code B} and returns
     * {@code A}.</p>
     *
     * <pre>
     *  A B
     *  ^
     * </pre>
     *
     * If the parser is not in error recovery mode, the consumed symbol is added
     * to the parse tree using {@link ParserRuleContext#addChild(TerminalNode)}, and
     * {@link ParseTreeListener#visitTerminal} is called on any parse listeners.
     * If the parser <em>is</em> in error recovery mode, the consumed symbol is
     * added to the parse tree using {@link #createErrorNode(ParserRuleContext, Token)} then
     * {@link ParserRuleContext#addErrorNode(ErrorNode)} and
     * {@link ParseTreeListener#visitErrorNode} is called on any parse
     * listeners.
     *
     * @return \ANTLR\v4\Runtime\Token
     */
    public function consume(): Token
    {
        $t = $this->getCurrentToken();
        if ($t->getType() !== self::EOF) {
            $this->getInputStream()->consume();
        }

        if ($this->_buildParseTrees || !empty($this->_parseListeners)) {
            if ($this->_errHandler->inErrorRecoveryMode($this)) {
                $node = $this->_ctx->addErrorNode($this->createErrorNode($this->_ctx, $t));
                foreach ($this->_parseListeners as $listener) {
                    $listener->visitErrorNode($node);
                }
            }
            else {
                $node = $this->_ctx->addTerminalNodeChild($this->createTerminalNode($this->_ctx, $t));
                foreach ($this->_parseListeners as $listener) {
                    $listener->visitTerminal($node);
                }
            }
        }

        return $t;
    }

    /**
     * How to create a token leaf node associated with a parent.
     * Typically, the terminal node to create is not a function of the parent.
     *
     * @param \ANTLR\v4\Runtime\ParserRuleContext $parent
     * @param \ANTLR\v4\Runtime\Token $t
     *
     * @return \ANTLR\v4\Runtime\Tree\TerminalNode
     */
    public function createTerminalNode(ParserRuleContext $parent, Token $t): TerminalNode
    {
        return new TerminalNodeImpl($t);
    }

    /**
     * How to create an error node, given a token, associated with a parent.
     * Typically, the error node to create is not a function of the parent.
     *
     * @param \ANTLR\v4\Runtime\ParserRuleContext $parent
     * @param \ANTLR\v4\Runtime\Token $t
     *
     * @return \ANTLR\v4\Runtime\Tree\ErrorNode
     */
    public function createErrorNode(ParserRuleContext $parent, Token $t): ErrorNode
    {
        return new ErrorNodeImpl($t);
    }

    protected function addContextToParseTree(): void
    {
        // Add current context to parent if we have a parent
        if (($parent = $this->_ctx->parent) !== null) {
            /** @var \ANTLR\v4\Runtime\ParserRuleContext $parent */
            $parent->addRuleContextChild($this->_ctx);
        }
    }

    /**
     * Always called by generated parsers upon entry to a rule. Access field
     * {@link #_ctx} get the current context.
     *
     * @param \ANTLR\v4\Runtime\ParserRuleContext $localctx
     * @param int $state
     * @param int $ruleIndex
     */
    public function enterRule(ParserRuleContext $localctx, int $state, int $ruleIndex): void
    {
        $this->setState($state);
        $this->_ctx = $localctx;
        $this->_ctx->start = $this->_input->LT(1);

        if ($this->_buildParseTrees) {
            $this->addContextToParseTree();
        }

        $this->triggerEnterRuleEvent();
    }

    public function exitRule(): void
    {
        if ($this->matchedEOF) {
            // if we have matched EOF, it cannot consume past EOF so we use LT(1) here
            $this->_ctx->stop = $this->_input->LT(1); // LT(1) will be end of file
        } else {
            $this->_ctx->stop = $this->_input->LT(-1); // stop node is what we just matched
        }

        // trigger event on _ctx, before it reverts to parent
        $this->triggerExitRuleEvent();

        $this->setState($this->_ctx->invokingState);
        $this->_ctx = $this->_ctx->parent;
    }

    public function enterOuterAlt(ParserRuleContext $localctx, int $altNum): void
    {
        $localctx->setAltNumber($altNum);
        // if we have new localctx, make sure we replace existing ctx
        // that is previous child of parse tree
        if ($this->_buildParseTrees && $this->_ctx !== $localctx) {
            if (($parent = $this->_ctx->parent) !== null) {
                /** @var \ANTLR\v4\Runtime\ParserRuleContext $parent */
                $parent->removeLastChild();
                $parent->addAnyChild($localctx);
            }
        }

        $this->_ctx = $localctx;
    }

    /**
     * Get the precedence level for the top-most precedence rule.
     *
     * @return int The precedence level for the top-most precedence rule, or -1 if
     * the parser context is not nested within a precedence rule.
     */
    public final function getPrecedence(): int
    {
        if (empty($this->_precedenceStack)) {
            return -1;
        }

        return $this->_precedenceStack[count($this->_precedenceStack) - 1];
    }

    public function enterRecursionRule(ParserRuleContext $localctx, int $state, int $ruleIndex, int $precedence): void
    {
        $this->setState($state);
        $this->_precedenceStack[] = $precedence;
        $this->_ctx = $localctx;
        $this->_ctx->start = $this->_input->LT(1);

        $this->triggerEnterRuleEvent(); // Simulates rule entry for left-recursive rules
    }

    /**
     * Like {@link #enterRule} but for recursive rules.
     * Make the current context the child of the incoming localctx.
     *
     * @param \ANTLR\v4\Runtime\ParserRuleContext $localctx
     * @param int $state
     * @param int $ruleIndex
     */
    public function pushNewRecursionContext(ParserRuleContext $localctx, int $state, int $ruleIndex): void
    {
        $previous = $this->_ctx;
        $previous->parent = $localctx;
        $previous->invokingState = $state;
        $previous->stop = $this->_input->LT(-1);

        $this->_ctx = $localctx;
        $this->_ctx->start = $previous->start;
        if ($this->_buildParseTrees) {
            $this->_ctx->addRuleContextChild($previous);
        }

        $this->triggerEnterRuleEvent(); // Simulates rule entry for left-recursive rules
    }

    public function unrollRecursionContexts(?ParserRuleContext $_parentctx): void
    {
        array_pop($this->_precedenceStack);

        $this->_ctx->stop = $this->_input->LT(-1);

        // save current ctx (return value)
        $retctx = $this->_ctx;

        // unroll so _ctx is as it was before call to recursive method
        if (!empty($this->_parseListeners)) {
            while ($this->_ctx !== $_parentctx) {
                $this->triggerExitRuleEvent();
                $this->_ctx = $this->_ctx->parent;
            }
        } else {
            $this->_ctx = $_parentctx;
        }

        // hook into tree
        $retctx->parent = $_parentctx;

        if ($this->_buildParseTrees && $_parentctx !== null) {
            // add return ctx into invoking rule's tree
            $_parentctx->addRuleContextChild($retctx);
        }
    }

    public function getInvokingContext(int $ruleIndex): ParserRuleContext
    {
        $p = $this->_ctx;
        while ($p !== null) {
            if ($p->getRuleIndex() === $ruleIndex) {
                return $p;
            }

            $p = $p->parent;
        }

        return null;
    }

    public function getContext(): ParserRuleContext
    {
        return $this->_ctx;
    }

    public function setContext(ParserRuleContext $ctx): void
    {
        $this->_ctx = $ctx;
    }

    /**
     * {@inheritdoc}
     */
    public function precpred(RuleContext $parserCallStack, int $precedence): bool
    {
        return $precedence >= $this->_precedenceStack[count($this->_precedenceStack) - 1];
    }

    public function inContext(String $context): bool
    {
        return false;
    }

    /**
     * Checks whether or not {@code symbol} can follow the current state in the
     * ATN. The behavior of this method is equivalent to the following, but is
     * implemented such that the complete context-sensitive follow set does not
     * need to be explicitly constructed.
     *
     * <pre>
     * return getExpectedTokens().contains(symbol);
     * </pre>
     *
     * @param int $symbol the symbol type to check
     *
     * @return bool {@code true} if {@code symbol} can follow the current state in
     * the ATN, otherwise {@code false}.
     */
    public function isExpectedToken(int $symbol): bool
    {
        // TODO: Missing \ANTLR\v4\Runtime\Parser::isExpectedToken implementation
        return false;
    }

    public function isMatchedEOF(): bool
    {
        return $this->matchedEOF;
    }

    /**
     * Computes the set of input symbols which could follow the current parser
     * state and context, as given by {@link #getState} and {@link #getContext},
     * respectively.
     *
     * @see ATN#getExpectedTokens(int, RuleContext)
     *
     * @return \ANTLR\v4\Runtime\Misc\IntervalSet
     */
    public function getExpectedTokens(): IntervalSet
    {
        return $this->getATN()->getExpectedTokens($this->getState(), $this->getContext());
    }

    public function getExpectedTokensWithinCurrentRule(): IntervalSet
    {
        $atn = $this->getInterpreter()->atn;

        return $atn->nextTokensForATNState(
            $atn->states[$this->getState()]
        );
    }

    /**
     * Get a rule's index (i.e., {@code RULE_ruleName} field) or -1 if not found.
     *
     * @param string $ruleName
     *
     * @return int
     */
    public function getRuleIndex(string $ruleName): int
    {
        $map = $this->getRuleIndexMap();
        if (!isset($map[$ruleName])) {
            return -1;
        }

        return $map[$ruleName];
    }

    // TODO: Seems to be duplicate of \ANTLR\v4\Runtime\Parser::getContext
    public function getRuleContext(): ParserRuleContext
    {
        return $this->_ctx;
    }

    /** Return List&lt;String&gt; of the rule names in your parser instance
     *  leading up to a call to the current rule.  You could override if
     *  you want more details such as the file/line info of where
     *  in the ATN a rule is invoked.
     *
     *  This is very useful for error messages.
     *
     * @param \ANTLR\v4\Runtime\RuleContext|null $p
     *
     * @return \ANTLR\v4\Runtime\RuleInvocationStack
     */
    public function getRuleInvocationStack(RuleContext $p = null): RuleInvocationStack
    {
        if ($p === null) {
            $p = $this->_ctx;
        }

        $ruleNames = $this->getRuleNames();

        $stack = new RuleInvocationStack();
        while ($p !== null) {
            // compute what follows who invoked us
            $idx = $p->getRuleIndex();

            if ($idx < 0) {
                $stack[] = "n/a";
            } else {
                $stack[] = $ruleNames[$idx];
            }

            $p = $p->parent;
        }

        return $stack;
    }

    public function getSourceName(): string
    {
        return $this->_input->getSourceName();
    }

    /**
     * During a parse is sometimes useful to listen in on the rule entry and exit
     * events as well as token matches. This is for quick and dirty debugging.
     *
     * @param bool $trace
     */
    public function setTrace(bool $trace): void
    {
        if (!$trace) {
            $this->removeParseListener($this->_tracer);
            $this->_tracer = null;
        } else {
            if ($this->_tracer !== null) {
                $this->removeParseListener($this->_tracer);
            } else {
                $this->_tracer = new TraceListener($this);
            }

            $this->addParseListener($this->_tracer);
        }
    }

    /**
     * Gets whether a {@link TraceListener} is registered as a parse listener
     * for the parser.
     *
     * @see #setTrace(boolean)
     */
    public function isTrace(): bool
    {
        return $this->_tracer !== null;
    }
}
