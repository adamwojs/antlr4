<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Exception\EmptyStackException;
use ANTLR\v4\Runtime\Exception\IllegalStateException;
use ANTLR\v4\Runtime\Exception\LexerNoViableAltException;
use ANTLR\v4\Runtime\Exception\RecognitionException;
use ANTLR\v4\Runtime\Misc\Interval;
use ANTLR\v4\Runtime\Misc\Pair;

abstract class Lexer extends Recognizer implements TokenSource
{
    public const DEFAULT_MODE = 0;
    public const MORE = -2;
    public const SKIP = -3;

    public const DEFAULT_TOKEN_CHANNEL = Token::DEFAULT_CHANNEL;
    public const HIDDEN = Token::HIDDEN_CHANNEL;
    public const MIN_CHAR_VALUE = 0x0000;
    public const MAX_CHAR_VALUE = 0x10FFFF;

    /** @var \ANTLR\v4\Runtime\CharStream */
    public $_input;

    /**
     * The goal of all lexer rules/methods is to create a token object.
     * This is an instance variable as multiple rules may collaborate to
     * create a single token. nextToken will return this object after
     * matching lexer rule(s). If you subclass to allow multiple token
     * emissions, then set this to the last token to be matched or
     * something nonnull so that the auto token emit mechanism will not
     * emit another token.
     *
     * @var \ANTLR\v4\Runtime\Token
     */
    public $_token;

    /**
     * What character index in the stream did the current token start at?
     * Needed, for example, to get the text for current token.  Set at
     * the start of nextToken.
     *
     * @var int
     */
    public $_tokenStartCharIndex;

    /**
     * The line on which the first character of the token resides.
     *
     * @var int
     */
    public $_tokenStartLine;

    /**
     * The character position of first character within the line.
     *
     * @var int
     */
    public $_tokenStartCharPositionInLine;

    /**
     * Once we see EOF on char stream, next token will be EOF.
     * If you have DONE : EOF ; then you see DONE EOF.
     *
     * @var bool
     */
    public $_hitEOF;

    /**
     * The channel number for the current token.
     *
     * @var int
     */
    public $_channel;

    /**
     * The token type for the current token.
     *
     * @var int
     */
    public $_type;

    /** @var array */
    public $_modeStack = [];

    /** @var int */
    public $_mode = self::DEFAULT_MODE;

    /**
     * You can set the text for the current token to override what is in
     * the input char buffer.  Use setText() or can set this instance var.
     *
     * @var string|null
     */
    public $_text;

    /** @var \ANTLR\v4\Runtime\Misc\Pair */
    protected $_tokenFactorySourcePair;

    /**
     * How to create token objects ?
     *
     * @var \ANTLR\v4\Runtime\TokenFactory
     */
    protected $_factory;

    public function __construct(CharStream $input = null)
    {
        parent::__construct();

        $this->_factory = CommonTokenFactory::getDefault();
        if ($input !== null) {
            $this->_input = $input;
            $this->_tokenFactorySourcePair = new Pair($this, $input);
        }
    }

    public function reset(): void
    {
        // wack Lexer state variables
        if ($this->_input !== null) {
            // rewind the input
            $this->_input->seek(0);
        }

        $this->_token = null;
        $this->_type = Token::INVALID_TYPE;
        $this->_channel = Token::DEFAULT_CHANNEL;
        $this->_tokenStartCharIndex = -1;
        $this->_tokenStartCharPositionInLine = -1;
        $this->_tokenStartLine = -1;
        $this->_text = null;

        $this->_hitEOF = false;
        $this->_mode = self::DEFAULT_MODE;
        $this->_modeStack = [];

        $this->getInterpreter()->reset();
    }

