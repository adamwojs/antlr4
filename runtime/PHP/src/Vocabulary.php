<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use IntlChar;

/**
 * This class provides a default implementation of the {@link Vocabulary}
 * interface.
 */
class Vocabulary extends BaseObject implements VocabularyInterface
{
    /** @var string[] */
    private $literalNames;

    /** @var string[] */
    private $symbolicNames;

    /** @var string[] */
    private $displayNames;

    /** @var int */
    private $maxTokenType;

    /**
     * Constructs a new instance of {@link VocabularyImpl} from the specified
     * literal, symbolic, and display token names.
     *
     * @param string[] $literalNames The literal names assigned to tokens, or {@code null}
     * if no literal names are assigned.
     * @param string[] $symbolicNames The symbolic names assigned to tokens, or
     * {@code null} if no symbolic names are assigned.
     * @param string[] $displayNames The display names assigned to tokens, or {@code null}
     * to use the values in {@code literalNames} and {@code symbolicNames} as
     * the source of display names, as described in
     * {@link #getDisplayName(int)}.
     *
     * @see #getLiteralName(int)
     * @see #getSymbolicName(int)
     * @see #getDisplayName(int)
     */
    public function __construct(array $literalNames = [], array $symbolicNames = [], array $displayNames = [])
    {
        $this->literalNames = $literalNames;
        $this->symbolicNames = $symbolicNames;
        $this->displayNames = $displayNames;

        // See note here on -1 part: https://github.com/antlr/antlr4/pull/1146
        $this->maxTokenType = max(count($literalNames), count($symbolicNames), count($displayNames)) - 1;
    }

    /**
     * {@inheritdoc}
     */
    public function getMaxTokenType(): int
    {
        return $this->maxTokenType;
    }

    /**
     * {@inheritdoc}
     */
    public function getLiteralName(int $tokenType): ?string
    {
        if ($tokenType >= 0 && $tokenType < count($this->literalNames)) {
            return $this->literalNames[$tokenType];
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getSymbolicName(int $tokenType): ?string
    {
        if ($tokenType >= 0 && $tokenType < count($this->symbolicNames)) {
            return $this->symbolicNames[$tokenType];
        }

        if ($tokenType === Token::EOF) {
            return "EOF";
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getDisplayName(int $tokenType): string
    {
        if ($tokenType > 0 && $tokenType < count($this->displayNames)) {
            if ($this->displayNames[$tokenType] !== null) {
                return $this->displayNames[$tokenType];
            }
        }

        if (($literalName = $this->getLiteralName($tokenType)) !== null) {
            return $literalName;
        }

        if (($symbolicName = $this->getSymbolicName($tokenType)) !== null) {
            return $symbolicName;
        }

        return IntlChar::chr($tokenType);
    }

    /**
     * Gets an empty {@link Vocabulary} instance.
     *
     * <p>
     * No literal or symbol names are assigned to token types, so
     * {@link #getDisplayName(int)} returns the numeric value for all tokens
     * except {@link Token#EOF}.</p>
     *
     * @return \ANTLR\v4\Runtime\VocabularyInterface
     */
    public static function getEmpty(): VocabularyInterface
    {
        static $instance = null;
        if ($instance === null) {
            $instance = new Vocabulary();
        }

        return $instance;
    }

    /**
     * Returns a {@link VocabularyImpl} instance from the specified set of token
     * names. This method acts as a compatibility layer for the single
     * {@code tokenNames} array generated by previous releases of ANTLR.
     *
     * <p>The resulting vocabulary instance returns {@code null} for
     * {@link #getLiteralName(int)} and {@link #getSymbolicName(int)}, and the
     * value from {@code tokenNames} for the display names.</p>
     *
     * @param string[] $tokenNames The token names, or {@code null} if no token names are
     * available.
     *
     * @return \ANTLR\v4\Runtime\VocabularyInterface A {@link Vocabulary} instance which uses {@code tokenNames} for
     * the display names of tokens.
     */
    public static function fromTokenNames(?array $tokenNames): VocabularyInterface
    {
        if ($tokenNames === null || empty($tokenNames)) {
            return self::getEmpty();
        }

        $literalNames = $symbolicNames = $tokenNames;

        foreach ($tokenNames as $i => $tokenName) {
            if ($tokenName === null) {
                continue;
            }

            if (!empty($tokenName)) {
                $firstChar = mb_substr($tokenName, 0, 1);
                if ($firstChar === '\'') {
                    $symbolicNames[$i] = null;
                    continue;
                }
                else if (self::isUpperCase($firstChar)) {
                    $literalNames[$i] = null;
                    continue;
                }
            }

            // wasn't a literal or symbolic name
            $literalNames[$i] = $symbolicNames[$i] = null;
        }

        return new Vocabulary($literalNames, $symbolicNames, $tokenNames);
    }

    private static function isUpperCase(string $str): bool
    {
        return mb_convert_case($str, MB_CASE_UPPER, "UTF-8") === $str;
    }
}
