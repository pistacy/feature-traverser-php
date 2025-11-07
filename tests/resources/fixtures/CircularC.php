<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests\Resources\Fixtures;

final class CircularC
{
    public static function anotherMethod(): void
    {
        CircularA::method(); // @phpstan-ignore method.staticCall
    }
}
