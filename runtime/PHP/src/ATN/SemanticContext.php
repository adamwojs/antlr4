<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\ATN\SemanticContext\AndOperator;
use ANTLR\v4\Runtime\ATN\SemanticContext\OrOperator;
use ANTLR\v4\Runtime\ATN\SemanticContext\PrecedencePredicate;
use ANTLR\v4\Runtime\ATN\SemanticContext\Predicate;
use ANTLR\v4\Runtime\BaseObject;
use ANTLR\v4\Runtime\Recognizer;
use ANTLR\v4\Runtime\RuleContext;
use Traversable;

/**
 * A tree structure used to record the semantic context in which
 * an ATN configuration is valid.  It's either a single predicate,
 * a conjunction {@code p1&&p2}, or a sum of products {@code p1||p2}.
 *
 * <p>I have scoped the {@link AND}, {@link OR}, and {@link Predicate} subclasses of
 * {@link SemanticContext} within the scope of this outer class.</p>
 */
abstract class SemanticContext extends BaseObject
{
    /**
     * The default {@link SemanticContext}, which is semantically equivalent to
     * a predicate of the form {@code {true}?}.
     *
     * @var \ANTLR\v4\Runtime\ATN\SemanticContext
     */
    private static $none = null;

    /**
     * For context independent predicates, we evaluate them without a local
     * context (i.e., null context). That way, we can evaluate them without
     * having to create proper rule-specific context during prediction (as
     * opposed to the parser, which creates them naturally). In a practical
     * sense, this avoids a cast exception from RuleContext to myruleContext.
     *
     * <p>For context dependent predicates, we must pass in a local context so that
     * references such as $arg evaluate properly as _localctx.arg. We only
     * capture context dependent predicates in the context in which we begin
     * prediction, so we passed in the outer context here in case of context
     * dependent predicate evaluation.</p>
     */
    public abstract function evaluate(Recognizer $parser, RuleContext $parserCallStack): bool;

    /**
     * Evaluate the precedence predicates for the context and reduce the result.
     *
     * @param \ANTLR\v4\Runtime\Recognizer $parser The parser instance.
     * @param \ANTLR\v4\Runtime\RuleContext $parserCallStack
     *
     * @return \ANTLR\v4\Runtime\ATN\SemanticContext|null The simplified semantic context after precedence predicates are
     * evaluated, which will be one of the following values.
     * <ul>
     * <li>{@link #NONE}: if the predicate simplifies to {@code true} after
     * precedence predicates are evaluated.</li>
     * <li>{@code null}: if the predicate simplifies to {@code false} after
     * precedence predicates are evaluated.</li>
     * <li>{@code this}: if the semantic context is not changed as a result of
     * precedence predicate evaluation.</li>
     * <li>A non-{@code null} {@link SemanticContext}: the new simplified
     * semantic context after precedence predicates are evaluated.</li>
     * </ul>
     */
    public function evaluatePrecedence(Recognizer $parser, RuleContext $parserCallStack): ?SemanticContext
    {
        return $this;
    }

    public static function and(?SemanticContext $a, ?SemanticContext $b): SemanticContext
    {
        if ($a === null || $a === self::NONE()) {
            return $b;
        }

        if ($b === null || $b === self::NONE()) {
            return $a;
        }

        $result = new AndOperator($a, $b);
        if (count($result->opnds) === 1) {
            return $result->opnds[0];
        }

        return $result;
    }

    public static function or(?SemanticContext $a, ?SemanticContext $b): SemanticContext
    {
        if ($a === null) {
            return $b;
        }

        if ($b === null) {
            return $a;
        }

        if ($a === self::NONE() || $b === self::NONE()) {
            return self::NONE();
        }

        $result = new OrOperator($a, $b);
        if (count($result->opnds) === 1) {
            return $result->opnds[0];
        }

        return $result;
    }

    public static function filterPrecedencePredicates(Traversable $collection): array
    {
        $result = [];

        foreach ($collection as $context) {
            if ($context instanceof PrecedencePredicate) {
                $result[] = $context;
            }
        }

        return $result;
    }

    public static function NONE(): SemanticContext
    {
        if (self::$none === null) {
            self::$none = new Predicate();
        }

        return self::$none;
    }
}
