<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures;

function myGlobalFunction(): void
{
    anotherGlobalFunction();
}

function anotherGlobalFunction(): void
{
}
