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
        return (string) $this->find('setting_mysqldump_command');
    }

    public function getBackupDir(): string
    {
        return (string) $this->find('setting_backup_dir');
    }

    public function getPathsToBeBackuped(): string
    {
        return (string) $this->find('setting_paths_to_be_backuped');
    }
}
