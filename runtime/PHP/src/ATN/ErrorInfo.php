<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\ATN;

/**
 * This class represents profiling event information for a syntax error
 * identified during prediction. Syntax errors occur when the prediction
 * algorithm is unable to identify an alternative which would lead to a
 * successful parse.
 *
 * @see Parser#notifyErrorListeners(Token, String, RecognitionException)
 * @see ANTLRErrorListener#syntaxError
 */
class ErrorInfo extends DecisionEventInfo
{
}
