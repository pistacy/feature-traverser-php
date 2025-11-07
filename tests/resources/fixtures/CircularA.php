<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests\Resources\Fixtures;

final class CircularA
{
    public function method(): void
    {
        CircularB::staticMethod();
    }
}
