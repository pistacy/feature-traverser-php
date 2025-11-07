<?php

declare(strict_types=1);

namespace Pistacy\FeatureTraverser\Analyzer\NodeVisitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

final class CallCollectorVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<array{type: string, class: string|null, method: string|null, line: int}>
     */
    private array $calls = [];

    private ?string $currentClass = null;
    private ?string $currentMethod = null;
    private ?string $currentNamespace = null;
    private ?string $targetClassName = null;
    private ?string $targetMethodName = null;
    private bool $insideTargetMethod = false;

    /**
     * @var array<string, string> Map of use statements (alias => fully qualified name)
     * Used only for resolving DocBlock types
     */
    private array $useStatements = [];

    /**
     * @var array<string, string> Map of variable names to their types from method parameters
     */
    private array $parameterTypes = [];

    /**
     * @var array<string, string> Map of property names to their types from constructor
     */
    private array $propertyTypes = [];

    /**
     * @var array<string, string> Map of variable names to their types (from foreach, assignments, etc.)
     */
    private array $variableTypes = [];

    public function setTargetMethod(?string $className, ?string $methodName): void
    {
        $this->targetClassName = $className;
        $this->targetMethodName = $methodName;
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->calls = [];
        $this->currentClass = null;
        $this->currentMethod = null;
        $this->currentNamespace = null;
        $this->useStatements = [];
        $this->insideTargetMethod = false;

        return null;
    }

    public function enterNode(Node $node): ?int
    {
        // Track namespace (for DocBlock resolution)
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->currentNamespace = $node->name?->toString();
        }

        // Track use statements (for DocBlock resolution)
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $alias = $use->getAlias();
                if ($alias !== null) {
                    $aliasString = $alias->toString();
                } else {
                    $aliasString = $use->name->getLast();
                }
                $this->useStatements[$aliasString] = $use->name->toString();
            }
        }

        // Track current class (get FQCN from namespacedName attribute added by NameResolver)
        if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_ || $node instanceof Node\Stmt\Trait_) {
            $this->currentClass = $node->namespacedName?->toString() ?? $node->name?->toString();

            // Extract property types from constructor property promotion and @var annotations
            $this->extractPropertyTypes($node);
        }

        // Track current method
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->currentMethod = $node->name->toString();

            // Check if we're entering the target method
            if ($this->targetClassName !== null && $this->targetMethodName !== null) {
                if ($this->currentClass === $this->targetClassName && $this->currentMethod === $this->targetMethodName) {
                    $this->insideTargetMethod = true;

                    // Extract parameter types from method signature
                    $this->extractParameterTypes($node);

                    // Collect parameter types as dependencies
                    $this->collectParameterTypeDependencies($node);
                }
            } else {
                // If no target specified, collect from all methods
                $this->insideTargetMethod = true;
                $this->extractParameterTypes($node);
                $this->collectParameterTypeDependencies($node);
            }
        }

        // Track global functions
        if ($node instanceof Node\Stmt\Function_) {
            // If no specific target, collect from all functions
            if ($this->targetClassName === null && $this->targetMethodName === null) {
                $this->insideTargetMethod = true;
            }
        }

        // Only collect calls if we're inside the target method (or no target specified)
        if (!$this->insideTargetMethod) {
            return null;
        }

        // Collect method calls
        if ($node instanceof Node\Expr\MethodCall) {
            $this->collectMethodCall($node);
        }

        // Collect static calls
        if ($node instanceof Node\Expr\StaticCall) {
            $this->collectStaticCall($node);
        }

        // Collect function calls
        if ($node instanceof Node\Expr\FuncCall) {
            $this->collectFunctionCall($node);
        }

        // Collect new instantiations
        if ($node instanceof Node\Expr\New_) {
            $this->collectInstantiation($node);
        }

        // Collect invokable calls (e.g., $variable(...))
        if ($node instanceof Node\Expr\FuncCall && $node->name instanceof Node\Expr\Variable) {
            $this->collectInvokableCall($node);
        }

        // Track foreach loops to capture variable types
        if ($node instanceof Node\Stmt\Foreach_) {
            $this->trackForeachVariableType($node);
        }

        // Track variable assignments to infer types
        if ($node instanceof Node\Expr\Assign) {
            $this->trackVariableAssignment($node);
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        // Reset current method when leaving method
        if ($node instanceof Node\Stmt\ClassMethod) {
            $this->currentMethod = null;
            $this->insideTargetMethod = false;
            $this->parameterTypes = [];
            $this->variableTypes = [];
        }

        // Reset when leaving global function
        if ($node instanceof Node\Stmt\Function_) {
            if ($this->targetClassName === null && $this->targetMethodName === null) {
                $this->insideTargetMethod = false;
            }
        }

        // Reset current class when leaving class
        if ($node instanceof Node\Stmt\Class_ || $node instanceof Node\Stmt\Interface_ || $node instanceof Node\Stmt\Trait_) {
            $this->currentClass = null;
        }

        return null;
    }

    private function collectMethodCall(Node\Expr\MethodCall $node): void
    {
        if ($node->name instanceof Node\Identifier) {
            $methodName = $node->name->toString();

            // Try to resolve the class from the variable type
            $className = $this->resolveClassName($node->var);

            $this->calls[] = [
                'type' => 'method_call',
                'class' => $className,
                'method' => $methodName,
                'line' => $node->getLine(),
            ];
        }
    }

    private function extractPropertyTypes(Node\Stmt $node): void
    {
        if (!($node instanceof Node\Stmt\Class_)) {
            return;
        }

        foreach ($node->stmts as $stmt) {
            // Extract from constructor property promotion
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() === '__construct') {
                foreach ($stmt->params as $param) {
                    if ($param->flags !== 0 && $param->type instanceof Node\Name && $param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                        $propertyName = $param->var->name;
                        // Use namespacedName added by NameResolver, or toString() if already FullyQualified
                        $namespacedName = $param->type->getAttribute('namespacedName');
                        if ($namespacedName instanceof Node\Name) {
                            $typeName = $namespacedName->toString();
                        } else {
                            $typeName = $param->type->toString();
                        }

                        $this->propertyTypes[$propertyName] = $typeName;
                    }
                }
            }

            // Extract from property declarations with @var docblocks
            // Note: DocBlocks can't benefit from NameResolver, must resolve manually
            if ($stmt instanceof Node\Stmt\Property) {
                $docComment = $stmt->getDocComment();
                if ($docComment !== null) {
                    $propertyName = $stmt->props[0]->name->toString();
                    $typeName = $this->extractTypeFromDocBlock($docComment->getText());

                    if ($typeName !== null) {
                        // Resolve type name using current namespace and use statements
                        $resolvedTypeName = $this->resolveDocBlockTypeName($typeName);
                        $this->propertyTypes[$propertyName] = $resolvedTypeName;
                    }
                }
            }
        }
    }

    /**
     * Resolve a type name from DocBlock to FQCN using use statements and namespace
     */
    private function resolveDocBlockTypeName(string $typeName): string
    {
        // Already fully qualified
        if (str_starts_with($typeName, '\\')) {
            return ltrim($typeName, '\\');
        }

        // Check use statements
        $parts = explode('\\', $typeName);
        $firstPart = $parts[0];

        if (isset($this->useStatements[$firstPart])) {
            $parts[0] = $this->useStatements[$firstPart];
            return implode('\\', $parts);
        }

        // Prepend current namespace
        if ($this->currentNamespace !== null) {
            return $this->currentNamespace . '\\' . $typeName;
        }

        return $typeName;
    }

    /**
     * Extract type from @var docblock
     * Handles Collection<int, TypeName> and simple TypeName
     */
    private function extractTypeFromDocBlock(string $docComment): ?string
    {
        // Match @var Collection<int, FullyQualifiedClassName>
        if (preg_match('/@var\s+Collection<[^,]+,\s*([^>]+)>/', $docComment, $matches)) {
            return trim($matches[1]);
        }

        // Match @var array<FullyQualifiedClassName>
        if (preg_match('/@var\s+array<[^,]+,\s*([^>]+)>/', $docComment, $matches)) {
            return trim($matches[1]);
        }

        // Match simple @var FullyQualifiedClassName
        if (preg_match('/@var\s+([^\s]+)/', $docComment, $matches)) {
            $type = trim($matches[1]);

            // Skip primitive types and Collection/array without generics
            if (in_array($type, ['string', 'int', 'bool', 'float', 'array', 'Collection'], true)) {
                return null;
            }

            return $type;
        }

        return null;
    }

    /**
     * Track variable type in foreach loop
     */
    private function trackForeachVariableType(Node\Stmt\Foreach_ $node): void
    {
        // Get the expression being iterated (e.g., $this->views)
        $expr = $node->expr;

        // Try to resolve the collection type
        $collectionType = null;

        if (
            $expr instanceof Node\Expr\PropertyFetch
            && $expr->var instanceof Node\Expr\Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Node\Identifier
        ) {
            $propertyName = $expr->name->toString();
            $collectionType = $this->propertyTypes[$propertyName] ?? null;
        }

        // If we found the element type, track the foreach variable
        if ($collectionType !== null && $node->valueVar instanceof Node\Expr\Variable && is_string($node->valueVar->name)) {
            $variableName = $node->valueVar->name;
            $this->variableTypes[$variableName] = $collectionType;
        }
    }

    /**
     * Track variable assignment to infer variable types
     * e.g., $model = $this->repository->getForUpdate(...) means $model has the return type of getForUpdate
     */
    private function trackVariableAssignment(Node\Expr\Assign $node): void
    {
        // Only track simple variable assignments
        if (!($node->var instanceof Node\Expr\Variable) || !is_string($node->var->name)) {
            return;
        }

        $variableName = $node->var->name;

        // Check if right side is a method call
        if ($node->expr instanceof Node\Expr\MethodCall) {
            $type = $this->inferTypeFromMethodCall($node->expr);
            if ($type !== null) {
                $this->variableTypes[$variableName] = $type;
            }
        }
    }

    /**
     * Try to infer the return type of a method call
     * For now, we use simple heuristics based on method return type declarations
     */
    private function inferTypeFromMethodCall(Node\Expr\MethodCall $node): ?string
    {
        // Get the class type of the object we're calling the method on
        $className = $this->resolveClassName($node->var);
        if ($className === null) {
            return null;
        }

        // Get the method name
        if (!($node->name instanceof Node\Identifier)) {
            return null;
        }
        $methodName = $node->name->toString();

        // Try to load and parse the class/interface to get return type
        return $this->getMethodReturnType($className, $methodName);
    }

    /**
     * Get the return type of a method by parsing its declaration
     */
    private function getMethodReturnType(string $className, string $methodName): ?string
    {
        // Try to locate the file for this class
        $classFile = $this->locateClassFile($className);
        if ($classFile === null || !file_exists($classFile)) {
            return null;
        }

        // Parse the file
        try {
            $code = file_get_contents($classFile);
            if ($code === false) {
                return null;
            }

            $parser = (new \PhpParser\ParserFactory())->create(\PhpParser\ParserFactory::PREFER_PHP7);
            $stmts = $parser->parse($code);
            if ($stmts === null) {
                return null;
            }

            // Apply NameResolver to resolve all class names to FQCN
            $traverser = new \PhpParser\NodeTraverser();
            $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());
            /** @var array<Node\Stmt> $resolvedStmts */
            $resolvedStmts = $traverser->traverse($stmts);

            // Find the method and extract its return type
            return $this->extractReturnTypeFromAst($resolvedStmts, $methodName);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Locate the file path for a given class name
     */
    private function locateClassFile(string $className): ?string
    {
        // Try to use Composer's autoloader to find the class
        $classLoaderPath = null;
        foreach (
            [
            '/var/www/current/vendor/autoload.php',
            __DIR__ . '/../../../vendor/autoload.php',
            __DIR__ . '/../../../../vendor/autoload.php',
            ] as $path
        ) {
            if (file_exists($path)) {
                $classLoaderPath = $path;
                break;
            }
        }

        if ($classLoaderPath === null) {
            return null;
        }

        require_once $classLoaderPath;

        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
                $file = $autoloader[0]->findFile($className);
                if ($file !== false) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Extract return type from AST of a class/interface
     * Note: AST should have been processed by NameResolver before calling this
     *
     * @param array<Node\Stmt> $stmts
     */
    private function extractReturnTypeFromAst(array $stmts, string $methodName): ?string
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Namespace_) {
                // Find method in namespace
                foreach ($stmt->stmts as $subStmt) {
                    $returnType = $this->findMethodReturnType($subStmt, $methodName);
                    if ($returnType !== null) {
                        return $returnType;
                    }
                }
            } else {
                $returnType = $this->findMethodReturnType($stmt, $methodName);
                if ($returnType !== null) {
                    return $returnType;
                }
            }
        }

        return null;
    }

    /**
     * Find method return type in a class/interface statement
     * Assumes AST was processed by NameResolver, so namespacedName attribute is available
     */
    private function findMethodReturnType(Node\Stmt $stmt, string $methodName): ?string
    {
        if (!($stmt instanceof Node\Stmt\Class_) && !($stmt instanceof Node\Stmt\Interface_)) {
            return null;
        }

        foreach ($stmt->stmts as $classStmt) {
            if ($classStmt instanceof Node\Stmt\ClassMethod && $classStmt->name->toString() === $methodName) {
                // Check for return type declaration
                if ($classStmt->returnType instanceof Node\Name) {
                    // Use namespacedName added by NameResolver, or toString() if already FullyQualified
                    return $classStmt->returnType->getAttribute('namespacedName')?->toString()
                        ?? $classStmt->returnType->toString();
                }
            }
        }

        return null;
    }

    private function collectStaticCall(Node\Expr\StaticCall $node): void
    {
        if ($node->name instanceof Node\Identifier) {
            $methodName = $node->name->toString();
            // Use namespacedName from NameResolver if available
            $className = null;
            if ($node->class instanceof Node\Name) {
                $className = $node->class->getAttribute('namespacedName')?->toString() ?? $node->class->toString();
            }

            $this->calls[] = [
                'type' => 'static_call',
                'class' => $className,
                'method' => $methodName,
                'line' => $node->getLine(),
            ];
        }
    }

    private function collectFunctionCall(Node\Expr\FuncCall $node): void
    {
        if ($node->name instanceof Node\Name) {
            $functionName = $node->name->toString();

            $this->calls[] = [
                'type' => 'function_call',
                'class' => null,
                'method' => $functionName,
                'line' => $node->getLine(),
            ];
        }
    }

    private function collectInstantiation(Node\Expr\New_ $node): void
    {
        // Use namespacedName from NameResolver if available
        $className = null;
        if ($node->class instanceof Node\Name) {
            $className = $node->class->getAttribute('namespacedName')?->toString() ?? $node->class->toString();
        }

        if ($className !== null) {
            $this->calls[] = [
                'type' => 'class_instantiation',
                'class' => $className,
                'method' => '__construct',
                'line' => $node->getLine(),
            ];
        }
    }

    private function collectInvokableCall(Node\Expr\FuncCall $node): void
    {
        // This handles calls like $variable(...) which are invokable objects
        if ($node->name instanceof Node\Expr\Variable && is_string($node->name->name)) {
            $variableName = $node->name->name;

            // Try to resolve the class from parameter types
            $className = $this->parameterTypes[$variableName] ?? null;

            $this->calls[] = [
                'type' => 'invokable_call',
                'class' => $className,
                'method' => '__invoke',
                'line' => $node->getLine(),
                'variable' => $variableName,
            ];
        }
    }

    private function extractParameterTypes(Node\Stmt\ClassMethod $method): void
    {
        $this->parameterTypes = [];

        foreach ($method->params as $param) {
            if ($param->type instanceof Node\Name && $param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                $variableName = $param->var->name;
                // Use namespacedName added by NameResolver, or toString() if already FullyQualified
                $namespacedName = $param->type->getAttribute('namespacedName');
                if ($namespacedName instanceof Node\Name) {
                    $typeName = $namespacedName->toString();
                } else {
                    $typeName = $param->type->toString();
                }

                $this->parameterTypes[$variableName] = $typeName;
            }
        }
    }

    /**
     * Collect parameter types as dependencies (without method calls)
     * This ensures DTO classes used as parameters are included in extraction
     */
    private function collectParameterTypeDependencies(Node\Stmt\ClassMethod $method): void
    {
        foreach ($method->params as $param) {
            if ($param->type instanceof Node\Name && $param->var instanceof Node\Expr\Variable && is_string($param->var->name)) {
                // Use namespacedName added by NameResolver, or toString() if already FullyQualified
                $namespacedName = $param->type->getAttribute('namespacedName');
                if ($namespacedName instanceof Node\Name) {
                    $typeName = $namespacedName->toString();
                } else {
                    $typeName = $param->type->toString();
                }

                // Skip primitive types and built-in classes
                if ($this->isPrimitiveOrBuiltinType($typeName)) {
                    continue;
                }

                // Add as a "type_dependency" to include the class definition
                $this->calls[] = [
                    'type' => 'parameter_type',
                    'class' => $typeName,
                    'method' => null, // No specific method needed
                    'line' => $param->getLine(),
                ];
            }
        }
    }

    /**
     * Check if a type is primitive or built-in PHP class
     */
    private function isPrimitiveOrBuiltinType(string $typeName): bool
    {
        $primitives = [
            'string', 'int', 'float', 'bool', 'array', 'object', 'mixed',
            'callable', 'iterable', 'void', 'never', 'null', 'false', 'true',
            'self', 'parent', 'static',
        ];

        // Check if it's a primitive
        if (in_array(strtolower($typeName), $primitives, true)) {
            return true;
        }

        // Check if it's a built-in PHP class (starts with backslash or no namespace)
        $builtinNamespaces = ['DateTime', 'DateTimeImmutable', 'Exception', 'Throwable', 'stdClass'];
        foreach ($builtinNamespaces as $builtin) {
            if ($typeName === $builtin || str_ends_with($typeName, '\\' . $builtin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<array{type: string, class: string|null, method: string|null, line: int}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * Filter calls to only those from a specific method
     *
     * @return array<array{type: string, class: string|null, method: string|null, line: int}>
     */
    public function getCallsFromMethod(string $className, string $methodName): array
    {
        $calls = [];
        $inTargetMethod = false;

        foreach ($this->calls as $call) {
            // Simple heuristic: if we tracked class/method context properly, use it
            // For now, we return all calls (this can be improved with better context tracking)
            $calls[] = $call;
        }

        return $calls;
    }

    private function resolveClassName(Node\Expr $expr): ?string
    {
        // Handle $this->property access
        if (
            $expr instanceof Node\Expr\PropertyFetch
            && $expr->var instanceof Node\Expr\Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Node\Identifier
        ) {
            $propertyName = $expr->name->toString();
            return $this->propertyTypes[$propertyName] ?? null;
        }

        // Handle variables from foreach loops or other tracked sources
        if ($expr instanceof Node\Expr\Variable && is_string($expr->name)) {
            $variableName = $expr->name;

            // Check if we tracked this variable's type (e.g., from foreach)
            if (isset($this->variableTypes[$variableName])) {
                return $this->variableTypes[$variableName];
            }

            // Common patterns like $this->service or $repository
            return null; // Cannot determine statically without type inference
        }

        return null;
    }
}