    /**
     * {@inheritdoc}
     */
    public function nextToken(): Token
    {
        if ($this->_input === null) {
            throw new IllegalStateException('nextToken requires a non-null input stream.');
        }

        // Mark start location in char stream so unbuffered streams are
        // guaranteed at least have text of current token
        $tokenStartMarker = $this->_input->mark();

        try {
            while (true) {
                if ($this->_hitEOF) {
                    $this->emitEOF();

                    return $this->_token;
                }

                $this->_token = null;
                $this->_channel = Token::DEFAULT_CHANNEL;
                $this->_tokenStartCharIndex = $this->_input->index();
                $this->_tokenStartCharPositionInLine = $this->getInterpreter()->getCharPositionInLine();
                $this->_tokenStartLine = $this->getInterpreter()->getLine();
                $this->_text = null;

                do {
                    $this->_type = Token::INVALID_TYPE;

                    try {
                        $ttype = $this->getInterpreter()->match($this->_input, $this->_mode);
                    } catch (LexerNoViableAltException $e) {
                        $this->notifyListeners($e);
                        $this->recoverLexerNoViableAltException($e);
                        $ttype = self::SKIP;
                    }

                    if ($this->_input->LA(1) === IntStream::EOF) {
                        $this->_hitEOF = true;
                    }

                    if ($this->_type === Token::INVALID_TYPE) {
                        $this->_type = $ttype;
                    }

                    if ($this->_type === self::SKIP) {
                        continue 2;
                    }
                } while ($this->_type === self::MORE);

                if ($this->_token === null) {
                    $this->emit();
                }

                return $this->_token;
            }
        } finally {
            // make sure we release marker after match or
            // unbuffered char stream will keep buffering
            $this->_input->release($tokenStartMarker);
        }
    }

    /**
     * Instruct the lexer to skip creating a token for current lexer rule
     * and look for another token.  nextToken() knows to keep looking when
     * a lexer rule finishes with token set to SKIP_TOKEN.  Recall that
     * if token==null at end of any token rule, it creates one for you
     * and emits it.
     */
    public function skip(): void
    {
        $this->_type = self::SKIP;
    }

    public function more(): void
    {
        $this->_type = self::MORE;
    }

    public function mode(int $m): void
    {
        $this->_mode = $m;
    }

    public function pushMode(int $mode): void
    {
        array_push($this->_modeStack, $this->_mode);
        $this->mode($mode);
    }

    public function popMode(): int
    {
        if (empty($this->_modeStack)) {
            throw new EmptyStackException();
        }

        $this->mode(array_pop($this->_modeStack));

        return $this->_mode;
    }

    /**
     * {@inheritdoc}
     */
    public function setTokenFactory(TokenFactory $factory): void
    {
        $this->_factory = $factory;
    }

    /**
     * {@inheritdoc}
     */
    public function getTokenFactory(): TokenFactory
    {
        return $this->_factory;
    }

    /**
     * {@inheritdoc}
     */
    public function setInputStream(IntStream $input): void
    {
        $this->_input = null;
        $this->_tokenFactorySourcePair = new Pair($this, $this->_input);
        $this->reset();
        $this->_input = $input;
        $this->_tokenFactorySourcePair = new Pair($this, $this->_input);
    }

    /**
     * {@inheritdoc}
     */
    public function getSourceName(): string
    {
        return $this->_input->getSourceName();
    }

    /**
     * {@inheritdoc}
     */
    public function getInputStream(): IntStream
    {
        return $this->_input;
    }

    /**
     * By default does not support multiple emits per nextToken invocation
     * for efficiency reasons.  Subclass and override this method, nextToken,
     * and getToken (to push tokens into a list and pull from that list
     * rather than a single variable as this implementation does).
     *
     * @param \ANTLR\v4\Runtime\Token $token |null
     *
     * @return \ANTLR\v4\Runtime\Token
     */
    public function emit(Token $token = null): Token
    {
        if ($token === null) {
            $token = $this->_factory->create(
                $this->_tokenFactorySourcePair,
                $this->_type,
                $this->_text,
                $this->_channel,
                $this->_tokenStartCharIndex,
                $this->getCharIndex() - 1,
                $this->_tokenStartLine,
                $this->_tokenStartCharPositionInLine
            );
        }

        return $this->_token = $token;
    }

