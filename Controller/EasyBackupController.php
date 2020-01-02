<?php

/*
 * This file is part of the EasyBackupBundle for Kimai 2.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\EasyBackupBundle\Configuration\EasyBackupConfiguration;
use PhpOffice\PhpWord\Shared\ZipArchive;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/admin/easy-backup")
 * @Security("is_granted('easy_backup')")
 */
class EasyBackupController extends AbstractController
{
    public const   CMD_GIT_HEAD = 'git rev-parse HEAD';

    public const   README_FILENAME = 'Readme.txt';

    public const   SQL_DUMP_FILENAME = 'database_dump.sql';

    public const   CMD_KIMAI_VERSION = '/bin/console kimai:version';

    /**
     * @var string
     */
    protected $kimaiRootPath;

    /**
     * @var string
     */
    protected $backupDirectory;

    /**
     * @var string
     */
    protected $dbUrl;

    /**
     * @var EasyBackupConfiguration
     */
    protected $configuration;

    public function __construct(string $dataDirectory, EasyBackupConfiguration $configuration)
    {
        $this->configuration = $configuration;

        $this->kimaiRootPath = dirname(dirname($dataDirectory)) . '/';
        $this->backupDirectory = $this->kimaiRootPath . $this->configuration->getBackupDir(); //'var/easy_backup/';

        $this->dbUrl = $_ENV['DATABASE_URL'];
    }

