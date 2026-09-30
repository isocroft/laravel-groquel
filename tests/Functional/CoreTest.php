<?php

namespace Groquel\Laravel\Tests\Functional;

use PHPUnit\Framework\TestCase;

use LaravelFaked\Core\Foundation\InteractsWithFakeLaravel;

class CoreTest extends TestCase {
    use InteractsWithFakeLaravel;
    
    protected function setUp(): void {
        parent::setUp(); // Always call the parent method
        
        $this->bootFakeLaravel(
            packageConfig: require __DIR__ . '/../fixtures/laravel-fake-config.php',
            request: require __DIR__ . '/../fixtures/laravel-fake-request.php',
        );
    }

    protected function tearDown(): void {
        $this->releaseFakeLaravel();
        parent::tearDown();
    }

    public function testClient(): void {
        $this->assertNull(NULL);
    }
}

?>
