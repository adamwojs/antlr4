<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

/**
 * This implementation of {@link LexerAction} is used for tracking input offsets
 * for position-dependent actions within a {@link LexerActionExecutor}.
 *
 * <p>This action is not serialized as part of the ATN, and is only required for
 * position-dependent lexer actions which appear at a location other than the
 * end of a rule. For more information about DFA optimizations employed for
 * lexer actions, see {@link LexerActionExecutor#append} and
 * {@link LexerActionExecutor#fixOffsetBeforeMatch}.</p>
 */
final class LexerIndexedCustomAction extends BaseObject implements LexerAction
{
    /** @var int */
    private $offset;

    /** @var \ANTLR\v4\Runtime\ATN\LexerAction */
    private $action;

    /**
     * Constructs a new indexed custom action by associating a character offset
     * with a {@link LexerAction}.
     *
     * <p>Note: This class is only required for lexer actions for which
     * {@link LexerAction#isPositionDependent} returns {@code true}.</p>
     *
     * @param int $offset The offset into the input {@link CharStream}, relative to
     * the token start index, at which the specified lexer action should be
     * executed.
     * @param \ANTLR\v4\Runtime\ATN\LexerAction $action The lexer action to execute at a particular offset in the
     * input {@link CharStream}.
     */
    public function __construct(int $offset, LexerAction $action)
    {
        $this->offset = $offset;
        $this->action = $action;
    }

    /**
     * Gets the location in the input {@link CharStream} at which the lexer
     * action should be executed. The value is interpreted as an offset relative
     * to the token start index.
     *
     * @return int The location in the input {@link CharStream} at which the lexer
     * action should be executed.
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Gets the lexer action to execute.
     *
     * @return \ANTLR\v4\Runtime\ATN\LexerAction A {@link LexerAction} object which executes the lexer action.
     */
    public function getAction(): LexerAction
    {
        return $this->action;
    }

    /**
     * {@inheritdoc}
     */
    public function getActionType(): int
    {
        return $this->action->getActionType();
    }

    /**
     * {@inheritdoc}
     */
    public function isPositionDependent(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * <p>This method calls {@link #execute} on the result of {@link #getAction}
     * using the provided {@code lexer}.</p>
     */
    public function execute(Lexer $lexer): void
    {
        // assume the input stream position was properly set by the calling code
        $this->action->execute($lexer);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof LexerIndexedCustomAction) {
            return $o->getOffset() === $this->getOffset()
                && $o->getAction()->equals($this->getAction());
        }

        return false;
    }
}
