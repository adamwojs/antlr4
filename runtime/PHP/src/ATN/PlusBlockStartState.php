<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * Start of {@code (A|B|...)+} loop. Technically a decision state, but
 * we don't use for code generation; somebody might need it, so I'm defining
 * it for completeness. In reality, the {@link PlusLoopbackState} node is the
 * real decision-making note for {@code A+}.
 */
final class PlusBlockStartState extends BlockStartState
{
    /** @var \ANTLR\v4\Runtime\ATN\PlusLoopbackState */
    public $loopBackState;

    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::PLUS_BLOCK_START;
    }
}
