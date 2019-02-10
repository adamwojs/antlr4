<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime;

use ArrayObject;

final class RuleInvocationStack extends ArrayObject
{
    public function __toString(): string
    {
        return '[' . implode(', ', $this->getArrayCopy()) . ']';
    }
}
