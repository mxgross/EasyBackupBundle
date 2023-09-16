<?php

/*
 * This file is part of the EasyBackupBundle.
 * All rights reserved by Maximilian Groß (www.maximiliangross.de).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\Service;

use App\Constants;
use KimaiPlugin\EasyBackupBundle\Configuration\EasyBackupConfiguration;
use PhpOffice\PhpWord\Shared\ZipArchive;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\Exception\RuntimeException;

/**
 * @Service
 */
class EasyBackupService
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

    public function __construct(string $dataDirectory, EasyBackupConfiguration $configuration)
    {
        $this->kimaiRootPath = \dirname(\dirname($dataDirectory)) . DIRECTORY_SEPARATOR;
        $this->configuration = $configuration;
        $this->dbUrl = $_SERVER['DATABASE_URL'];
        $this->filesystem = new Filesystem();
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
            throw new RuntimeException('filesystem.mkdir.error.backupDir');
        }
    }

    public function getBackupDirectory(): string
    {
        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->kimaiRootPath . $this->configuration->getBackupDir());
    }

    public function createBackup()
    {
        // Clear old log file
        $logFile = $this->getBackupDirectory() . self::LOG_FILE_NAME;
        $this->filesystem->remove($logFile);
        $this->log(self::LOG_INFO_PREFIX, '--- S T A R T   C R E A T I N G   B A C K U P ---');

        $backupName = date(self::BACKUP_NAME_DATE_FORMAT);
        $backupDir = $this->getBackupDirectory();
        $pluginBackupDir = $backupDir . $backupName . DIRECTORY_SEPARATOR;

        // Create the backup folder
        $this->log(self::LOG_INFO_PREFIX, "Creating backup dir '$pluginBackupDir'.");
        $this->filesystem->mkdir($pluginBackupDir);

        // If not yet existing, create a .gitignore to exclude the backup files.

        $gitignoreFullPath = $backupDir . self::GITIGNORE_NAME;

        if (!$this->filesystem->exists($gitignoreFullPath)) {
            $this->filesystem->touch($gitignoreFullPath);
            $this->filesystem->appendToFile($gitignoreFullPath, '*');
        }

        // Save the specific kimai version and git head

        $manifestFile = $pluginBackupDir . self::MANIFEST_FILENAME;
        $this->log(self::LOG_INFO_PREFIX, "Creating manifest file '$manifestFile'.");
        $this->filesystem->touch($manifestFile);
        $manifest = [
            'git' => 'not available',
            'version' => $this->getKimaiVersion(),
            'software' => $this->getKimaiVersion(true),
        ];

        try {
            $output = [];
            $returnValue = null;

            $this->log(self::LOG_INFO_PREFIX, "Executing '" . self::CMD_GIT_HEAD . "'.");
            exec(self::CMD_GIT_HEAD, $output, $returnValue);

            // Check if the $output array contains at least one element
            if (!empty($output)) {
                // Extract the first element (Git commit hash) and assign it to $manifest['git']
                $manifest['git'] = $output[0];

                $this->log(self::LOG_INFO_PREFIX, 'Git commit hash: ' . $manifest['git']);
            } else {
                // Handle the case where $output is empty (no output received)
                $this->log(self::LOG_WARN_PREFIX, 'No output received from the command.');
            }

            // Check the return value to detect errors
            if ($returnValue !== 0) {
                $this->log(self::LOG_WARN_PREFIX, "Command failed with exit code: $returnValue");
            }
        } catch (\Exception $ex) {
            $this->log(self::LOG_WARN_PREFIX, $ex->getMessage());
        }

        $this->filesystem->appendToFile($manifestFile, \strval(json_encode($manifest, JSON_PRETTY_PRINT)));

        // Backing up files and directories
        $this->log(self::LOG_INFO_PREFIX, 'Get files and dirs to backup.');
        $arrayOfPathsToBackup = preg_split('/\r\n|\r|\n/', $this->configuration->getPathsToBeBackuped());

        foreach ($arrayOfPathsToBackup as $filename) {
            $sourceFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->kimaiRootPath . $filename);
            $targetFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pluginBackupDir . $filename);

            $this->log(self::LOG_INFO_PREFIX, "Start to backup '$sourceFile'.");

            if ($this->filesystem->exists($sourceFile)) {
                if (is_dir($sourceFile)) {
                    $this->log(self::LOG_INFO_PREFIX, "It's a directory. Start to mirror it to '$targetFile'.");
                    $this->filesystem->mirror($sourceFile, $targetFile);
                }

                if (is_file($sourceFile)) {
                    $this->log(self::LOG_INFO_PREFIX, "It's a file. Start to copy it to '$targetFile'.");
                    $this->filesystem->copy($sourceFile, $targetFile);
                }
            } else {
                $this->log(self::LOG_WARN_PREFIX, "Path '$sourceFile' is not existing or not accessable.");
            }
        }

        $sqlDumpName = $pluginBackupDir . self::SQL_DUMP_FILENAME;

        $this->backupDatabase($sqlDumpName);
        $backupZipName = $backupDir . $backupName . '.zip';

        $this->zipData($pluginBackupDir, $backupZipName);

        // Now the temporary files can be deleted

        $this->log(self::LOG_INFO_PREFIX, "Remove temp dir '$pluginBackupDir'.");
        $this->filesystem->remove($pluginBackupDir);

        $this->log(self::LOG_INFO_PREFIX, "Remove temp file '$sqlDumpName'.");
        $this->filesystem->remove($sqlDumpName);

        // Delete old backups if configured so
        $this->deleteOldBackups();
        $this->log(self::LOG_INFO_PREFIX, '--- F I N I S H E D   C R E A T I N G   B A C K U P ---');

        $logFile = $backupDir . self::LOG_FILE_NAME;
        $log = file_exists($logFile) ? file_get_contents($logFile) : '';

        return $log;
    }

    public function getFilesInDirRecursively(string $dir, array &$resultFileList = []): array
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

    public function backupDatabase(string $sqlDumpName): void
    {
        $this->log(self::LOG_INFO_PREFIX, 'Start database backup.');

        $dbUrlArr = parse_url($this->dbUrl);

        /*  Example:

            array(6) {
            ["scheme"] => string(5) "mysql"
            ["host"]   => string(9) "127.0.0.1"
            ["port"]   => int(3306)
            ["user"]   => string(8) "myDbUser"
            ["pass"]   => string(24) "my-super-secret-password"
            ["path"]   => string(9) "/myDBName"
            ["query"]  => string(30) "charset=utf8&serverVersion=5.7"
            }
        */

        $scheme = $dbUrlArr['scheme'] ?? null;
        $host = $dbUrlArr['host'] ?? null;
        $port = $dbUrlArr['port'] ?? null;
        $user = $dbUrlArr['user'] ?? null;
        $pass = $dbUrlArr['pass'] ?? null;
        $path = $dbUrlArr['path'] ?? null;

        // This is only for mysql and mariadb. sqlite will be backuped via the file backups
        $this->log(self::LOG_INFO_PREFIX, "Used database: '$scheme'.");

        if (\in_array($scheme, ['mysql', 'mysqli'])) {
            // The MysqlDumpCommand per default looks like this: '/usr/bin/mysqldump --user={user} --password={password} --host={host} --port={port} --single-transaction --force {database}'

            $mysqlDumpCmd = $this->configuration->getMysqlDumpCommand();
            $mysqlDumpCmd = str_replace('{user}', escapeshellarg($user), $mysqlDumpCmd);
            $mysqlDumpCmd = str_replace('{password}', escapeshellarg(urldecode($pass)), $mysqlDumpCmd);
            $mysqlDumpCmd = str_replace('{host}', escapeshellarg($host), $mysqlDumpCmd);
            $mysqlDumpCmd = str_replace('{database}', escapeshellarg(trim($path, '/')), $mysqlDumpCmd);

            // Port can be default port / empty in database URL
            if (!empty($port)) {
                $mysqlDumpCmd = str_replace('{port}', \strval(escapeshellarg($port)), $mysqlDumpCmd);
            } else {
                $mysqlDumpCmd = str_replace('--port={port}', '', $mysqlDumpCmd);
            }

            // $numErrors is 0 when no error occured, else the number of occured errors
            // $output is an string array containing success or error messages

            $mysqlResArr = $this->execute($mysqlDumpCmd);

            if (!empty($mysqlResArr['out'])) {
                // When the mysqldump command cannot be parsed it will not throw an error but something like e.g.
                // Usage: mysqldump [OPTIONS] database [tables] OR mysqldump [OPTIONS] --databases [OPTIONS] DB1 [DB2 DB3...] OR mysqldump [OPTIONS] --all-databases [OPTIONS] For more options, use mysqldump --help
                // As this would be written to the mysql dump file we catch this case and write an error into the log.
                // In the end of the backup process an error message is shown if any error in the log exists.

                if (preg_match('/Usage: mysqldump/i', $mysqlResArr['out'])) {
                    $this->log(self::LOG_ERROR_PREFIX, $mysqlResArr['out']);
                } else {
                    $this->log(self::LOG_INFO_PREFIX, "Creating '$sqlDumpName'.");
                    $this->filesystem->touch($sqlDumpName);
                    $this->filesystem->appendToFile($sqlDumpName, $mysqlResArr['out']);
                }
            }

            $errorsStr = $mysqlResArr['err'];
            $errorsStr = str_replace('mysqldump: [Warning] Using a password on the command line interface can be insecure.', '', $errorsStr);
            $errorsStr = trim($errorsStr, PHP_EOL);

            if (!empty($errorsStr)) {
                $this->log(self::LOG_ERROR_PREFIX, $errorsStr);
                throw new RuntimeException($errorsStr);
            }
        }
    }

    public function zipData(string $source, string $destination): bool
    {
        $this->log(self::LOG_INFO_PREFIX, "Start zipping '$source' to '$destination'.");

        if (\extension_loaded('zip') === true) {
            if (file_exists($source) === true) {
                $zip = new ZipArchive();
                if ($zip->open($destination, ZIPARCHIVE::CREATE) === true) {
                    $source = realpath($source);
                    if (is_dir($source) === true) {
                        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

                        foreach ($files as $file) {
                            // Ignore "." and ".." folders
                            if (\in_array(substr($file, strrpos($file, DIRECTORY_SEPARATOR) + 1), ['.', '..'])) {
                                continue;
                            }

                            $file = realpath($file);

                            if (is_dir($file) === true) {
                                $zip->addEmptyDir(str_replace($source . DIRECTORY_SEPARATOR, '', $file . DIRECTORY_SEPARATOR));
                            } elseif (is_file($file) === true) {
                                $zip->addFromString(str_replace($source . DIRECTORY_SEPARATOR, '', $file), file_get_contents($file));
                            }
                        }
                    } elseif (is_file($source) === true) {
                        $zip->addFromString(basename($source), file_get_contents($source) ?: '');
                    }
                } else {
                    $this->log(self::LOG_ERROR_PREFIX, "Couldn't open '$destination'.");
                    throw new RuntimeException('backup.action.zip.error.destination');
                }

                return $zip->close();
            } else {
                $this->log(self::LOG_ERROR_PREFIX, "Source file not found: '$source'.");
                throw new RuntimeException('backup.action.zip.error.source');
            }
        } else {
            $this->log(self::LOG_ERROR_PREFIX, "Extension 'zip' not found!");
            throw new RuntimeException('backup.action.zip.error.extension');
        }

        return false;
    }

    public function execute(string $cmd, string $workdir = null): array
    {
        if (\is_null($workdir)) {
            $workdir = __DIR__;
        }

        $descriptorspec = [
           0 => ['pipe', 'r'],  // stdin
           1 => ['pipe', 'w'],  // stdout
           2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open(escapeshellcmd($cmd), $descriptorspec, $pipes, $workdir, null);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [
            'code' => proc_close($process),
            'out' => trim(\strval($stdout)),
            'err' => trim(\strval($stderr)),
        ];
    }

    public function getKimaiVersion(bool $full = false): string
    {
        if ($full) {
            return Constants::SOFTWARE . ' - ' . Constants::VERSION; // . ' ' . Constants::STATUS; TODO
        }

        return Constants::VERSION; // . ' ' . Constants::STATUS; // TODO
    }

    public function getExistingBackups(): array
    {
        $backupDir = $this->getBackupDirectory();
        $existingBackups = [];

        if ($this->filesystem->exists($backupDir)) {
            $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
            $filesAndDirs = array_diff($files, ['.', '..', self::GITIGNORE_NAME]);

            foreach ($filesAndDirs as $fileOrDir) {
                // Make sure that only files are listed which match our wanted regex

                if (is_file($backupDir . $fileOrDir)
                && preg_match(self::REGEX_BACKUP_ZIP_NAME, $fileOrDir) == 1) {
                    $filesizeInMb = round(filesize($backupDir . $fileOrDir) / 1048576, 3);
                    $filemtime = filemtime($backupDir . $fileOrDir);

                    $existingBackups[] = ['name' => $fileOrDir,
                                          'size' => $filesizeInMb,
                                          'filemtime' => $filemtime];
                }
            }
        }

        return $existingBackups;
    }

    public function deleteOldBackups(): array
    {
        $backupAmountMax = $this->configuration->getBackupAmountMax();
        $existingBackupsArr = $this->getExistingBackups();
        $numBackupsExisting = \count($existingBackupsArr);
        $backupsToDeleteArr = [];

        // Important to do nothing when backupAmountMax is -1 or 0, because then we want to keep all the backups / no auto deletion
        if ($backupAmountMax > 0 && $numBackupsExisting > $backupAmountMax) {
            $this->log(self::LOG_INFO_PREFIX, "Delete old backups. Max. amount to keep: $backupAmountMax; Existing: $numBackupsExisting");

            // Sort backups by creation date
            usort($existingBackupsArr, function ($a, $b) {
                return $a['filemtime'] <=> $b['filemtime'];
            });

            $amountToDelete = $numBackupsExisting - $backupAmountMax;

            // A array with all backups to delete is wanted
            array_splice($existingBackupsArr, $amountToDelete);

            $backupsToDeleteArr = $existingBackupsArr;
            $path = $this->getBackupDirectory();

            foreach ($backupsToDeleteArr as $backupToDelete) {
                $backupFullPath = $path . $backupToDelete['name'];

                if ($this->filesystem->exists($backupFullPath)) {
                    $this->filesystem->remove($backupFullPath);
                    $this->log(self::LOG_INFO_PREFIX, "Deleted backup '$backupFullPath'");
                }
            }
        }

        return $backupsToDeleteArr;
    }
}
