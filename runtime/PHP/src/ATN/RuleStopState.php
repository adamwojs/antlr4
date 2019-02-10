<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * The last node in the ATN for a rule, unless that rule is the start symbol.
 * In that case, there is one transition to EOF. Later, we might encode
 * references to all calls to this rule to compute FOLLOW sets for
 * error handling.
 */
final class RuleStopState extends ATNState
{
    /**
     * {@inheritdoc}
     */
    public function getStateType(): int
    {
        return self::RULE_STOP;
    }
}
