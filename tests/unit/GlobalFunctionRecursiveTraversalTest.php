<?php

declare(strict_types=1);

namespace Codeviastudio\FeatureTraverser\Tests;

use Codeviastudio\FeatureTraverser\Analyzer\AstAnalyzer;
use PHPUnit\Framework\TestCase;

final class GlobalFunctionRecursiveTraversalTest extends TestCase
{
    public function testAnalyzerDetectsFunctionCallsInGlobalFunctions(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/functions.php';
        $analyzer = new AstAnalyzer();

        $result = $analyzer->analyzeFile($filePath);

        $functionCalls = array_filter($result, fn($call) => $call['type'] === 'function_call');
        self::assertNotEmpty($functionCalls);

        $functionNames = array_column($functionCalls, 'method');
        self::assertContains('anotherGlobalFunction', $functionNames);
    }

    public function testGlobalFunctionCallsAreDetectedWithinFunctions(): void
    {
        $filePath = __DIR__ . '/../resources/fixtures/functions.php';
        $analyzer = new AstAnalyzer();

        $result = $analyzer->analyzeFile($filePath);

        foreach ($result as $call) {
            if ($call['method'] === 'anotherGlobalFunction') {
                self::assertSame('function_call', $call['type']);
                self::assertNull($call['class']);
                return;
            }
        }

        self::fail('Expected to find anotherGlobalFunction call');
    }

    public function testWordPressStyleFunctionUsage(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php

namespace MyPlugin;

function my_plugin_init() {
    add_action("init", "MyPlugin\\register_post_types");
    wp_enqueue_script("my-script");
}

function register_post_types() {
    register_post_type("my_post_type");
}
');

        $analyzer = new AstAnalyzer();
        $result = $analyzer->analyzeFile($tempFile);

        $functionCalls = array_filter($result, fn($call) => $call['type'] === 'function_call');
        self::assertNotEmpty($functionCalls);

        $functionNames = array_column($functionCalls, 'method');
        self::assertContains('add_action', $functionNames);
        self::assertContains('wp_enqueue_script', $functionNames);
        self::assertContains('register_post_type', $functionNames);

        unlink($tempFile);
    }

    public function testMixedClassAndFunctionCalls(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, '<?php

namespace App;

class MyClass {
    public function process() {
        $data = get_user_data();

        $repository = new UserRepository();
        $repository->save($data);

        send_notification();
    }
}
');

        $analyzer = new AstAnalyzer();
        $result = $analyzer->analyzeMethod($tempFile, 'App\\MyClass', 'process');

        $functionCalls = array_filter($result, fn($call) => $call['type'] === 'function_call');
        $classCalls = array_filter($result, fn($call) => $call['type'] === 'class_instantiation' || $call['type'] === 'method_call');

        self::assertNotEmpty($functionCalls);
        self::assertNotEmpty($classCalls);

        $functionNames = array_column($functionCalls, 'method');
        self::assertContains('get_user_data', $functionNames);
        self::assertContains('send_notification', $functionNames);

        unlink($tempFile);
    }
}
