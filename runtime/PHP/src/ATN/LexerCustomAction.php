<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Lexer;

/**
 * Executes a custom lexer action by calling {@link Recognizer#action} with the
 * rule and action indexes assigned to the custom action. The implementation of
 * a custom action is added to the generated code for the lexer in an override
 * of {@link Recognizer#action} when the grammar is compiled.
 *
 * <p>This class may represent embedded actions created with the <code>{...}</code>
 * syntax in ANTLR 4, as well as actions created for lexer commands where the
 * command argument could not be evaluated when the grammar was compiled.</p>
 */
final class LexerCustomAction extends BaseObject implements LexerAction
{
    /** @var int */
    private $ruleIndex;

    /** @var int */
    private $actionIndex;

    /**
     * Constructs a custom lexer action with the specified rule and action
     * indexes.
     *
     * @param int $ruleIndex The rule index to use for calls to {@link Recognizer#action}.
     * @param int $actionIndex The action index to use for calls to {@link Recognizer#action}.
     */
    public function __construct(int $ruleIndex, int $actionIndex)
    {
        $this->ruleIndex = $ruleIndex;
        $this->actionIndex = $actionIndex;
    }

    /**
     * Gets the rule index to use for calls to {@link Recognizer#action}.
     *
     * @return int The rule index for the custom action.
     */
    public function getRuleIndex(): int
    {
        return $this->ruleIndex;
    }

    /**
     * Gets the action index to use for calls to {@link Recognizer#action}.
     *
     * @return int The action index for the custom action.
     */
    public function getActionIndex(): int
    {
        return $this->actionIndex;
    }

    /**
     * {@inheritdoc}
     */
    public function getActionType(): int
    {
        return LexerActionType::CUSTOM;
    }

    /**
     * {@inheritdoc}
     *
     * <p>Custom actions are position-dependent since they may represent a
     * user-defined embedded action which makes calls to methods like
     * {@link Lexer#getText}.</p>
     */
    public function isPositionDependent(): bool
    {
        return true;
    }

    /**
     * {@inheritdoc}
     *
     * <p>Custom actions are implemented by calling {@link Lexer#action} with the
     * appropriate rule and action indexes.</p>
     */
    public function execute(Lexer $lexer): void
    {
        $lexer->action(null, $this->ruleIndex, $this->actionIndex);
    }

    /**
     * {@inheritdoc}
     */
    public function equals($o): bool
    {
        if ($o === $this) {
            return true;
        }

        if ($o instanceof LexerCustomAction) {
            return $o->getRuleIndex() === $this->getRuleIndex()
                && $o->getActionIndex() === $this->getActionIndex();
        }

        return false;
    }
}
