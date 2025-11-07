<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests\Resources\Fixtures;

final class CircularB
{
    public static function staticMethod(): void
    {
        CircularC::anotherMethod();
    }
}
