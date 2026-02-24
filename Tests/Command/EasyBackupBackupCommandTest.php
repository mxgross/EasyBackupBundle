<?php

namespace KimaiPlugin\EasyBackupBundle\Tests\Command;

use Symfony\Component\Console\Tester\CommandTester;

class EasyBackupBackupCommandTest extends \PHPUnit\Framework\TestCase
{
    public function testExecute()
    {
        // simple stub that satisfies the type-hint by extending the real service
        $serviceStub = new class extends \KimaiPlugin\EasyBackupBundle\Service\EasyBackupService {
            public function __construct() {}
            public function createBackup()
            {
                return "backup-log";
            }
        };

        $command = new \KimaiPlugin\EasyBackupBundle\Command\EasyBackupBackupCommand($serviceStub);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        $this->assertStringContainsString('backup-log', $commandTester->getDisplay());
    }
}
