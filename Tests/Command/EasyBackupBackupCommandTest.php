<?php

declare(strict_types=1);

namespace KimaiPlugin\EasyBackupBundle\Tests\Command;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Bundle\FrameworkBundle\Console\Application;

class EasyBackupBackupCommandTest extends TestCase
{
    public function testExecute(): void
    {
        putenv('KIMAI_DATA_DIR=/opt/kimai/var/data');
        $_ENV['KIMAI_DATA_DIR'] = '/opt/kimai/var/data';
        $_SERVER['KIMAI_DATA_DIR'] = '/opt/kimai/var/data';
    
        $kernel = new Kernel('test', true);
        $kernel->boot();
    
        $application = new Application($kernel);
        $command = $application->find('easy-backup:create');
    
        $tester = new CommandTester($command);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
    }
}
