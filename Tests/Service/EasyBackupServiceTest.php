<?php

namespace KimaiPlugin\EasyBackupBundle\Tests\Service;

use KimaiPlugin\EasyBackupBundle\Service\EasyBackupService;
use KimaiPlugin\EasyBackupBundle\Configuration\EasyBackupConfiguration;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class EasyBackupServiceTest extends TestCase
{
    public function testGetBackupDirectory()
    {
        $root = vfsStream::setup('root', null, [
            'kimai' => [
                'var' => [
                    'data' => []
                ]
            ]
        ]);

        $dataDir = $root->url() . '/kimai/var/data';

        $stub = new class {
            public function find(string $key)
            {
                $map = ['easy_backup.setting_backup_dir' => 'var/easy_backup/'];
                return $map[$key] ?? null;
            }
        };

        $ref = new \ReflectionClass(EasyBackupConfiguration::class);
        $cfg = $ref->newInstanceWithoutConstructor();
        $prop = $ref->getProperty('configuration');
        $prop->setAccessible(true);
        $prop->setValue($cfg, $stub);

        $service = new EasyBackupService($dataDir, $cfg);

        $backupDir = $service->getBackupDirectory();

        $this->assertStringContainsString('var' . DIRECTORY_SEPARATOR . 'easy_backup', $backupDir);
    }

    public function testGetFilesInDirRecursively()
    {
        $tmp = sys_get_temp_dir() . '/easybackup_test_' . uniqid();
        mkdir($tmp . '/dir/sub', 0777, true);
        file_put_contents($tmp . '/dir/sub/file1.txt', 'hello');
        file_put_contents($tmp . '/dir/file2.txt', 'world');

        $stub2 = new class {
            public function find(string $key)
            {
                $map = ['easy_backup.setting_backup_dir' => ''];
                return $map[$key] ?? null;
            }
        };

        $ref2 = new \ReflectionClass(EasyBackupConfiguration::class);
        $cfg2 = $ref2->newInstanceWithoutConstructor();
        $prop2 = $ref2->getProperty('configuration');
        $prop2->setAccessible(true);
        $prop2->setValue($cfg2, $stub2);

        $service = new EasyBackupService($tmp . '/dir/..', $cfg2);

        $files = $service->getFilesInDirRecursively($tmp . '/dir');

        $this->assertNotEmpty($files);
        $found = false;
        foreach ($files as $f) {
            if (str_ends_with($f, 'file1.txt')) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Expected to find file1.txt in recursive file list');

        // Cleanup
        @unlink($tmp . '/dir/sub/file1.txt');
        @unlink($tmp . '/dir/file2.txt');
        @rmdir($tmp . '/dir/sub');
        @rmdir($tmp . '/dir');
    }

    public function testGetExistingBackups()
    {
        $root = vfsStream::setup('root', null, [
            'kimai' => [
                'var' => [
                    'data' => [],
                    'easy_backup' => [
                        '2026-02-24_120000.zip' => 'zipcontent',
                        'not_a_backup.txt' => 'x'
                    ]
                ]
            ]
        ]);

        $dataDir = $root->url() . '/kimai/var/data';

        $stub3 = new class {
            public function find(string $key)
            {
                $map = ['easy_backup.setting_backup_dir' => 'var/easy_backup/'];
                return $map[$key] ?? null;
            }
        };

        $ref3 = new \ReflectionClass(EasyBackupConfiguration::class);
        $cfg3 = $ref3->newInstanceWithoutConstructor();
        $prop3 = $ref3->getProperty('configuration');
        $prop3->setAccessible(true);
        $prop3->setValue($cfg3, $stub3);

        $service = new EasyBackupService($dataDir, $cfg3);

        $backups = $service->getExistingBackups();

        $this->assertNotEmpty($backups);
        $this->assertSame('2026-02-24_120000.zip', $backups[0]['name']);
    }
}
