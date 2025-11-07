<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests\Resources\Fixtures;

final class ClassUsingGlobalFunctions
{
    public function methodUsingFunctions(): void
    {
        myGlobalFunction();

        anotherGlobalFunction();
    }
}
