<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

use ANTLR\v4\Runtime\ATN\ATN;
use ANTLR\v4\Runtime\Parser;
use ANTLR\v4\Runtime\RuleContext;
use ANTLR\v4\Runtime\Token;

class Trees
{
    // TODO: Missing \ANTLR\v4\Runtime\Tree\Trees implementation

    /**
     * Print out a whole tree in LISP form. {@link #getNodeText} is used on the
     * node payloads to get the text for the nodes.  Detect
     * parse trees and extract data appropriately.
     *
     * @param \ANTLR\v4\Runtime\Tree\Tree $t
     * @param \ANTLR\v4\Runtime\Parser $recog
     *
     * @return string
     */
    public static function toStringTree(Tree $t, Parser $recog): string
    {
        $ruleNames = $recog !== null ? $recog->getRuleNames() : null;

        $s = self::escapeWhitespace(self::getNodeText($t, $ruleNames), false);
        if ($t->getChildCount() === 0) {
            return $s;
        }

        $buf = "(";
        $buf .= $s;
        $buf .= " ";
        for ($i = 0; $i < $t->getChildCount(); $i++) {
            if ($i > 0) {
                $buf .= " ";
            }
            $buf .= self::toStringTree($t->getChild($i), $recog);
        }
        $buf .= ")";

        return $buf;
    }

    public static function getNodeText(Tree $t, ?array $ruleNames): string
    {
        if ($ruleNames !== null) {
            if ($t instanceof RuleContext) {
                $ruleIndex = $t->getRuleContext()->getRuleIndex();
                $ruleName = $ruleNames[$ruleIndex];
                $altNumber = $t->getAltNumber();

                if ($altNumber !== ATN::INVALID_ALT_NUMBER) {
                    return "$ruleName:$altNumber";
                }

                return $ruleName;
            } else if ($t instanceof ErrorNode) {
                return (string)$t;
            } else if ($t instanceof TerminalNode) {
                $symbol = $t->getSymbol();
                if ($symbol !== null) {
                    return $symbol->getText();
                }
            }
        }

        // no recog for rule names
        $payload = $t->getPayload();
        if ($payload instanceof Token) {
            return $payload->getText();
        }

        return (string)$payload;
    }

    private static function escapeWhitespace(string $s, bool $escapeSpaces): string
    {
        return strtr($s, [
            "\t" => "\\t",
            "\n" => "\\n",
            "\r" => "\\r",
        ]);
    }
}
