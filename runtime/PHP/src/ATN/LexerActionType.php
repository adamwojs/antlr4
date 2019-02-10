<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * Represents the serialization type of a {@link LexerAction}.
 */
final class LexerActionType
{
    /**
     * The type of a {@link LexerChannelAction} action.
     *
     * @var int
     */
    public const CHANNEL = 0;

    /**
     * The type of a {@link LexerCustomAction} action.
     *
     * @var int
     */
    public const CUSTOM = 1;

    /**
     * The type of a {@link LexerModeAction} action.
     *
     * @var int
     */
    public const MODE = 2;

    /**
     * The type of a {@link LexerMoreAction} action.
     *
     * @var int
     */
    public const MORE = 3;

    /**
     * The type of a {@link LexerPopModeAction} action.
     *
     * @var int
     */
    public const POP_MODE = 4;

    /**
     * The type of a {@link LexerPushModeAction} action.
     *
     * @var int
     */
    public const PUSH_MODE = 5;

    /**
     * The type of a {@link LexerSkipAction} action.
     *
     * @var int
     */
    public const SKIP = 6;

    /**
     * The type of a {@link LexerTypeAction} action.
     *
     * @var int
     */
    public const TYPE = 7;
}
