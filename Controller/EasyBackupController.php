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
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/admin/easy-backup")
 * @Security("is_granted('easy_backup')")
 */
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
     * @var string
     */
    private $filesystem;

    public function __construct(string $dataDirectory, EasyBackupConfiguration $configuration)
    {
        $this->kimaiRootPath = dirname(dirname($dataDirectory)).DIRECTORY_SEPARATOR;
        $this->configuration = $configuration;
        $this->dbUrl = $_SERVER['DATABASE_URL'];
        $this->filesystem = new Filesystem();
    }

    private function log($logLevel, $message)
    {
        $backupDir = $this->getBackupDirectory();
        $logFile = $backupDir.self::LOG_FILE_NAME;

        try {
            if (!file_exists($logFile)) {
                $this->filesystem->touch($logFile);
            }
    
            $dateTime = date('Y-m-d H:i:s');
            $this->filesystem->appendToFile($logFile, "[$dateTime] $logLevel: $message".PHP_EOL);

        }  catch (\Exception $e) {
           $this->flashError('filesystem.mkdir.error.backupDir');
        }

    }

    private function getBackupDirectory(): string
    {
$this->configuration->getMysqlDumpCommand();

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->kimaiRootPath.$this->configuration->getBackupDir());
    }

    /**
     * @Route(path="", name="easy_backup", methods={"GET", "POST"})
     *
     * @return Response
     */
    public function indexAction(): Response
    {
        $backupDir = $this->getBackupDirectory();

        if (!file_exists($backupDir)) {
            $this->filesystem->mkdir($backupDir);
        }

        $existingBackups = [];
        $status = $this->checkStatus();

        if ($this->filesystem->exists($backupDir)) {
            $files = scandir($backupDir, SCANDIR_SORT_DESCENDING);
            $filesAndDirs = array_diff($files, ['.', '..', self::GITIGNORE_NAME]);

            foreach ($filesAndDirs as $fileOrDir) {
                // Make sure that only files are listed which match our wanted regex

                if (is_file($backupDir.$fileOrDir)
                && preg_match(self::REGEX_BACKUP_ZIP_NAME, $fileOrDir) == 1) {
                    $filesizeInMb = round(filesize($backupDir.$fileOrDir) / 1048576, 3);
                    $existingBackups[$fileOrDir] = $filesizeInMb;
                }
            }
        } 

        $logFile = $backupDir.self::LOG_FILE_NAME;
        $log = file_exists($logFile) ? file_get_contents($logFile) : 'empty';

        return $this->render('@EasyBackup/index.html.twig', [
            'existingBackups' => $existingBackups,
            'status' => $status,
            'log' => $log,
        ]);
    }

    /**
     * @Route(path="/create_backup", name="create_backup", methods={"GET", "POST"})
     *
     * @return Response
     */
    public function createBackupAction(): Response
    {
        // Clear old log file
        $logFile = $this->getBackupDirectory().self::LOG_FILE_NAME;
        $this->filesystem->remove($logFile);
        $this->log(self::LOG_INFO_PREFIX, '--- S T A R T   C R E A T I N G   B A C K U P ---');

        $backupName = date(self::BACKUP_NAME_DATE_FORMAT);
        $backupDir = $this->getBackupDirectory();
        $pluginBackupDir = $backupDir.$backupName.DIRECTORY_SEPARATOR;

        // Create the backup folder
        $this->log(self::LOG_INFO_PREFIX, "Creating backup dir '$pluginBackupDir'.");
        $this->filesystem->mkdir($pluginBackupDir);

        // If not yet existing, create a .gitignore to exclude the backup files.

        $gitignoreFullPath = $backupDir.self::GITIGNORE_NAME;

        if (!$this->filesystem->exists($gitignoreFullPath)) {
            $this->filesystem->touch($gitignoreFullPath);
            $this->filesystem->appendToFile($gitignoreFullPath, '*');
        }

        // Save the specific kimai version and git head

        $manifestFile = $pluginBackupDir.self::MANIFEST_FILENAME;
        $this->log(self::LOG_INFO_PREFIX, "Creating manifest file '$manifestFile'.");
        $this->filesystem->touch($manifestFile);
        $manifest = [
            'git' => 'not available',
            'version' => $this->getKimaiVersion(),
            'software' => $this->getKimaiVersion(true),
        ];

        try {
            $manifest['git'] = str_replace(PHP_EOL, '', exec(self::CMD_GIT_HEAD));
        } catch (\Exception $ex) {
            // ignore exception
        }
        $this->filesystem->appendToFile($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));

        // Backing up files and directories
        $this->log(self::LOG_INFO_PREFIX, 'Get files and dirs to backup.');
        $arrayOfPathsToBackup = preg_split('/\r\n|\r|\n/', $this->configuration->getPathsToBeBackuped());

        foreach ($arrayOfPathsToBackup as $filename) {
            $sourceFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->kimaiRootPath.$filename);
            $targetFile = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pluginBackupDir.$filename);

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

        $sqlDumpName = $pluginBackupDir.self::SQL_DUMP_FILENAME;

        $this->backupDatabase($sqlDumpName);
        $backupZipName = $backupDir.$backupName.'.zip';

        $this->zipData($pluginBackupDir, $backupZipName);

        // Now the temporary files can be deleted

        $this->log(self::LOG_INFO_PREFIX, "Remove temp dir '$pluginBackupDir'.");
        $this->filesystem->remove($pluginBackupDir);

        $this->log(self::LOG_INFO_PREFIX, "Remove temp file '$sqlDumpName'.");
        $this->filesystem->remove($sqlDumpName);

        $this->flashSuccess('backup.action.create.success');
        $this->log(self::LOG_INFO_PREFIX, '--- F I N I S H E D   C R E A T I N G   B A C K U P ---');

        return $this->redirectToRoute('easy_backup');
    }

    /**
     * @Route(path="/download", name="download", methods={"GET"})

     *
     * @return Response
     */
    public function downloadAction(Request $request): Response
    {
        $backupName = $request->query->get('backupFilename');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $backupName)) {
            $zipNameAbsolute = $this->getBackupDirectory().$backupName;

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
     * @Route(path="/restore", name="restore", methods={"GET"})

     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function restoreAction(Request $request)
    {
        // Clear old log file
        $logFile = $this->getBackupDirectory().self::LOG_FILE_NAME;
        $this->filesystem->remove($logFile);

        $this->log(self::LOG_INFO_PREFIX, '--- S T A R T   R E S T O R E ---');

        $backupName = $request->query->get('backupFilename');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $backupName)) {
            // Prepare paths for windows and unix system as well.

            $zipNameAbsolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBackupDirectory().$backupName);
            $backupName = basename($zipNameAbsolute, '.zip'); // e.g. 2020-11-02_174452
            $restoreDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBackupDirectory());
            $restoreDir = $restoreDir.$backupName.DIRECTORY_SEPARATOR; // e.g. .../kimai2/var/easy_backup/2020-11-02_174452

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
     * @Route(path="/prepareRecovery", name="prepareRecovery", methods={"GET"})

     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function prepareRecoveryAction(Request $request)
    {
        $backupName = $request->query->get('backupFilename');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $backupName)) {
            // Prepare paths for windows and unix system as well.

            $zipNameAbsolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBackupDirectory().$backupName);
            $backupName = basename($zipNameAbsolute, '.zip'); // e.g. 2020-11-02_174452
            $restoreDir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $this->getBackupDirectory());
            $restoreDir = $restoreDir.$backupName.DIRECTORY_SEPARATOR; // e.g. .../kimai2/var/easy_backup/2020-11-02_174452

            $this->unzip($zipNameAbsolute, $restoreDir);
        } else {
            $this->flashError('backup.action.filename.error');
        }

        $fileOverwrites = $this->getFilesInDirRecursively($restoreDir);

        $fileOverwrites = array_filter(str_replace('C:\\xampp\\htdocs\kimai2\\', '', $fileOverwrites));

        // Cleanup the extracted backup folder
        $this->filesystem->remove($restoreDir);

        return $this->render('@EasyBackup/prepairRecovery.html.twig', [
            'fileOverwrites' => $fileOverwrites,
        ]);
    }

    private function getFilesInDirRecursively($dir, &$resultFileList = [])
    {
        $files = scandir($dir);

        foreach ($files as $fileOrDir) {
            $path = realpath($dir.DIRECTORY_SEPARATOR.$fileOrDir);
            if (!is_dir($path)) {
                $resultFileList[] = $path;
            } elseif (!in_array($fileOrDir, ['.', '..', '.git'])) {
                $this->getFilesInDirRecursively($path, $resultFileList);
            }
        }

        return $resultFileList;
    }

    private function restoreDirsAndFiles($restoreDir)
    {
        $this->log(self::LOG_INFO_PREFIX, 'Start restoring files and dirs.');

        // Blacklist for files we don't want to move anywere else.

        $blacklist = [self::SQL_DUMP_FILENAME, self::MANIFEST_FILENAME];
        $this->log(self::LOG_INFO_PREFIX, 'Blacklist of files not to move: '.join(', ', $blacklist));

        $filePathsToRestore = $this->getFilesInDirRecursively($restoreDir);

        foreach ($filePathsToRestore as $filenameAbs) {
            $filenameOnlyArr = explode(DIRECTORY_SEPARATOR, $filenameAbs);
            $filenameOnly = end($filenameOnlyArr);

            // Some files in the backup dir are for internal usage, we don't want to move them anywere else.

            if (in_array($filenameOnly, $blacklist)) {
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
     * @Route(path="/delete", name="delete", methods={"GET"})

     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction(Request $request)
    {
        $dirname = $request->query->get('backupFilename');

        // Validate the given user input (filename)

        if (preg_match(self::REGEX_BACKUP_ZIP_NAME, $dirname)) {
            $path = $this->getBackupDirectory().$dirname;

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
        $this->log(self::LOG_INFO_PREFIX, 'Start database backup.');
        $dbUrlExploded = explode(':', $this->dbUrl);
        $dbUsed = $dbUrlExploded[0];

        // This is only for mysql and mariadb. sqlite will be backuped via the file backups
        $this->log(self::LOG_INFO_PREFIX, "Used database: '$dbUsed'.");

        if ($dbUsed === 'mysql' || $dbUsed === 'mysqli') {
            $dbUser = str_replace('/', '', $dbUrlExploded[1]);
            $dbPwd = explode('@', $dbUrlExploded[2])[0];
            $dbHost = explode('@', $dbUrlExploded[2])[1];
            $dbPort = explode('/', explode('@', $dbUrlExploded[3])[0])[0];
            $dbName = explode('?', explode('/', $dbUrlExploded[3])[1])[0];

            // The MysqlDumpCommand per default looks like this: '/usr/bin/mysqldump --user={user} --password={password} --host={host} --port={port} --single-transaction --force {database}'

            $mysqlDumpCmd = $this->configuration->getMysqlDumpCommand();
            $mysqlDumpCmd = str_replace('{user}', $dbUser, $mysqlDumpCmd);
            $mysqlDumpCmd = str_replace('{password}', urldecode($dbPwd), $mysqlDumpCmd);
            $mysqlDumpCmd = str_replace('{host}', $dbHost, $mysqlDumpCmd);
            $mysqlDumpCmd = str_replace('{port}', $dbPort, $mysqlDumpCmd);
            $mysqlDumpCmd = str_replace('{database}', $dbName, $mysqlDumpCmd);

            // $numErrors is 0 when no error occured, else the number of occured errors
            // $output is an string array containing success or error messages

            $mysqlResArr = $this->execute($mysqlDumpCmd);

            if(!empty($mysqlResArr['out'])) {
                $this->log(self::LOG_INFO_PREFIX, "Creating '$sqlDumpName'.");
                $this->filesystem->touch($sqlDumpName);
                $this->filesystem->appendToFile($sqlDumpName, $mysqlResArr['out']);
            }

            $errorsStr = $mysqlResArr['err'];
            $errorsStr = str_replace('mysqldump: [Warning] Using a password on the command line interface can be insecure.', '', $errorsStr);
            $errorsStr = trim($errorsStr, PHP_EOL);

            if (!empty($errorsStr)) {
                $this->flashError($errorsStr);
                $this->log(self::LOG_ERROR_PREFIX, $errorsStr);
            } 
        }
    }

    private function startsWith($needle, $haystack)
    {
        $length = strlen($needle);

        return substr($haystack, 0, $length) === $needle;
    }

    private function unzip($source, $destination)
    {
        $this->log(self::LOG_INFO_PREFIX, "Start unzipping '$source' to '$destination'.");

        if (extension_loaded('zip') === true) {
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
            $this->log(self::LOG_ERROR_PREFIX, "Extension ZIP not found.");
        }

        return false;
    }

    private function zipData($source, $destination)
    {
        $this->log(self::LOG_INFO_PREFIX, "Start zipping '$source' to '$destination'.");

        if (extension_loaded('zip') === true) {
            if (file_exists($source) === true) {
                $zip = new ZipArchive();
                if ($zip->open($destination, ZIPARCHIVE::CREATE) === true) {
                    $source = realpath($source);
                    if (is_dir($source) === true) {
                        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST);

                        foreach ($files as $file) {
                            // Ignore "." and ".." folders
                            if (in_array(substr($file, strrpos($file, DIRECTORY_SEPARATOR) + 1), ['.', '..'])) {
                                continue;
                            }

                            $file = realpath($file);

                            if (is_dir($file) === true) {
                                $zip->addEmptyDir(str_replace($source.DIRECTORY_SEPARATOR, '', $file.DIRECTORY_SEPARATOR));
                            } elseif (is_file($file) === true) {
                                $zip->addFromString(str_replace($source.DIRECTORY_SEPARATOR, '', $file), file_get_contents($file));
                            }
                        }
                    } elseif (is_file($source) === true) {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                } else {
                    $this->flashError('backup.action.zip.error.destination');
                    $this->log(self::LOG_ERROR_PREFIX, "Couldn't open '$destination'.");
                }

                return $zip->close();
            } else {
                $this->flashError('backup.action.zip.error.source');
                $this->log(self::LOG_ERROR_PREFIX, "Source file not found: '$source'.");
            }
        } else {
            $this->flashError('backup.action.zip.error.extension');
            $this->log(self::LOG_ERROR_PREFIX, "Extension 'zip' not found!");
        }

        return false;
    }

    private function execute($cmd, $workdir = null) {

        if (is_null($workdir)) {
            $workdir = __DIR__;
        }
    
        $descriptorspec = array(
           0 => array("pipe", "r"),  // stdin
           1 => array("pipe", "w"),  // stdout
           2 => array("pipe", "w"),  // stderr
        );
    
        $process = proc_open($cmd, $descriptorspec, $pipes, $workdir, null);
    
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
    
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
    
        return [
            'code' => proc_close($process),
            'out' => trim($stdout),
            'err' => trim($stderr),
        ];
    }

    private function checkStatus()
    {
        $status = [];

        $path = $this->kimaiRootPath.'var';
        $status[] = [
                'desc' => "Path '$path' readable",
                'status' => is_readable($path),
                'result' => '',
        ];

        $path = $this->kimaiRootPath.'var';
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
            'status' => extension_loaded('zip'),
            'result' => '',
        ];

        $status[] = [
            'desc' => 'Kimai version',
            'status' => true,
            'result' => $this->getKimaiVersion(),
        ];

        // Todo: build path via config files instead of manually
        $dotGitPath = $this->kimaiRootPath . 'var' . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'EasyBackupBundle' . DIRECTORY_SEPARATOR .'.git';

        if (file_exists($dotGitPath)) {
            $cmd = self::CMD_GIT_HEAD;
            $cmdResArr = $this->execute($cmd);
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
            'status' => in_array($dbUsed, $dbUsedExpected),
            'result' => $dbUsed,
        ];

        if ($dbUsed === 'mysql' || $dbUsed === 'mysqli') {

            // Check if the mysqldump command is working

            $cmd = $this->configuration->getMysqlDumpCommand();
            $cmd = explode(' ', $cmd)[0].' --version';
            $cmdResArr = $this->execute($cmd);
            $cmdRes = !empty($cmdResArr['err']) ? $cmdResArr['err'] : $cmdResArr['out'];

            $status[] = [
                'desc' => $cmd,
                'status' => empty($cmdResArr['err']),
                'result' => $cmdRes,
            ];

            // Check if the mysql command is working

            $cmd = $this->configuration->getMysqlRestoreCommand();
            $cmd = explode(' ', $cmd)[0].' --version';
            $cmdResArr = $this->execute($cmd);
            $cmdRes = !empty($cmdResArr['err']) ? $cmdResArr['err'] : $cmdResArr['out'];

            $status[] = [
                'desc' => $cmd,
                'status' => empty($cmdResArr['err']),
                'result' => $cmdRes,
            ];
        }

        return $status;
    }

    private function getKimaiVersion(bool $full = false): string
    {
        if ($full) {
            return Constants::SOFTWARE.' - '.Constants::VERSION.' '.Constants::STATUS;
        }

        return Constants::VERSION.' '.Constants::STATUS;
    }

    private function restoreMySQLDump($restoreDir)
    {
        $this->log(self::LOG_INFO_PREFIX, 'Start restoring MySQL dump.');

        // For mysql or mariadb we must execute additinal code. For sqlite it's just a file which will be moved.

        $dbUrlExploded = explode(':', $this->dbUrl);
        $dbUsed = $dbUrlExploded[0];

        if ($dbUsed === 'mysql' || $dbUsed === 'mysqli') {
            $dbUser = str_replace('/', '', $dbUrlExploded[1]);
            $dbPwd = explode('@', $dbUrlExploded[2])[0];
            $dbHost = explode('@', $dbUrlExploded[2])[1];
            $dbPort = explode('/', explode('@', $dbUrlExploded[3])[0])[0];
            $dbName = explode('?', explode('/', $dbUrlExploded[3])[1])[0];

            $mysqlCmd = $this->configuration->getMysqlRestoreCommand();
            $mysqlCmd = str_replace('{user}', $dbUser, $mysqlCmd);
            $mysqlCmd = str_replace('{password}', urldecode($dbPwd), $mysqlCmd);
            $mysqlCmd = str_replace('{host}', $dbHost, $mysqlCmd);
            $mysqlCmd = str_replace('{port}', $dbPort, $mysqlCmd);
            $mysqlCmd = str_replace('{database}', $dbName, $mysqlCmd);
            $mysqlCmd = str_replace('{sql_file}', $restoreDir.self::SQL_DUMP_FILENAME, $mysqlCmd);

            $mysqlResArr = $this->execute($mysqlCmd);
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
