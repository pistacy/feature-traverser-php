<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Tests\Resources\Fixtures;

use Pistacy\FeatureTraverser\Tests\Resources\Fixtures\Dependency\MyService;
use Pistacy\FeatureTraverser\Tests\Resources\Fixtures\Dependency\MyRepository;

final class SimpleClass
{
    /**
     * @phpstan-param MyService $service
     * @phpstan-param MyRepository $repository
     */
    public function myMethod(MyService $service, MyRepository $repository): void
    {
        $service(); // @phpstan-ignore class.notFound

        $repository->find('id'); // @phpstan-ignore class.notFound

        MyStaticClass::staticMethod(); // @phpstan-ignore class.notFound

        new MyInstantiatedClass(); // @phpstan-ignore class.notFound

        someFunction(); // @phpstan-ignore function.notFound
    }
}
