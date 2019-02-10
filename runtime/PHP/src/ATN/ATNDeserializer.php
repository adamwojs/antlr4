<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

use ANTLR\v4\Runtime\ATN\ATNDeserializer\UnicodeDeserializer;
use ANTLR\v4\Runtime\ATN\ATNDeserializer\UnicodeDeserializingMode;
use ANTLR\v4\Runtime\Exception\IllegalArgumentException;
use ANTLR\v4\Runtime\Exception\IllegalStateException;
use ANTLR\v4\Runtime\Exception\UnsupportedOperationException;
use ANTLR\v4\Runtime\Misc\Character;
use ANTLR\v4\Runtime\Misc\IntervalSet;
use ANTLR\v4\Runtime\Token;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ATNDeserializer
{
    /*
     * This value should never change. Updates following this version are
     * reflected as change in the unique ID SERIALIZED_UUID.
     */
    public const SERIALIZED_VERSION = 3;

    /**
     * This is the earliest supported serialized UUID.
     */
    private const BASE_SERIALIZED_UUID = "33761b2d-78bb-4a43-8b0b-4f5bee8aacf3";

    /**
     * This UUID indicates an extension of {@link BASE_SERIALIZED_UUID} for the
     * addition of precedence predicates.
     */
    private const ADDED_PRECEDENCE_TRANSITIONS = "1da0c57d-6c06-438a-9b27-10bcb3ce0f61";

    /**
     * This UUID indicates an extension of {@link #ADDED_PRECEDENCE_TRANSITIONS}
     * for the addition of lexer actions encoded as a sequence of
     * {@link LexerAction} instances.
     */
    private const ADDED_LEXER_ACTIONS = "aadb8d7e-aeef-4415-ad2b-8204d6cf042e";

    /**
     * This UUID indicates the serialized ATN contains two sets of
     * IntervalSets, where the second set's values are encoded as
     * 32-bit integers to support the full Unicode SMP range up to U+10FFFF.
     */
    private const ADDED_UNICODE_SMP = "59627784-3be5-417a-b9eb-8131a7286089";

    /**
     * This is the current serialized UUID.
     */
    public const SERIALIZED_UUID = self::ADDED_UNICODE_SMP;

    /**
     * This list contains all of the currently supported UUIDs, ordered by when
     * the feature first appeared in this branch.
     */
    public const SUPPORTED_UUIDS = [
        self::BASE_SERIALIZED_UUID,
        self::ADDED_PRECEDENCE_TRANSITIONS,
        self::ADDED_LEXER_ACTIONS,
        self::ADDED_UNICODE_SMP
    ];

    /** @var \ANTLR\v4\Runtime\ATN\ATNDeserializationOptions */
    private $options;

    /**
     * @param \ANTLR\v4\Runtime\ATN\ATNDeserializationOptions $options
     */
    public function __construct(ATNDeserializationOptions $options = null)
    {
        if ($options === null) {
            $options = ATNDeserializationOptions::getDefaultOptions();
        }

        $this->options = $options;
    }

    public function deserialize(array $data): ATN
    {
        // Each char value in data is shifted by +2 at the entry to this method.
        // This is an encoding optimization targeting the serialized values 0
        // and -1 (serialized to 0xFFFF), each of which are very common in the
        // serialized form of the ATN. In the modified UTF-8 that Java uses for
        // compiled string literals, these two character values have multi-byte
        // forms. By shifting each value by +2, they become characters 2 and 1
        // prior to writing the string, each of which have single-byte
        // representations. Since the shift occurs in the tool during ATN
        // serialization, each target is responsible for adjusting the values
        // during deserialization.
        //
        // As a special case, note that the first element of data is not
        // adjusted because it contains the major version number of the
        // serialized ATN, which was fixed at 3 at the time the value shifting
        // was implemented.

        for ($i = 1; $i < count($data); $i++) {
            $data[$i] = ($data[$i] - 2) & 0xFFFF;
        }

        $p = 0;

        /** @var int $version */
        $version = $data[$p++];
        if ($version !== self::SERIALIZED_VERSION) {
            $this->throwUnsupportedSerializedVersionException($version);
        }

        $uuid = $this->toUUID($data, $p);
        if (!in_array($uuid->toString(), self::SUPPORTED_UUIDS)) {
            $this->throwUnsupportedUUIDException($uuid);
        }

        $p += 8;

        $supportsPrecedencePredicates = $this->isFeatureSupported(self::ADDED_PRECEDENCE_TRANSITIONS, $uuid);
        $supportsLexerActions = $this->isFeatureSupported(self::ADDED_LEXER_ACTIONS, $uuid);

        /** @var int $grammarType */
        $grammarType = $data[$p++];
        /** @var int $maxTokenType */
        $maxTokenType = $data[$p++];

        $atn = new ATN($grammarType, $maxTokenType);

        //
        // STATES
        //

        $loopBackStateNumbers = $endStateNumbers = [];

        $nstates = $data[$p++];
        for ($i = 0; $i < $nstates; $i++) {
            $stype = $data[$p++];

            if ($stype === ATNState::INVALID_TYPE) {
                // ignore bad type of states
                $atn->addState(null);
                continue;
            }

            $ruleIndex = $data[$p++];
            if ($ruleIndex === Character::MAX_VALUE) {
                $ruleIndex = -1;
            }

            $state = $this->stateFactory($stype, $ruleIndex);
            if ($stype === ATNState::LOOP_END) {
                $loopBackStateNumbers[] = [$state, $data[$p++]];
            } else if ($state instanceof BlockStartState) {
                $endStateNumbers[] = [$state, $data[$p++]];;
            }

            $atn->addState($state);
        }

        // delay the assignment of loop back and end states until we know all the state instances have been initialized
        foreach ($loopBackStateNumbers as &$pair) {
            list($loopEndState, $stateIdx) = $pair;

            /** @var $loopEndState \ANTLR\v4\Runtime\ATN\LoopEndState */
            $loopEndState->loopBackState = $atn->states[$stateIdx];
        }

        foreach ($endStateNumbers as &$pair) {
            list($blockStartState, $stateIdx) = $pair;

            /** @var $blockStartState \ANTLR\v4\Runtime\ATN\BlockStartState */
            $blockStartState->endState = $atn->states[$stateIdx];
        }

        $numNonGreedyStates = $data[$p++];
        for ($i = 0; $i < $numNonGreedyStates; $i++) {
            $atn->states[$data[$p++]]->nonGreedy = true;
        }

        if ($supportsPrecedencePredicates) {
            $numPrecedenceStates = $data[$p++];
            for ($i = 0; $i < $numPrecedenceStates; $i++) {
                $atn->states[$data[$p++]]->isLeftRecursiveRule = true;
            }
        }

        //
        // RULES
        //

        $nrules = $data[$p++];
        if ($atn->grammarType === ATNType::LEXER) {
            $atn->ruleToTokenType = [];
        }

        $atn->ruleToStartState = [];
        for ($i = 0; $i < $nrules; $i++) {
            $atn->ruleToStartState[$i] = $atn->states[$data[$p++]];

            if ($atn->grammarType === ATNType::LEXER) {
                $ttype = $data[$p++];
                if ($ttype === 0xFFFF) {
                    $ttype = Token::EOF;
                }

                $atn->ruleToTokenType[$i] = $ttype;

                if (!$this->isFeatureSupported(self::ADDED_LEXER_ACTIONS, $uuid)) {
                    // this piece of unused metadata was serialized prior to the
                    // addition of LexerAction
                    $actionIndexIgnored = $data[$p++];
                }
            }
        }

        $atn->ruleToStopState = [];
        foreach ($atn->states as $state) {
            if (!($state instanceof RuleStopState)) {
                continue;
            }

            $atn->ruleToStopState[$state->ruleIndex] = $state;
            $atn->ruleToStartState[$state->ruleIndex]->stopState = $state;
        }

        //
        // MODES
        //
        $nmodes = $data[$p++];
        for ($i = 0; $i < $nmodes; $i++) {
            $atn->modeToStartState[] = $atn->states[$data[$p++]];
        }

        //
        // SETS
        //
        $sets = [];

        // First, read all sets with 16-bit Unicode code points <= U+FFFF.
        $p = $this->deserializeSets($data, $p, $sets, $this->getUnicodeDeserializer(UnicodeDeserializingMode::UNICODE_BMP));

        // Next, if the ATN was serialized with the Unicode SMP feature,
        // deserialize sets with 32-bit arguments <= U+10FFFF.
        if ($this->isFeatureSupported(self::ADDED_UNICODE_SMP, $uuid)) {
            $p = $this->deserializeSets($data, $p, $sets, $this->getUnicodeDeserializer(UnicodeDeserializingMode::UNICODE_SMP));
        }

        //
        // EDGES
        //
        $nedges = $data[$p++];
        for ($i = 0; $i < $nedges; $i++) {
            $src   = $data[$p++];
            $trg   = $data[$p++];
            $ttype = $data[$p++];
            $arg1  = $data[$p++];
            $arg2  = $data[$p++];
            $arg3  = $data[$p++];

            $atn->states[$src]->addTransition(
                $this->edgeFactory($atn, $ttype, $src, $trg, $arg1, $arg2, $arg3, $sets)
            );
        }

        // edges for rule stop states can be derived, so they aren't serialized
        foreach ($atn->states as $state) {
            for ($i = 0; $i < $state->getNumberOfTransitions(); $i++) {
                $transition = $state->transition($i);

                if (!($transition instanceof RuleTransition)) {
                    continue;
                }

                $outermostPrecedenceReturn = -1;
                if ($atn->ruleToStartState[$transition->target->ruleIndex]->isLeftRecursiveRule && $transition->precedence === 0) {
                    $outermostPrecedenceReturn = $transition->target->ruleIndex;
                }

                $atn->ruleToStopState[$transition->target->ruleIndex]->addTransition(new EpsilonTransition(
                    $transition->followState, $outermostPrecedenceReturn
                ));
            }
        }

        foreach ($atn->states as $state) {
            if ($state instanceof BlockStartState) {
                if ($state->endState === null) {
                    // we need to know the end state to set its start state
                    throw new IllegalStateException();
                }

                // block end states can only be associated to a single block start state
                if ($state->endState->startState !== null) {
                    throw new IllegalStateException();
                }

                $state->endState->startState = $state;
            }

            if ($state instanceof PlusLoopbackState) {
                for ($i = 0; $i < $state->getNumberOfTransitions(); $i++) {
                    $target = $state->transition($i)->target;
                    if ($target instanceof PlusBlockStartState) {
                        $target->loopBackState = $state;
                    }
                }
            }

            if ($state instanceof StarLoopbackState) {
                for ($i = 0; $i < $state->getNumberOfTransitions(); $i++) {
                    $target = $state->transition($i)->target;
                    if ($target instanceof StarLoopEntryState) {
                        $target->loopBackState = $state;
                    }
                }
            }
        }

        //
        // DECISIONS
        //
        $ndecistions = $data[$p++];
        for ($i = 1; $i <= $ndecistions; $i++) {
            /** @var \ANTLR\v4\Runtime\ATN\DecisionState $decState */
            $decState = $atn->states[$data[$p++]];
            $decState->decision = $i - 1;

            $atn->decisionToState[] = $decState;
        }

        //
        // LEXER ACTIONS
        //
        if ($atn->grammarType === ATNType::LEXER) {
            if ($supportsLexerActions) {
                $nLexerActions = $data[$p++];

                $atn->lexerActions = [];
                for ($i = 0; $i < $nLexerActions; $i++) {
                    $type  = $data[$p++];
                    $data1 = $data[$p++];
                    $data2 = $data[$p++];

                    if ($data1 === 0xFFFF) {
                        $data1 = -1;
                    }

                    if ($data2 === 0xFFFF) {
                        $data2 = -1;
                    }

                    $atn->lexerActions[$i] = $this->lexerActionFactory($type, $data1, $data2);
                }
            }
            else {
                // for compatibility with older serialized ATNs, convert the old
                // serialized action index for action transitions to the new
                // form, which is the index of a LexerCustomAction

                // TODO: Implement compatibility layer for lexer actions support
            }
        }

        $this->markPrecedenceDecisions($atn);

        if ($this->options->isVerifyATN()) {
            $this->verifyATN($atn);
        }

        if ($this->options->isGenerateRuleBypassTransitions() && $atn->grammarType === ATNType::PARSER) {
            // TODO: Implement support for "isGenerateRuleBypassTransitions" option

            // reverify after modification
            if ($this->options->isVerifyATN()) {
                $this->verifyATN($atn);
            }
        }

        return $atn;
    }

    public function getUnicodeDeserializer(int $mode): UnicodeDeserializer
    {
        if ($mode === UnicodeDeserializingMode::UNICODE_BMP) {
            return new class implements UnicodeDeserializer
            {
                public function readUnicode(array &$data, int $p): int
                {
                    return $data[$p];
                }

                public function size(): int
                {
                    return 1;
                }
            };
        }

        if ($mode === UnicodeDeserializingMode::UNICODE_SMP) {
            return new class implements UnicodeDeserializer
            {
                public function readUnicode(array &$data, int $p): int
                {
                    return $data[$p] | ($data[$p + 1] << 16);
                }

                public function size(): int
                {
                    return 2;
                }
            };
        }

        throw new InvalidArgumentException("Undefined unicode deserialization mode: $mode");
    }

    private function toUUID(array &$data, int $offset): UuidInterface
    {
        $bytes = [];
        foreach (array_slice($data, $offset, 8) as $word) {
            $bytes[] = chr($word & 0xFF);
            $bytes[] = chr($word >> 8);
        }
        $bytes = implode('', array_reverse($bytes));

        return Uuid::fromBytes($bytes);
    }

    private function deserializeSets(array &$data, int $p, array &$sets, UnicodeDeserializer $unicodeDeserializer): int
    {
        $nsets = $data[$p++];
        for ($i = 0; $i < $nsets; $i++) {
            $nintervals = $data[$p++];

            $set = new IntervalSet();

            $containsEOF = $data[$p++] !== 0;
            if ($containsEOF) {
                $set->add(-1);
            }

            for ($j = 0; $j < $nintervals; $j++) {
                $a  = $unicodeDeserializer->readUnicode($data, $p);
                $p += $unicodeDeserializer->size();
                $b  = $unicodeDeserializer->readUnicode($data, $p);
                $p += $unicodeDeserializer->size();

                $set->add($a, $b);
            }

            $sets[] = $set;
        }

        return $p;
    }

    /**
     * Analyze the {@link StarLoopEntryState} states in the specified ATN to set
     * the {@link StarLoopEntryState#isPrecedenceDecision} field to the
     * correct value.
     *
     * @param \ANTLR\v4\Runtime\ATN\ATN $atn The ATN.
     */
    private function markPrecedenceDecisions(ATN $atn): void
    {
        foreach ($atn->states as $state) {
            if (!($state instanceof StarLoopEntryState)) {
                continue;
            }

            /*
             * We analyze the ATN to determine if this ATN decision state is the
             * decision for the closure block that determines whether a
             * precedence rule should continue or complete.
             */
            if ($atn->ruleToStartState[$state->ruleIndex]->isLeftRecursiveRule) {
                $target = $state->transition($state->getNumberOfTransitions() - 1)->target;
                if (!($target instanceof LoopEndState)) {
                    continue;
                }

                if ($target->epsilonOnlyTransitions && $target->transition(0)->target instanceof RuleStopState) {
                    $state->isPrecedenceDecision = true;
                }
            }
        }
    }

    private function verifyATN(ATN $atn): void
    {
        foreach ($atn->states as $state) {
            if ($state === null) {
                continue;
            }

            $this->assert($state->onlyHasEpsilonTransitions() || $state->getNumberOfTransitions() <= 1);

            if ($state instanceof PlusBlockStartState) {
                $this->assert($state->loopBackState !== null);
            }

            if ($state instanceof StarLoopEntryState) {
                $this->assert($state->loopBackState !== null);
                $this->assert($state->getNumberOfTransitions() === 2);

                if ($state->transition(0)->target instanceof StarBlockStartState) {
                    $this->assert($state->transition(1)->target instanceof LoopEndState);
                    $this->assert(!$state->nonGreedy);
                }
                else if ($state->transition(0)->target instanceof LoopEndState) {
                    $this->assert($state->transition(1)->target instanceof StarBlockStartState);
                    $this->assert($state->nonGreedy);
                }
                else {
                    throw new IllegalStateException();
                }
            }

            if ($state instanceof StarLoopbackState) {
                $this->assert($state->getNumberOfTransitions() === 1);
                $this->assert($state->transition(0)->target instanceof StarLoopEntryState);
            }

            if ($state instanceof LoopEndState) {
                $this->assert($state->loopBackState !== null);
            }

            if ($state instanceof RuleStartState) {
                $this->assert($state->stopState !== null);
            }

            if ($state instanceof BlockStartState) {
                $this->assert($state->endState !== null);
            }

            if ($state instanceof BlockEndState) {
                $this->assert($state->startState !== null);
            }

            if ($state instanceof DecisionState) {
                $this->assert($state->getNumberOfTransitions() <= 1 || $state->decision >= 0);
            } else {
                $this->assert($state->getNumberOfTransitions() <= 1 || $state instanceof RuleStopState);
            }
        }
    }

    private function assert(bool $condition, string $msg = null): void
    {
        if (!$condition) {
            throw new IllegalStateException($msg ?? '');
        }
    }

    private function isFeatureSupported(string $featureUUID, UuidInterface $actualUUID): bool
    {
        $featureUUIDIndex = array_search($featureUUID, self::SUPPORTED_UUIDS, true);
        if ($featureUUIDIndex === false) {
            return false;
        }

        $actualUUIDIndex = array_search($actualUUID->toString(), self::SUPPORTED_UUIDS, true);
        if ($actualUUIDIndex === false) {
            return false;
        }

        return $actualUUIDIndex >= $featureUUIDIndex;
    }

    private function stateFactory(int $type, int $ruleIndex): ?ATNState
    {
        switch ($type) {
            case ATNState::INVALID_TYPE:
                return null;
            case ATNState::BASIC:
                $s = new BasicState();
                break;
            case ATNState::RULE_START:
                $s = new RuleStartState();
                break;
            case ATNState::BLOCK_START:
                $s = new BasicBlockStartState();
                break;
            case ATNState::PLUS_BLOCK_START:
                $s = new PlusBlockStartState();
                break;
            case ATNState::STAR_BLOCK_START:
                $s = new StarBlockStartState();
                break;
            case ATNState::TOKEN_START:
                $s = new TokensStartState();
                break;
            case ATNState::RULE_STOP:
                $s = new RuleStopState();
                break;
            case ATNState::BLOCK_END:
                $s = new BlockEndState();
                break;
            case ATNState::STAR_LOOP_BACK:
                $s = new StarLoopbackState();
                break;
            case ATNState::STAR_LOOP_ENTRY:
                $s = new StarLoopEntryState();
                break;
            case ATNState::PLUS_LOOP_BACK:
                $s = new PlusLoopbackState();
                break;
            case ATNState::LOOP_END:
                $s = new LoopEndState();
                break;
            default:
                throw new IllegalStateException("The specified state type $type is not valid.");
        }

        $s->ruleIndex = $ruleIndex;

        return $s;
    }

    private function edgeFactory(ATN $atn, int $type, int $src, int $trg, int $arg1, int $arg2, int $arg3, array $sets): Transition
    {
        $target = $atn->states[$trg];

        switch ($type) {
            case Transition::EPSILON:
                return new EpsilonTransition($target);
            case Transition::RANGE:
                return new RangeTransition($target, $arg3 !== 0 ? Token::EPSILON : $arg1, $arg2);
            case Transition::RULE:
                return new RuleTransition($atn->states[$arg1], $arg2, $arg3, $target);
            case Transition::PREDICATE:
                return new PredicateTransition($target, $arg1, $arg2, $arg3 !== 0);
            case Transition::PRECEDENCE:
                return new PrecedencePredicateTransition($target, $arg1);
            case Transition::ATOM:
                return new AtomTransition($target, $arg3 !== 0 ? Token::EOF : $arg1);
            case Transition::ACTION:
                return new ActionTransition($target, $arg1, $arg2, $arg3 !== 0);
            case Transition::SET:
                return new SetTransition($target, $sets[$arg1]);
            case Transition::NOT_SET:
                return new NotSetTransition($target, $sets[$arg1]);
            case Transition::WILDCARD:
                return new WildcardTransition($target);
        }

        throw new IllegalArgumentException("The specified transition type is not valid.");
    }

    private function lexerActionFactory(int $type, int $data1, int $data2): LexerAction
    {
        switch ($type) {
            case LexerActionType::CHANNEL:
                return new LexerChannelAction($data1);
            case LexerActionType::CUSTOM:
                return new LexerCustomAction($data1, $data2);
            case LexerActionType::MODE:
                return new LexerModeAction($data1);
            case LexerActionType::MORE:
                return LexerMoreAction::getInstance();
            case LexerActionType::POP_MODE:
                return LexerPopModeAction::getInstance();
            case LexerActionType::PUSH_MODE:
                return new LexerPushModeAction($data1);
            case LexerActionType::SKIP:
                return LexerSkipAction::getInstance();
            case LexerActionType::TYPE:
                return new LexerTypeAction($data1);
        }

        throw new IllegalArgumentException("The specified lexer action type $type is not valid.");
    }

    private function throwUnsupportedUUIDException(UuidInterface $uuid): void
    {
        $message = sprintf(
            "Could not deserialize ATN with UUID %s (expected %s or a legacy UUID).",
            $uuid->toString(),
            implode(', ', self::SUPPORTED_UUIDS)
        );

        throw new UnsupportedOperationException($message);
    }

    private function throwUnsupportedSerializedVersionException(int $version): void
    {
        throw new UnsupportedOperationException(sprintf(
            "Could not deserialize ATN with version %d (expected %d).", $version, self::SERIALIZED_VERSION
        ));
    }
}
