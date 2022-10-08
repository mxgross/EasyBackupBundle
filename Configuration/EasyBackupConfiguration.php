<?php

/*
 * This file is part of the EasyBackupBundle.
 * All rights reserved by Maximilian Groß (www.maximiliangross.de).
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\Configuration;

use App\Configuration\SystemConfiguration;

final class EasyBackupConfiguration
{
    private $configuration;

    public function __construct(SystemConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function getMysqlDumpCommand(): string
    {
        $config = $this->configuration->find('easy_backup.setting_mysqldump_command');
        if (!\is_string($config)) {
            return 'NOT SET';
        }

        return $config;
    }

    public function getMysqlRestoreCommand(): string
    {
        $config = $this->configuration->find('easy_backup.setting_mysql_restore_command');
        if (!\is_string($config)) {
            return 'NOT SET';
        }

        return $config;
    }

    public function getBackupDir(): string
    {
        $config = $this->configuration->find('easy_backup.setting_backup_dir');
        if (!\is_string($config)) {
            return 'NOT SET';
        }

        return $config;
    }

    public function getPathsToBeBackuped(): string
    {
        $config = $this->configuration->find('easy_backup.setting_paths_to_backup');

        if (!\is_string($config)) {
            return 'NOT SET';
        }

        return $config;
    }

    public function getBackupAmountMax(): int
    {
        $config = $this->configuration->find('easy_backup.setting_backup_amount_max');

        if (!\is_string($config)) {
            return -1;
        }

        return \intval($config);
    }
}
