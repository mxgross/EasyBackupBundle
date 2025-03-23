<?php

declare(strict_types=1);

namespace EasyBackupBundle\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class EasyBackupControllerTest extends WebTestCase
{
    public function testUnauthorizedUserIsRedirected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/easy-backup/');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testUnauthorizedUserCannotCreateBackup(): void
    {
        $client = static::createClient();
        $client->request('POST', '/admin/easy-backup/create_backup');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testUnauthorizedUserCannotDownloadBackup(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/easy-backup/download?backupFilename=fake.zip');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testUnauthorizedUserCannotAccessRestore(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/easy-backup/restore?backupFilename=fake.zip');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testUnauthorizedUserCannotAccessDelete(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/easy-backup/delete?backupFilename=fake.zip');

        $this->assertResponseStatusCodeSame(Response::HTTP_FOUND);
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }
}
