<?php

namespace Rikudou\SourceGenerators\Contract;

use Rikudou\SourceGenerators\Context\Context;

interface SourceGenerator
{
    public function execute(Context $context): void;
}