    public function emitEOF(): Token
    {
        $token = $this->_factory->create(
            $this->_tokenFactorySourcePair,
            Token::EOF,
            null,
            Token::DEFAULT_CHANNEL,
            $this->_input->index(),
            $this->_input->index() - 1,
            $this->getLine(),
            $this->getCharPositionInLine()
        );

        return $this->emit($token);
    }

    /**
     * {@inheritdoc}
     */
    public function getLine(): int
    {
        return $this->getInterpreter()->getLine();
    }

    public function setLine(int $line): void
    {
        $this->getInterpreter()->setLine($line);
    }

    /**
     * {@inheritdoc}
     */
    public function getCharPositionInLine(): int
    {
        return $this->getInterpreter()->getCharPositionInLine();
    }

    public function setCharPositionInLine(int $charPositionInLine): void
    {
        $this->getInterpreter()->setCharPositionInLine($charPositionInLine);
    }

    /**
     * What is the index of the current character of lookahead?
     *
     * @return int
     */
    public function getCharIndex(): int
    {
        return $this->_input->index();
    }

    /**
     * Return the text matched so far for the current token or any
     * text override.
     *
     * @return string
     */
    public function getText(): string
    {
        if ($this->_text != null) {
            return $this->_text;
        }

        return $this->getInterpreter()->getText($this->_input);
    }

    /**
     * Set the complete text of this token; it wipes any previous
     * changes to the text.
     *
     * @param string|null $text
     */
    public function setText(?string $text): void
    {
        $this->_text = $text;
    }

    /**
     * Override if emitting multiple tokens.
     *
     * @return \ANTLR\v4\Runtime\Token
     */
    public function getToken(): Token
    {
        return $this->_token;
    }

    public function setToken(Token $token): void
    {
        $this->_token = $token;
    }

    public function getType(): int
    {
        return $this->_type;
    }

    public function setType(int $type): void
    {
        $this->_type = $type;
    }

    public function getChannel(): int
    {
        return $this->_channel;
    }

    public function setChannel(int $channel): void
    {
        $this->_channel = $channel;
    }

    public function getChannelNames(): array
    {
        return [];
    }

    public function getModeNames(): array
    {
        return [];
    }

    /**
     * Return a list of all Token objects in input char stream.
     * Forces load of all tokens. Does not include EOF token.
     *
     * @return \ANTLR\v4\Runtime\Token[]
     */
    public function getAllTokens(): array
    {
        // TODO: Missing \ANTLR\v4\Runtime\Lexer::getAllTokens implementation
        return [];
    }

    public function recoverLexerNoViableAltException(LexerNoViableAltException $e): void
    {
        if ($this->_input->LA(1) !== IntStream::EOF) {
            // skip a char and try again
            $this->getInterpreter()->consume($this->_input);
        }
    }

    /**
     * Lexers can normally match any char in it's vocabulary after matching
     * a token, so do the easy thing and just kill a character and hope
     * it all works out.  You can instead use the rule invocation stack
     * to do sophisticated error recovery if you are in a fragment rule.
     *
     * @param \ANTLR\v4\Runtime\Exception\RecognitionException $e
     */
    public function recoverRecognitionException(RecognitionException $e): void
    {
        $this->_input->consume();
    }

    public function notifyListeners(LexerNoViableAltException $e): void
    {
        $text = $this->_input->getText(Interval::of($this->_tokenStartCharIndex, $this->_input->index()));
        $msg = "token recognition error at: '{$this->getErrorDisplay($text)}'";

        $this->getErrorListenerDispatch()->syntaxError(
            $this, null, $this->_tokenStartLine, $this->_tokenStartCharPositionInLine, $msg, $e
        );
    }

    public function getErrorDisplay(string $text): string
    {
        return strtr($text, [
            Token::EOF => '<EOF>',
            "\n" => '\\n',
            "\t" => '\\t',
            "\r" => '\\r',
        ]);
    }
}
