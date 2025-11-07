<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures;

final class CircularA
{
    public function method(): void
    {
        CircularB::staticMethod();
    }
}
