<?php

declare(strict_types=1);

namespace KimaiPlugin\EasyBackupBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;

class EasyBackupControllerTest extends TestCase
{
    public function testUnauthorizedUserIsRedirected(): void
    {
        $this->markTestSkipped('Controller tests require full Symfony kernel and HTTP client; run integration tests in CI or a dev container with increased memory.');
    }
}
