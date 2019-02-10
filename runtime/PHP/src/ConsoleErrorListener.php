<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ANTLR\v4\Runtime\Exception\RecognitionException;

class ConsoleErrorListener extends BaseErrorListener
{
    /**
     * {@inheritdoc}
     *
     * <p>
     * This implementation prints messages to {@link System#err} containing the
     * values of {@code line}, {@code charPositionInLine}, and {@code msg} using
     * the following format.</p>
     *
     * <pre>
     * line <em>line</em>:<em>charPositionInLine</em> <em>msg</em>
     * </pre>
     */
    public function syntaxError(
        Recognizer $recognizer,
        $offendingSymbol,
        int $line,
        int $charPositionInLine,
        string $msg,
        ?RecognitionException $e
    ): void {
        fprintf(STDERR, "line %d:%d %s\n", $line, $charPositionInLine, $msg);
    }
}