    /**
     * @Route(path="", name="easy_backup", methods={"GET", "POST"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $existingBackups = [];
        $filesystem = new Filesystem();

        $status = $this->checkStatus();

        if ($filesystem->exists($this->backupDirectory)) {
            $files = scandir($this->backupDirectory, SCANDIR_SORT_DESCENDING);
            $filesAndDirs = array_diff($files, ['.', '..']);

            foreach ($filesAndDirs as $fileOrDir) {
                if (is_file($this->backupDirectory . $fileOrDir)) {
                    $filesizeInMb = round(filesize($this->backupDirectory . $fileOrDir) / 1048576, 2);
                    //array_push($existingBackups, $fileOrDir . ' ('. $filesizeInMb . 'MB)');

                    $existingBackups[$fileOrDir] = $filesizeInMb;
                }
            }
        }

        return $this->render('@EasyBackup/index.html.twig', [
            'existingBackups' => $existingBackups,
            'status' => $status,
        ]);
    }

    /**
     * @Route(path="/create_backup", name="create_backup", methods={"GET", "POST"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function createBackupAction(Request $request)
    {
        // Don't use the /var/data folder, because we want to backup it too!

        $backupName = date('Y-m-d_His');
        $pluginBackupDir = $this->backupDirectory . $backupName . '/';
        $filesystem = new Filesystem();

        // Create the backup folder

        $filesystem->mkdir($pluginBackupDir);

        // Save the specific kimai version and git head

        $readMeFile = $pluginBackupDir . self::README_FILENAME;
        $filesystem->touch($readMeFile);

        $process = new Process(self::CMD_GIT_HEAD);
        $process->run();
        $filesystem->appendToFile($readMeFile, self::CMD_GIT_HEAD);
        $filesystem->appendToFile($readMeFile, "\r\n");
        $filesystem->appendToFile($readMeFile, $process->getOutput());
        $filesystem->appendToFile($readMeFile, "\r\n");

        $process = new Process($this->kimaiRootPath . self::CMD_KIMAI_VERSION);
        $process->run();
        $filesystem->appendToFile($readMeFile, self::CMD_KIMAI_VERSION);
        $filesystem->appendToFile($readMeFile, "\r\n");
        $filesystem->appendToFile($readMeFile, $process->getOutput());
        $filesystem->appendToFile($readMeFile, "\r\n");

        // Backing up files and directories

        $arrayOfPathsToBackup = [
            '.env',
            'config/packages/local.yaml',
            'var/data/',
            'var/plugins/',
        ];

        foreach ($arrayOfPathsToBackup as $filename) {
            $sourceFile = $this->kimaiRootPath . $filename;
            $targetFile = $pluginBackupDir . $filename;

            if ($filesystem->exists($sourceFile)) {
                if (is_dir($sourceFile)) {
                    $filesystem->mirror($sourceFile, $targetFile);
                }

                if (is_file($sourceFile)) {
                    $filesystem->copy($sourceFile, $targetFile);
                }
            }
        }

        $this->backupDatabase();

        $this->zipData($pluginBackupDir, $this->backupDirectory . $backupName . '.zip');

        // Now the folder can be deleted
        $filesystem->remove($pluginBackupDir);

        $this->addFlash('success', 'Backup created.');

        return $this->redirectToRoute('easy_backup', $request->query->all());
    }

    /**
     * @Route(path="/download", name="download", methods={"GET"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function downloadAction(Request $request)
    {
        $filesystem = new Filesystem();

        $backupName = $request->query->get('dirname');
        $zipNameAbsolute = $this->backupDirectory . $backupName;

        if ($filesystem->exists($zipNameAbsolute)) {
            $response = new Response(file_get_contents($zipNameAbsolute));
            $d = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $backupName);
            $response->headers->set('Content-Disposition', $d);

            return $response;
        }

        return $this->redirectToRoute('easy_backup', $request->query->all());
    }

    /**
     * @Route(path="/delete", name="delete", methods={"GET"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request)
    {
        $filesystem = new Filesystem();

        $dirname = $request->query->get('dirname');
        $path = $this->backupDirectory . $dirname;

        if ($filesystem->exists($path)) {
            $filesystem->remove($path);
        }

        $this->addFlash('success', 'Backup deleted.');

        return $this->redirectToRoute('easy_backup', $request->query->all());
    }

    protected function backupDatabase()
    {
        $dbUrlExploded = explode(':', $this->dbUrl);
        $dbUsed = $dbUrlExploded[0];

        // This is only for mysql and mariadb. sqlite will be backuped via the file backups
        if ($dbUsed === 'mysql') {
            $dbUser = str_replace('/', '', $dbUrlExploded[1]);
            $dbPwd = explode('@', $dbUrlExploded[2])[0];
            $dbName = explode('/', $dbUrlExploded[3])[1];

            $sqlDumpName = $this->backupDirectory . self::SQL_DUMP_FILENAME;

            $mysqlDumpCmd = $this->configuration->getMysqlDumpPath();
            $process = new Process("($mysqlDumpCmd --user=$dbUser --password=$dbPwd $dbName > $sqlDumpName)");
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }
    }

    protected function zipData($source, $destination)
    {
        if (extension_loaded('zip') === true) {
            if (file_exists($source) === true) {
                $zip = new ZipArchive();
                if ($zip->open($destination, ZIPARCHIVE::CREATE) === true) {
                    $source = realpath($source);
                    if (is_dir($source) === true) {
                        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);
                        foreach ($files as $file) {
                            $file = realpath($file);
                            if (is_dir($file) === true) {
                                $zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
                            } elseif (is_file($file) === true) {
                                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                            }
                        }
                    } elseif (is_file($source) === true) {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                }

                return $zip->close();
            }
        }

        return false;
    }

    protected function checkStatus()
    {
        $status = [];

        // Check
        $path = $this->kimaiRootPath . 'var';
        $status["is_readable $path"] = is_readable($path);
        $status["is_writable $path"] = is_writable($path);

        $cmd = $this->kimaiRootPath . self::CMD_KIMAI_VERSION;
        $status[$cmd] = $this->processCmdAndGetResult($cmd);

        $cmd = self::CMD_GIT_HEAD;
        $status[$cmd] = $this->processCmdAndGetResult($cmd);

        $cmd = $this->configuration->getMysqlDumpPath() . ' --version';
        $status[$cmd] = $this->processCmdAndGetResult($cmd);

        return $status;
    }

    protected function processCmdAndGetResult($cmd)
    {
        $process = new Process($cmd);
        $process->run();

        if ($process->isSuccessful()) {
            return $process->getOutput();
        } else {
            return $process->isSuccessful();
        }
    }
}
