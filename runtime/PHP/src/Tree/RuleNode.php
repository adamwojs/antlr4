<?php

declare(strict_types=1);

namespace ANTLR\v4\Runtime\Tree;

use ANTLR\v4\Runtime\RuleContext;

interface RuleNode extends ParseTree
{
    public function getRuleContext(): RuleContext;
}
