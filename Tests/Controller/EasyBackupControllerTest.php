<?php

declare(strict_types=1);

namespace KimaiPlugin\EasyBackupBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use App\Kernel;

class EasyBackupControllerTest extends WebTestCase
{

    protected function setUp(): void
    {
        putenv('KIMAI_DATA_DIR=/opt/kimai/var/data');
        $_ENV['KIMAI_DATA_DIR'] = '/opt/kimai/var/data';
        $_SERVER['KIMAI_DATA_DIR'] = '/opt/kimai/var/data';
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public static function createKernel(array $options = []): KernelInterface
    {
        $kernel = new Kernel('test', true);

        $_ENV['KIMAI_DATA_DIR'] = '/opt/kimai/var/data';
        $_SERVER['KIMAI_DATA_DIR'] = '/opt/kimai/var/data';

        return $kernel;
    }

    public function testUnauthorizedUserIsRedirected(): void
    {
        putenv('KIMAI_DATA_DIR=/opt/kimai/var/data');
        $_ENV['KIMAI_DATA_DIR'] = '/opt/kimai/var/data';
        $_SERVER['KIMAI_DATA_DIR'] = '/opt/kimai/var/data';
    
        $client = static::createClient();
        $client->request('GET', '/admin/easy-backup/');
    
        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }
    
}
