<?php

namespace KimaiPlugin\EasyBackupBundle\Controller;

use App\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

use PhpOffice\PhpWord\Shared\ZipArchive;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use KimaiPlugin\EasyBackupBundle\Configuration\EasyBackupConfiguration;

class EasyBackupController extends AbstractController
{
    const   CMD_GIT_HEAD = 'git rev-parse HEAD';

    const   README_FILENAME = 'Readme.txt';

    const   SQL_DUMP_FILENAME = 'database_dump.sql';

    const   CMD_KIMAI_VERSION = '/bin/console kimai:version';

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
     * @Route(path="/admin/easy-backup", name="easy_backup", methods={"GET", "POST"})

     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {

        $existingBackups = array();
        $filesystem = new Filesystem();

        $status = $this->checkStatus();

        if ($filesystem->exists($this->backupDirectory)) {
            $files = scandir($this->backupDirectory, SCANDIR_SORT_DESCENDING);
            $filesAndDirs = array_diff($files, array('.', '..'));

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
     * @Route(path="/admin/easy-backup/create_backup", name="create_backup", methods={"GET", "POST"})

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

        $arrayOfPathsToBackup = array(
            '.env',
            'config/packages/local.yaml',
            'var/data/',
            'var/plugins/',
        );

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
        $backupZipName = $this->backupDirectory . $backupName . '.zip';

        $this->zipData($pluginBackupDir, $backupZipName);

        // Now the folder can be deleted
        $filesystem->remove($pluginBackupDir);

        return $this->redirectToRoute('easy_backup', $request->query->all());
    }

    /**
     * @Route(path="/admin/easy-backup/download", name="download", methods={"GET"})

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
     * @Route(path="/admin/easy-backup/delete", name="delete", methods={"GET"})

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

        $this->addFlash("success", "Backup deleted.");

        return $this->redirectToRoute('easy_backup', $request->query->all());
    }

    protected function backupDatabase()
    {

        //$this->dbUrl = 'mysql://kimai:3oxlXhrFDjGbVDEJ@localhost:3306/kimai';
        $dbUrlExploded = explode(':', $this->dbUrl);
        $dbUsed = $dbUrlExploded[0];

        // This is only for mysql and mariadb. sqlite will be backuped via the file backups

        if ($dbUsed == 'mysql') {

            $dbUser = str_replace('/', '', $dbUrlExploded[1]);
            $dbPwd = explode('@', $dbUrlExploded[2])[0];
            $dbName = explode('/', $dbUrlExploded[3])[1];

            $sqlDumpName = $pluginBackupDir . self::SQL_DUMP_FILENAME;

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
                            } else if (is_file($file) === true) {
                                $zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
                            }
                        }
                    } else if (is_file($source) === true) {
                        $zip->addFromString(basename($source), file_get_contents($source));
                    }
                } else {
                    $this->addFlash("error", "Error while creating zip file '$destination'.");
                }
                return $zip->close();
            } else {
                $this->addFlash("error", "Source'source' not existing.");
            }
        } else {
            $this->addFlash("error", "No php extension 'zip' found.");
        }
        return false;
    }

    protected function checkStatus()
    {
        $status = array();

        // Check 
        $path = $this->kimaiRootPath . 'var';
        $status["Path '$path' readable?"] = is_readable($path);
        $status["Path '$path' writable"] = is_writable($path);
        $status["PHP extionsion 'zip' loaded?"] = extension_loaded('zip');

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
