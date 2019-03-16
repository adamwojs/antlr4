<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\CharStream;
use ANTLR\v4\Runtime\Lexer;
use ANTLR\v4\Runtime\Misc\MurmurHash;

/**
 * Represents an executor for a sequence of lexer actions which traversed during
 * the matching operation of a lexer rule (token).
 *
 * <p>The executor tracks position information for position-dependent lexer actions
 * efficiently, ensuring that actions appearing only at the end of the rule do
 * not cause bloating of the {@link DFA} created for the lexer.</p>
 */
class LexerActionExecutor extends BaseObject
{
    /** @var \ANTLR\v4\Runtime\ATN\LexerAction[] */
    private $lexerActions;

    /**
     * Caches the result of {@link #hashCode} since the hash code is an element
     * of the performance-critical {@link LexerATNConfig#hashCode} operation.
     *
     * @var int
     */
    private $hashCode;

    /**
     * @param \ANTLR\v4\Runtime\ATN\LexerAction[] $lexerActions
     */
    public function __construct(array $lexerActions)
    {
        $this->lexerActions = $lexerActions;

        $hash = MurmurHash::initialize();
        foreach ($this->lexerActions as $lexerAction) {
            $hash = MurmurHash::update($hash, $lexerAction->hash());
        }

        $this->hashCode = MurmurHash::finish($hash, count($this->lexerActions));
    }

    /**
     * Creates a {@link LexerActionExecutor} which encodes the current offset
     * for position-dependent lexer actions.
     *
     * <p>Normally, when the executor encounters lexer actions where
     * {@link LexerAction#isPositionDependent} returns {@code true}, it calls
     * {@link IntStream#seek} on the input {@link CharStream} to set the input
     * position to the <em>end</em> of the current token. This behavior provides
     * for efficient DFA representation of lexer actions which appear at the end
     * of a lexer rule, even when the lexer rule matches a variable number of
     * characters.</p>
     *
     * <p>Prior to traversing a match transition in the ATN, the current offset
     * from the token start index is assigned to all position-dependent lexer
     * actions which have not already been assigned a fixed offset. By storing
     * the offsets relative to the token start index, the DFA representation of
     * lexer actions which appear in the middle of tokens remains efficient due
     * to sharing among tokens of the same length, regardless of their absolute
     * position in the input stream.</p>
     *
     * <p>If the current executor already has offsets assigned to all
     * position-dependent lexer actions, the method returns {@code this}.</p>
     *
     * @param int $offset the current offset to assign to all position-dependent
     * lexer actions which do not already have offsets assigned
     *
     * @return \ANTLR\v4\Runtime\ATN\LexerActionExecutor a {@link LexerActionExecutor} which stores input stream offsets
     * for all position-dependent lexer actions
     */
    public function fixOffsetBeforeMatch(int $offset): self
    {
        $updatedLexerActions = null;
        foreach ($this->lexerActions as $i => $lexerAction) {
            if ($lexerAction->isPositionDependent() && !($lexerAction instanceof LexerIndexedCustomAction)) {
                if ($updatedLexerActions === null) {
                    $updatedLexerActions = $this->lexerActions;
                }

                $updatedLexerActions[$i] = new LexerIndexedCustomAction($offset, $lexerAction);
            }
        }

        if ($updatedLexerActions === null) {
            return $this;
        }

        return new self($updatedLexerActions);
    }

    /**
     * Gets the lexer actions to be executed by this executor.
     *
     * @return \ANTLR\v4\Runtime\ATN\LexerAction[] the lexer actions to be executed by this executor
     */
    public function getLexerActions(): array
    {
        return $this->lexerActions;
    }

    /**
     * Execute the actions encapsulated by this executor within the context of a
     * particular {@link Lexer}.
     *
     * <p>This method calls {@link IntStream#seek} to set the position of the
     * {@code input} {@link CharStream} prior to calling
     * {@link LexerAction#execute} on a position-dependent action. Before the
     * method returns, the input position will be restored to the same position
     * it was in when the method was invoked.</p>
     *
     * @param \ANTLR\v4\Runtime\Lexer $lexer the lexer instance
     * @param \ANTLR\v4\Runtime\CharStream $input The input stream which is the source for the current token.
     * When this method is called, the current {@link IntStream#index} for
     * {@code input} should be the start of the following token, i.e. 1
     * character past the end of the current token.
     * @param int $startIndex The token start index. This value may be passed to
     * {@link IntStream#seek} to set the {@code input} position to the beginning
     * of the token.
     */
    public function execute(Lexer $lexer, CharStream $input, int $startIndex): void
    {
        $requireSeek = false;
        $stopIndex = $input->index();

        try {
            foreach ($this->lexerActions as $lexerAction) {
                if ($lexerAction instanceof LexerIndexedCustomAction) {
                    $offset = $lexerAction->getOffset();
                    $input->seek($startIndex + $offset);
                    $lexerAction = $lexerAction->getAction();
                    $requireSeek = ($startIndex + $offset) !== $stopIndex;
                } elseif ($lexerAction->isPositionDependent()) {
                    $input->seek($stopIndex);
                    $requireSeek = false;
                }

                $lexerAction->execute($lexer);
            }
        } finally {
            if ($requireSeek) {
                $input->seek($stopIndex);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function hash(): int
    {
        return $this->hashCode;
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof self) {
            if ($o->hashCode !== $this->hashCode) {
                return false;
            }

            if (count($o->lexerActions) !== count($this->lexerActions)) {
                return false;
            }

            foreach ($o->lexerActions as $i => $lexerAction) {
                if (!$lexerAction->equals($this->lexerActions[$i])) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Creates a {@link LexerActionExecutor} which executes the actions for
     * the input {@code lexerActionExecutor} followed by a specified
     * {@code lexerAction}.
     *
     * @param \ANTLR\v4\Runtime\ATN\LexerActionExecutor|null $executor The executor for actions already traversed by
     * the lexer while matching a token within a particular
     * {@link LexerATNConfig}. If this is {@code null}, the method behaves as
     * though it were an empty executor.
     * @param \ANTLR\v4\Runtime\ATN\LexerAction $action the lexer action to execute after the actions
     * specified in {@code lexerActionExecutor}
     *
     * @return \ANTLR\v4\Runtime\ATN\LexerActionExecutor a {@link LexerActionExecutor} for executing the combine actions
     * of {@code lexerActionExecutor} and {@code lexerAction}
     */
    public static function append(?self $executor, LexerAction $action): self
    {
        if ($executor === null) {
            return new self([$action]);
        }

        return new self(array_merge($executor->lexerActions, [$action]));
    }
}
