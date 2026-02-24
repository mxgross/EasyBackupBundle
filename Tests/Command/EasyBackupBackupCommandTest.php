<?php

declare(strict_types=1);

namespace KimaiPlugin\EasyBackupBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class EasyBackupBackupCommandTest extends TestCase
{
    public function testExecute(): void
    {
        $serviceStub = new class extends \KimaiPlugin\EasyBackupBundle\Service\EasyBackupService {
            public function __construct() {}
            public function createBackup()
            {
                return 'backup-log';
            }
        };

        $command = new \KimaiPlugin\EasyBackupBundle\Command\EasyBackupBackupCommand($serviceStub);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('backup-log', $tester->getDisplay());
    }
}
