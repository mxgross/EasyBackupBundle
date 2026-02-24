<?php

namespace KimaiPlugin\EasyBackupBundle\Tests\Configuration;

use KimaiPlugin\EasyBackupBundle\Configuration\EasyBackupConfiguration;
use PHPUnit\Framework\TestCase;

class EasyBackupConfigurationTest extends TestCase
{
    public function testGettersReturnConfiguredValues()
    {
        $stub = new class {
            private $map = [
                'easy_backup.setting_mysqldump_command' => '/usr/bin/mysqldump --user={user}',
                'easy_backup.setting_mysql_restore_command' => '/usr/bin/mysql --user={user} < {sql_file}',
                'easy_backup.setting_backup_dir' => 'var/easy_backup/',
                'easy_backup.setting_paths_to_backup' => ".env\nvar/data/",
                'easy_backup.setting_backup_amount_max' => '5',
            ];

            public function find(string $key)
            {
                return $this->map[$key] ?? null;
            }
        };

        $ref = new \ReflectionClass(EasyBackupConfiguration::class);
        $cfg = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('configuration');
        $prop->setAccessible(true);
        $prop->setValue($cfg, $stub);

        $this->assertSame('/usr/bin/mysqldump --user={user}', $cfg->getMysqlDumpCommand());
        $this->assertSame('/usr/bin/mysql --user={user} < {sql_file}', $cfg->getMysqlRestoreCommand());
        $this->assertSame('var/easy_backup/', $cfg->getBackupDir());
        $this->assertStringContainsString('.env', $cfg->getPathsToBeBackuped());
        $this->assertSame(5, $cfg->getBackupAmountMax());
    }

    public function testGettersReturnFallbacksOnNonString()
    {
        $stub = new class {
            public function find(string $key)
            {
                return null;
            }
        };

        $ref = new \ReflectionClass(EasyBackupConfiguration::class);
        $cfg = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('configuration');
        $prop->setAccessible(true);
        $prop->setValue($cfg, $stub);

        $this->assertSame('NOT SET', $cfg->getMysqlDumpCommand());
        $this->assertSame('NOT SET', $cfg->getMysqlRestoreCommand());
        $this->assertSame('NOT SET', $cfg->getBackupDir());
        $this->assertSame('NOT SET', $cfg->getPathsToBeBackuped());
        $this->assertSame(-1, $cfg->getBackupAmountMax());
    }
}
