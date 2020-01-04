<?php

/*
 * This file is part of the EasyBackupBundle for Kimai 2.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\Controller;

use App\Constants;
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
final class EasyBackupController extends AbstractController
{
    public const CMD_GIT_HEAD = 'git rev-parse HEAD';
    public const README_FILENAME = 'manifest.json';
    public const SQL_DUMP_FILENAME = 'database_dump.sql';
    public const REGEX_BACKUP_ZIP_NAME = '/^\d{4}-\d{2}-\d{2}_\d{6}\.zip$/';
    public const BACKUP_NAME_DATE_FORMAT = 'Y-m-d_His';

    /**
     * @var string
     */
    private $kimaiRootPath;

    /**
     * @var string
     */
    private $backupDirectory;

    /**
     * @var string
     */
    private $dbUrl;

    /**
     * @var string
     */
    private $filesystem;

    /**
     * @var EasyBackupConfiguration
     */
    private $configuration;

    public function __construct(string $dataDirectory, EasyBackupConfiguration $configuration)
    {
        $this->configuration = $configuration;

        $this->kimaiRootPath = dirname(dirname($dataDirectory)) . '/';
        $this->backupDirectory = $this->kimaiRootPath . $this->configuration->getBackupDir();

        $this->dbUrl = $_ENV['DATABASE_URL'];

        $this->filesystem = new Filesystem();
    }

    /**
     * @Route(path="", name="easy_backup", methods={"GET", "POST"})
     *
     * @return Response
     */
    public function indexAction(): Response
    {
        $existingBackups = [];

        $status = $this->checkStatus();

        if ($this->filesystem->exists($this->backupDirectory)) {
            $files = scandir($this->backupDirectory, SCANDIR_SORT_DESCENDING);
            $filesAndDirs = array_diff($files, ['.', '..', '.gitignore']);

            foreach ($filesAndDirs as $fileOrDir) {
                if (is_file($this->backupDirectory . $fileOrDir)) {
                    $filesizeInMb = round(filesize($this->backupDirectory . $fileOrDir) / 1048576, 2);
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
     *
     * @return Response
     */
    public function createBackupAction(): Response
    {
        // Don't use the /var/data folder, because we want to backup it too!

        $backupName = date(self::BACKUP_NAME_DATE_FORMAT);
        $pluginBackupDir = $this->backupDirectory . $backupName . '/';

        // Create the backup folder

        $this->filesystem->mkdir($pluginBackupDir);

        // If not yet existing, create a .gitignore to exclude the backup files.

        $gitignoreFullPath = $this->backupDirectory . '.gitignore';

        if (!$this->filesystem->exists($gitignoreFullPath)) {
            $this->filesystem->touch($gitignoreFullPath);
            $this->filesystem->appendToFile($gitignoreFullPath, '*');
        }

        // Save the specific kimai version and git head

        $readMeFile = $pluginBackupDir . self::README_FILENAME;
        $this->filesystem->touch($readMeFile);
        $manifest = [
            'git' => 'not available',
            'version' => $this->getKimaiVersion(),
            'software' => $this->getKimaiVersion(true),
        ];

        try {
            $process = new Process(self::CMD_GIT_HEAD);
            $process->run();
            $manifest['git'] = str_replace(PHP_EOL, '', $process->getOutput());
        } catch (\Exception $ex) {
            // ignore exception
        }
        $this->filesystem->appendToFile($readMeFile, json_encode($manifest, JSON_PRETTY_PRINT));

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

            if ($this->filesystem->exists($sourceFile)) {
                if (is_dir($sourceFile)) {
                    $this->filesystem->mirror($sourceFile, $targetFile);
                }

                if (is_file($sourceFile)) {
                    $this->filesystem->copy($sourceFile, $targetFile);
                }
            }
        }

        $sqlDumpName = $pluginBackupDir . self::SQL_DUMP_FILENAME;

        $this->backupDatabase($sqlDumpName);
        $backupZipName = $this->backupDirectory . $backupName . '.zip';

        $this->zipData($pluginBackupDir, $backupZipName);

        // Now the temporary files can be deleted

        $this->filesystem->remove($pluginBackupDir);
        $this->filesystem->remove($sqlDumpName);

        $this->flashSuccess('backup.action.create.success');

        return $this->redirectToRoute('easy_backup');
    }

    /**
     * @Route(path="/download", name="download", methods={"GET"})

     * @param Request $request
     * @return Response
     */
    public function downloadAction(Request $request): Response
    {
        $backupName = $request->query->get('dirname');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $backupName)) {
            $zipNameAbsolute = $this->backupDirectory . $backupName;

            if ($this->filesystem->exists($zipNameAbsolute)) {
                $response = new Response(file_get_contents($zipNameAbsolute));
                $d = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $backupName);
                $response->headers->set('Content-Disposition', $d);

                return $response;
            } else {
                $this->flashError('backup.action.download.error');
            }
        } else {
            $this->flashError('backup.action.download.error');
        }

        return $this->redirectToRoute('easy_backup');
    }

    /**
     * @Route(path="/delete", name="delete", methods={"GET"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request)
    {
        $dirname = $request->query->get('dirname');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $dirname)) {
            $path = $this->backupDirectory . $dirname;

            if ($this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
            }

            $this->flashSuccess('backup.action.delete.success');
        } else {
            $this->flashError('backup.action.delete.error.filename');
        }

        return $this->redirectToRoute('easy_backup', $request->query->all());
    }

    private function backupDatabase(string $sqlDumpName)
    {
        $dbUrlExploded = explode(':', $this->dbUrl);
        $dbUsed = $dbUrlExploded[0];

        // This is only for mysql and mariadb. sqlite will be backuped via the file backups

        if ($dbUsed === 'mysql') {
            $dbUser = str_replace('/', '', $dbUrlExploded[1]);
            $dbPwd = explode('@', $dbUrlExploded[2])[0];
            $dbName = explode('/', $dbUrlExploded[3])[1];

            $mysqlDumpCmd = $this->configuration->getMysqlDumpPath();
            $process = new Process("($mysqlDumpCmd --user=$dbUser --password=$dbPwd $dbName > $sqlDumpName)");
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }
    }

    private function zipData($source, $destination)
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
                } else {
                    $this->flashError('backup.action.zip.error.destination');
                }

                return $zip->close();
            } else {
                $this->flashError('backup.action.zip.error.source');
            }
        } else {
            $this->flashError('backup.action.zip.error.extension');
        }

        return false;
    }

    private function checkStatus()
    {
        $status = [];

        $path = $this->kimaiRootPath . 'var';
        $status["Path '$path' readable"] = is_readable($path);
        $status["Path '$path' writable"] = is_writable($path);
        $status["PHP extension 'zip' loaded"] = extension_loaded('zip');
        $status['Kimai version'] = $this->getKimaiVersion();

        $cmd = self::CMD_GIT_HEAD;
        $status[$cmd] = $this->processCmdAndGetResult($cmd);

        $cmd = $this->configuration->getMysqlDumpPath() . ' --version';
        $status[$cmd] = $this->processCmdAndGetResult($cmd);

        return $status;
    }

    private function getKimaiVersion(bool $full = false): string
    {
        if ($full) {
            return Constants::SOFTWARE . ' - ' . Constants::VERSION . ' ' . Constants::STATUS;
        }

        return Constants::VERSION . ' ' . Constants::STATUS;
    }

    private function processCmdAndGetResult($cmd)
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
