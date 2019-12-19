<?php

/*
 * This file is part of the Kimai EasyBackupBundle.
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

    public function getMysqlDumpPath(): string
    {
        return (string) $this->find('setting_mysqldump_path');
    }

    public function getBackupDir(): string
    {
        return (string) $this->find('setting_backup_dir');
    }
}
