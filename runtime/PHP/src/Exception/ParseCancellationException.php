<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Exception;

/**
 * This exception is thrown to cancel a parsing operation. This exception does
 * not extend {@link RecognitionException}, allowing it to bypass the standard
 * error recovery mechanisms. {@link BailErrorStrategy} throws this exception in
 * response to a parse error.
 */
class ParseCancellationException extends IllegalStateException
{
}
