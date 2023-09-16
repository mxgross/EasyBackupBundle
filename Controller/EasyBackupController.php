<?php

/*
 * This file is part of the EasyBackupBundle.
 * All rights reserved by Maximilian Groß (www.maximiliangross.de).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\EasyBackupBundle\Configuration\EasyBackupConfiguration;
use KimaiPlugin\EasyBackupBundle\Service\EasyBackupService;
use PhpOffice\PhpWord\Shared\ZipArchive;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\RuntimeException;

#[IsGranted('easy_backup')]
#[Route('/admin/easy-backup')]
final class EasyBackupController extends AbstractController
{
    public const CMD_GIT_HEAD = 'git rev-parse HEAD';
    public const MANIFEST_FILENAME = 'manifest.json';
    public const SQL_DUMP_FILENAME = 'database_dump.sql';
    public const REGEX_BACKUP_ZIP_NAME = '/^\d{4}-\d{2}-\d{2}_\d{6}\.zip$/';
    public const BACKUP_NAME_DATE_FORMAT = 'Y-m-d_His';
    public const GITIGNORE_NAME = '.gitignore';
    public const LOG_FILE_NAME = 'easybackup.log';
    public const LOG_ERROR_PREFIX = 'ERROR';
    public const LOG_WARN_PREFIX = 'WARNING';
    public const LOG_INFO_PREFIX = 'INFO';

    /**
     * @var string
     */
    private $kimaiRootPath;

    /**
     * @var EasyBackupConfiguration
     */
    private $configuration;

    /**
     * @var string
     */
    private $dbUrl;

    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var EasyBackupService
     */
    private $easyBackupService;

    public function __construct(string $dataDirectory, EasyBackupConfiguration $configuration, EasyBackupService $easyBackupService)
    {
        $this->kimaiRootPath = \dirname(\dirname($dataDirectory)) . DIRECTORY_SEPARATOR;
        $this->configuration = $configuration;
        $this->dbUrl = $_SERVER['DATABASE_URL'];
        $this->filesystem = new Filesystem();
        $this->easyBackupService = $easyBackupService;
    }

    private function log(string $logLevel, string $message): void
    {
        $backupDir = $this->getBackupDirectory();
        $logFile = $backupDir . self::LOG_FILE_NAME;

        try {
            if (!file_exists($logFile)) {
                $this->filesystem->touch($logFile);
            }

            $dateTime = date('Y-m-d H:i:s');
            $this->filesystem->appendToFile($logFile, "[$dateTime] $logLevel: $message" . PHP_EOL);
        } catch (\Exception $e) {
            $this->flashError('filesystem.mkdir.error.backupDir');
        }
    }

    private function getBackupDirectory(): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->kimaiRootPath . $this->configuration->getBackupDir());
    }

    /**
     * @return Response
     */
    #[Route('/', name: 'easy_backup', methods: ['GET', 'POST'])]
    public function indexAction(): Response
    {
        $backupDir = $this->getBackupDirectory();

        if (!file_exists($backupDir)) {
            $this->filesystem->mkdir($backupDir);
        }

        $status = $this->checkStatus();
        $existingBackups = $this->easyBackupService->getExistingBackups();

        $logFile = $backupDir . self::LOG_FILE_NAME;
        $log = file_exists($logFile) ? file_get_contents($logFile) : 'empty';

        return $this->render('@EasyBackup/index.html.twig', [
            'existingBackups' => $existingBackups,
            '_status' => $status,
            'log' => $log,
        ]);
    }

    /**
     * @return Response
     */
    #[Route('/create_backup', name: 'create_backup', methods: ['GET', 'POST'])]
    public function createBackupAction(): Response
    {
        try {
            $this->easyBackupService->createBackup();
        } catch (RuntimeException $e) {
            $this->flashError($e->getMessage());
        } finally {
            $this->flashSuccess('backup.action.create.success');

            return $this->redirectToRoute('easy_backup');
        }
    }

    /**
     * @return Response
     */
    #[Route('/download', name: 'download', methods: ['GET'])]
    public function downloadAction(Request $request): Response
    {
        $backupName = $request->query->get('backupFilename');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $backupName)) {
            $zipNameAbsolute = $this->getBackupDirectory() . $backupName;

            if ($this->filesystem->exists($zipNameAbsolute)) {
                $response = new Response(file_get_contents($zipNameAbsolute));
                $d = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $backupName);
                $response->headers->set('Content-Disposition', $d);

                return $response;
            } else {
                $this->flashError('backup.action.filename.error');
            }
        } else {
            $this->flashError('backup.action.filename.error');
        }

        return $this->redirectToRoute('easy_backup');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/restore', name: 'restore', methods: ['GET'])]
    public function restoreAction(Request $request)
    {
        // Clear old log file
        $logFile = $this->getBackupDirectory() . self::LOG_FILE_NAME;
        $this->filesystem->remove($logFile);

        $this->log(self::LOG_INFO_PREFIX, '--- S T A R T   R E S T O R E ---');

        $backupName = $request->query->get('backupFilename');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $backupName)) {
            // Prepare paths for windows and unix system as well.

            $zipNameAbsolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBackupDirectory() . $backupName);
            $backupName = basename($zipNameAbsolute, '.zip'); // e.g. 2020-11-02_174452
            $restoreDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBackupDirectory());
            $restoreDir = $restoreDir . $backupName . DIRECTORY_SEPARATOR; // e.g. .../kimai2/var/easy_backup/2020-11-02_174452

            $this->unzip($zipNameAbsolute, $restoreDir);
            $this->restoreMySQLDump($restoreDir);
            $this->restoreDirsAndFiles($restoreDir);

            // Cleanup the extracted backup folder
            $this->log(self::LOG_INFO_PREFIX, "Remove temp dir '$restoreDir'.");
            $this->filesystem->remove($restoreDir);
        } else {
            $this->flashError('backup.action.filename.error');
            $this->log(self::LOG_ERROR_PREFIX, "Backup '$backupName' not found.");
        }

        return $this->redirectToRoute('easy_backup');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/prepareRecovery', name: 'prepareRecovery', methods: 'GET')]
    public function prepareRecoveryAction(Request $request)
    {
        $backupName = $request->query->get('backupFilename');
        $fileOverwrites = [];

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $backupName)) {
            // Prepare paths for windows and unix system as well.

            $zipNameAbsolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBackupDirectory() . $backupName);
            $backupName = basename($zipNameAbsolute, '.zip'); // e.g. 2020-11-02_174452
            $restoreDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBackupDirectory());
            $restoreDir = $restoreDir . $backupName . DIRECTORY_SEPARATOR; // e.g. .../kimai2/var/easy_backup/2020-11-02_174452

            $this->unzip($zipNameAbsolute, $restoreDir);

            $fileOverwrites = $this->getFilesInDirRecursively($restoreDir);
            $fileOverwrites = array_filter(str_replace($restoreDir, '', $fileOverwrites));

            // Cleanup the extracted backup folder
            $this->filesystem->remove($restoreDir);
        } else {
            $this->flashError('backup.action.filename.error');
        }

        return $this->render('@EasyBackup/prepairRecovery.html.twig', [
            'fileOverwrites' => $fileOverwrites,
        ]);
    }

    private function getFilesInDirRecursively(string $dir, array &$resultFileList = []): array
    {
        $files = scandir($dir);

        foreach ($files as $fileOrDir) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $fileOrDir);
            if (!empty($path) && !is_dir($path)) {
                $resultFileList[] = $path;
            } elseif (!\in_array($fileOrDir, ['.', '..', '.git'])) {
                $this->getFilesInDirRecursively($path, $resultFileList);
            }
        }

        return $resultFileList;
    }

    private function restoreDirsAndFiles(string $restoreDir): void
    {
        $this->log(self::LOG_INFO_PREFIX, 'Start restoring files and dirs.');

        // Blacklist for files we don't want to move anywere else.

        $blacklist = [self::SQL_DUMP_FILENAME, self::MANIFEST_FILENAME];
        $this->log(self::LOG_INFO_PREFIX, 'Blacklist of files not to move: ' . implode(', ', $blacklist));

        $filePathsToRestore = $this->getFilesInDirRecursively($restoreDir);

        foreach ($filePathsToRestore as $filenameAbs) {
            $filenameOnlyArr = explode(DIRECTORY_SEPARATOR, $filenameAbs);
            $filenameOnly = end($filenameOnlyArr);

            // Some files in the backup dir are for internal usage, we don't want to move them anywere else.

            if (\in_array($filenameOnly, $blacklist)) {
                continue;
            }

            $filenameAbsNew = str_replace($restoreDir, $this->kimaiRootPath, $filenameAbs);
            $filepermsSource = substr(sprintf('%o', fileperms($filenameAbs)), -4);
            $filepermsDestination = substr(sprintf('%o', fileperms($filenameAbsNew)), -4);
            $this->log(self::LOG_INFO_PREFIX, "Copying '$filenameAbs' (Permissions: $filepermsSource) to '$filenameAbsNew' (Permissions: $filepermsDestination).");

            // Try to move the file.

            if (!is_writable($filenameAbsNew)) {
                $this->log(self::LOG_INFO_PREFIX, "'$filenameAbsNew' is not writable.");

                // If it isn't working try to change the file permissions.

                $newFilePermissions = 0777;
                $this->log(self::LOG_INFO_PREFIX, "Trying to change the file permissions of '$filenameAbsNew' to '$newFilePermissions' automatically.");
                if (chmod($filenameAbsNew, $newFilePermissions)) {
                    if (rename($filenameAbs, $filenameAbsNew)) {
                        $this->log(self::LOG_INFO_PREFIX, "Successfully copied '$filenameAbsNew' after file permissions were changed.");
                    } else {
                        $this->log(self::LOG_ERROR_PREFIX, "Unable to copy to '$filenameAbsNew'. Please check the file permissions and try it again.");
                    }
                } else {
                    $this->log(self::LOG_ERROR_PREFIX, "Failed to change permissions of '$filenameAbsNew' to '$newFilePermissions'.");
                }
            }
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route(path: '/delete', name: 'delete', methods: ['GET'])]
    public function deleteAction(Request $request): Response
    {
        $dirname = $request->query->get('backupFilename');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $dirname)) {
            $path = $this->getBackupDirectory() . $dirname;

            if ($this->filesystem->exists($path)) {
                $this->filesystem->remove($path);
            }

            $this->flashSuccess('backup.action.delete.success');
        } else {
            $this->flashError('backup.action.delete.error.filename');
        }

        return $this->redirectToRoute('easy_backup', $request->query->all());
    }

    private function unzip(string $source, string $destination): bool
    {
        $this->log(self::LOG_INFO_PREFIX, "Start unzipping '$source' to '$destination'.");

        if (\extension_loaded('zip') === true) {
            $zip = new ZipArchive();

            if (file_exists($source) === true
            && $zip->open($source) === true) {
                $this->filesystem->mkdir($destination);

                $this->log(self::LOG_INFO_PREFIX, "Extracting to '$destination'.");
                $zip->extractTo($destination);

                return $zip->close();
            } else {
                $this->flashError('backup.action.zip.error.source');
                $this->log(self::LOG_INFO_PREFIX, "File '$source' not found.");
            }
        } else {
            $this->flashError('backup.action.zip.error.extension');
            $this->log(self::LOG_ERROR_PREFIX, 'Extension ZIP not found.');
        }

        return false;
    }

    private function checkStatus(): array
    {
        $status = [];

        $path = $this->kimaiRootPath . 'var';
        $status[] = [
                'desc' => "Path '$path' readable",
                'status' => is_readable($path),
                'result' => '',
        ];

        $path = $this->kimaiRootPath . 'var';
        $status[] = [
            'desc' => "Path '$path' writable",
            'status' => is_writable($path),
            'result' => '',
        ];

        $path = $this->getBackupDirectory();
        $status[] = [
            'desc' => "Backup directory '$path' exists",
            'status' => is_writable($path),
            'result' => '',
        ];

        $status[] = [
            'desc' => "PHP extension 'zip' loaded",
            'status' => \extension_loaded('zip'),
            'result' => '',
        ];

        $status[] = [
            'desc' => 'Kimai version',
            'status' => true,
            'result' => $this->easyBackupService->getKimaiVersion(),
        ];

        // Todo: build path via config files instead of manually
        $dotGitPath = $this->kimaiRootPath . 'var' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'EasyBackupBundle' . DIRECTORY_SEPARATOR . '.git';

        if (file_exists($dotGitPath)) {
            $cmd = self::CMD_GIT_HEAD;
            $cmdResArr = $this->easyBackupService->execute($cmd);
            $cmdRes = !empty($cmdResArr['err']) ? $cmdResArr['err'] : $cmdResArr['out'];

            $status[] = [
                'desc' => 'git',
                'status' => empty($cmdResArr['err']),
                'result' => $cmdRes,
            ];
        } else {
            $this->log(self::LOG_INFO_PREFIX, 'No git repository recognized. Expected path: ' . $dotGitPath);
        }

        // Check used database

        $dbUrlExploded = explode(':', $this->dbUrl);
        $dbUsed = $dbUrlExploded[0];
        $dbUsedExpected = ['mysql', 'mysqli', 'sqllite'];

        $status[] = [
            'desc' => 'Database',
            'status' => \in_array($dbUsed, $dbUsedExpected),
            'result' => $dbUsed,
        ];

        if ($dbUsed === 'mysql' || $dbUsed === 'mysqli') {
            // Check if the mysqldump command is working

            $cmd = $this->configuration->getMysqlDumpCommand();
            $cmd = explode(' ', $cmd)[0] . ' --version';
            $cmdResArr = $this->easyBackupService->execute($cmd);
            $cmdRes = !empty($cmdResArr['err']) ? $cmdResArr['err'] : $cmdResArr['out'];

            $status[] = [
                'desc' => $cmd,
                'status' => empty($cmdResArr['err']),
                'result' => $cmdRes,
            ];

            // Check if the mysql command is working

            $cmd = $this->configuration->getMysqlRestoreCommand();
            $cmd = explode(' ', $cmd)[0] . ' --version';
            $cmdResArr = $this->easyBackupService->execute($cmd);
            $cmdRes = !empty($cmdResArr['err']) ? $cmdResArr['err'] : $cmdResArr['out'];

            $status[] = [
                'desc' => $cmd,
                'status' => empty($cmdResArr['err']),
                'result' => $cmdRes,
            ];
        }

        return $status;
    }

    private function restoreMySQLDump(string $restoreDir): void
    {
        $this->log(self::LOG_INFO_PREFIX, 'Start restoring MySQL dump.');

        // For mysql or mariadb we must execute additinal code. For sqlite it's just a file which will be moved.

        $dbUrlArr = parse_url($this->dbUrl);
        $scheme = $dbUrlArr['scheme'] ?? null;
        $host = $dbUrlArr['host'] ?? null;
        $port = $dbUrlArr['port'] ?? null;
        $user = $dbUrlArr['user'] ?? null;
        $pass = $dbUrlArr['pass'] ?? null;
        $path = $dbUrlArr['path'] ?? null;

        if (\in_array($scheme, ['mysql', 'mysqli'])) {
            $mysqlCmd = $this->configuration->getMysqlRestoreCommand();
            $mysqlCmd = str_replace('{user}', escapeshellarg($user), $mysqlCmd);
            $mysqlCmd = str_replace('{password}', escapeshellarg(urldecode($pass)), $mysqlCmd);
            $mysqlCmd = str_replace('{host}', escapeshellarg($host), $mysqlCmd);
            $mysqlCmd = str_replace('{database}', escapeshellarg(trim($path, '/')), $mysqlCmd);
            $mysqlCmd = str_replace('{sql_file}', $restoreDir . self::SQL_DUMP_FILENAME, $mysqlCmd);

            // Port can be default port / empty in database URL
            if (!empty($port)) {
                $mysqlCmd = str_replace('{port}', \strval(escapeshellarg($port)), $mysqlCmd);
            } else {
                $mysqlCmd = str_replace('--port={port}', '', $mysqlCmd);
            }

            $mysqlResArr = $this->easyBackupService->execute($mysqlCmd);
            $error = $mysqlResArr['err'];

            $errorsStr = $mysqlResArr['err'];
            $errorsStr = str_replace('mysql: [Warning] Using a password on the command line interface can be insecure.', '', $errorsStr);
            $errorsStr = trim($errorsStr, PHP_EOL);

            if (!empty($errorsStr)) {
                $this->flashError($errorsStr);
                $this->log(self::LOG_ERROR_PREFIX, $errorsStr);
            }
        }

        $this->log(self::LOG_INFO_PREFIX, 'Restored MySQL database.');
    }
}
