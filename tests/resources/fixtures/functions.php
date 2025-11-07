<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests\Resources\Fixtures;

function myGlobalFunction(): void
{
    anotherGlobalFunction();
}

function anotherGlobalFunction(): void
{
}
