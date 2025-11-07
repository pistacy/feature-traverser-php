<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests\Resources\Fixtures;

final class CircularC
{
    public static function anotherMethod(): void
    {
        CircularA::method(); // @phpstan-ignore method.staticCall
    }
}
