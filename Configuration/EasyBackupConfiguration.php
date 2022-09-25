<?php

/*
 * This file is part of the EasyBackupBundle for Kimai 2.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\EasyBackupBundle\Configuration;

use App\Configuration\StringAccessibleConfigTrait;
use App\Configuration\SystemBundleConfiguration;

class EasyBackupConfiguration implements SystemBundleConfiguration, \ArrayAccess
{
    use StringAccessibleConfigTrait;

    
    public function getPrefix(): string
    {
        return 'easy_backup';
    }

    public function getMysqlDumpCommand(): string
    {
        $config = $this->find('setting_mysqldump_command');
        if (!\is_string($config)) {
            return 'NOT SET';
        }

        return $config;
    }

    public function getMysqlRestoreCommand(): string
    {
        $config = $this->find('setting_mysql_restore_command');
        if (!\is_string($config)) {
            return 'NOT SET';
        }

        return $config;
    }

    public function getBackupDir(): string
    {   
        $config = $this->find('setting_backup_dir');
        if (!\is_string($config)) {
            return 'NOT SET';
        }

        return $config;
    }

    public function getPathsToBeBackuped(): string
    {
        $config = $this->find('setting_paths_to_backup');
        if (!\is_string($config)) {
            return 'NOT SET';
        }

        return $config;
    }
}
