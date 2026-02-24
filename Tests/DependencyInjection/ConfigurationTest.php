<?php

namespace KimaiPlugin\EasyBackupBundle\Tests\DependencyInjection;

use KimaiPlugin\EasyBackupBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaultValuesAreSet()
    {
        $configuration = new Configuration();
        $processor = new Processor();

        $config = $processor->processConfiguration($configuration, []);

        $this->assertArrayHasKey('setting_mysqldump_command', $config);
        $this->assertArrayHasKey('setting_mysql_restore_command', $config);
        $this->assertArrayHasKey('setting_backup_dir', $config);
        $this->assertArrayHasKey('setting_paths_to_backup', $config);

        $this->assertStringContainsString('mysqldump', $config['setting_mysqldump_command']);
        $this->assertSame('var/easy_backup/', $config['setting_backup_dir']);
    }
}
