<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

use ANTLR\v4\Runtime\Token;

interface TerminalNode extends ParseTree
{
    public function getSymbol(): Token;
}
