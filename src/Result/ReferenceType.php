<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Result;

enum ReferenceType: string
{
    case METHOD_CALL = 'method_call';
    case STATIC_CALL = 'static_call';
    case FUNCTION_CALL = 'function_call';
    case CLASS_INSTANTIATION = 'class_instantiation';
    case INTERFACE_USAGE = 'interface_usage';
    case TRAIT_USAGE = 'trait_usage';
    case PARAMETER_TYPE = 'parameter_type';
}
