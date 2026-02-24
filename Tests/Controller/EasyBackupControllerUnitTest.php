<?php

namespace KimaiPlugin\EasyBackupBundle\Tests\Controller;

use KimaiPlugin\EasyBackupBundle\Controller\EasyBackupController;
use KimaiPlugin\EasyBackupBundle\Configuration\EasyBackupConfiguration;
use KimaiPlugin\EasyBackupBundle\Service\EasyBackupService;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class EasyBackupControllerUnitTest extends TestCase
{
    public function testIndexActionReturnsRenderedResponse()
    {
        $tmp = sys_get_temp_dir() . '/kimai_unit_' . uniqid();
        mkdir($tmp . '/var/data', 0777, true);

        $stubCfgService = new class {
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
        $prop->setValue($cfg, $stubCfgService);

        $serviceStub = new class extends \KimaiPlugin\EasyBackupBundle\Service\EasyBackupService {
            public function __construct() {}
            public function getExistingBackups(): array
            {
                return [['name' => 'a.zip', 'size' => 1, 'filemtime' => time()]];
            }
        };

        $controller = new EasyBackupController($tmp . '/var/data', $cfg, $serviceStub);

        $container = new class implements ContainerInterface {
            private $services = [];
            public function set(string $id, $service): void { $this->services[$id] = $service; }
            public function get(string $id): mixed { return $this->services[$id] ?? null; }
            public function has(string $id): bool { return isset($this->services[$id]); }
            public function getSession(): mixed { return $this->services['session'] ?? null; }
        };

        $container->set('security.authorization_checker', new class {
            public function isGranted($attr) { return true; }
        });

        $container->set('twig', new class {
            public function render($template, $params = []) { return 'RENDERED:' . json_encode($params); }
        });

        $container->set('session', new class { public function getFlashBag(){ return new class { public function add($t,$m){} }; } });

        $controller->setContainer($container);

        $response = $controller->indexAction();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('RENDERED:', $response->getContent());

        // cleanup
        @rmdir($tmp . '/var/data');
        @rmdir($tmp . '/var');
        @rmdir($tmp);
    }

    public function testCreateBackupActionRedirects()
    {
        $tmp = sys_get_temp_dir() . '/kimai_unit_' . uniqid();
        mkdir($tmp . '/var/data', 0777, true);

        $stubCfgService = new class {
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
        $prop->setValue($cfg, $stubCfgService);

        $serviceStub = new class extends \KimaiPlugin\EasyBackupBundle\Service\EasyBackupService {
            public function __construct() {}
            public function createBackup()
            {
                return 'ok';
            }
        };

        $controller = new EasyBackupController($tmp . '/var/data', $cfg, $serviceStub);

        $container = new class implements ContainerInterface {
            private $services = [];
            public function set(string $id, $service): void { $this->services[$id] = $service; }
            public function get(string $id): mixed { return $this->services[$id] ?? null; }
            public function has(string $id): bool { return isset($this->services[$id]); }
            public function getSession(): mixed { return $this->services['session'] ?? null; }
        };

        $container->set('security.authorization_checker', new class { public function isGranted($a){return true;}});
        $container->set('session', new class { public function getFlashBag(){return new class { public function add($t,$m){} };}});
        $container->set('router', new class { public function generate($name, $params=[]){ return '/admin/easy-backup/'; }});
        $container->set('request_stack', new class {
            public function getCurrentRequest(){ return new \Symfony\Component\HttpFoundation\Request(); }
            public function getSession(){
                return new class {
                    public function getFlashBag(){ return new class { public function add($t,$m){} }; }
                };
            }
        });

        $controller->setContainer($container);

        $response = $controller->createBackupAction();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('/admin/easy-backup', $response->getTargetUrl());

        // cleanup
        @rmdir($tmp . '/var/data');
        @rmdir($tmp . '/var');
        @rmdir($tmp);
    }

    public function testDownloadActionReturnsFileResponse()
    {
        $tmp = sys_get_temp_dir() . '/kimai_unit_' . uniqid();
        mkdir($tmp . '/var/easy_backup', 0777, true);
        $filename = '2026-02-24_120000.zip';
        file_put_contents($tmp . '/var/easy_backup/' . $filename, 'ZIPDATA');

        $stubCfgService = new class {
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
        $prop->setValue($cfg, $stubCfgService);

        $serviceStub = new class extends \KimaiPlugin\EasyBackupBundle\Service\EasyBackupService {
            public function __construct() {}
        };

        $controller = new EasyBackupController($tmp . '/var/data', $cfg, $serviceStub);
        $container = new class implements ContainerInterface {
            private $services = [];
            public function set(string $id, $service): void { $this->services[$id] = $service; }
            public function get(string $id): mixed { return $this->services[$id] ?? null; }
            public function has(string $id): bool { return isset($this->services[$id]); }
            public function getSession(): mixed { return $this->services['session'] ?? null; }
        };

        $container->set('security.authorization_checker', new class { public function isGranted($a){return true;}});
        $container->set('session', new class { public function getFlashBag(){return new class { public function add($t,$m){} };}});
        $controller->setContainer($container);

        $request = new Request(['backupFilename' => $filename]);

        $response = $controller->downloadAction($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertStringContainsString('ZIPDATA', $response->getContent());
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));

        // cleanup
        @unlink($tmp . '/var/easy_backup/' . $filename);
        @rmdir($tmp . '/var/easy_backup');
        @rmdir($tmp . '/var');
        @rmdir($tmp);
    }
}
