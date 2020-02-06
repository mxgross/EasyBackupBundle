### EasyBackup

A Kimai 2 plugin, which allows you to backup your environment with a single click.

After the installation a new menu entry `EasyBackup` is created. There you can create a new backup
by clicking the `Create Backup` button. Afterwards all created backups will be listed at the page
and you can delete or download the createt backup as zip file.

![Kimai2 Easy Backup Plugin Bundle](https://github.com/mxgross/EasyBackupBundle/blob/master/screenshot.jpg?raw=true)

### Installation

First clone it to your Kimai installation `plugins` directory:
```
cd /kimai/var/plugins/
git clone https://github.com/mxgross/EasyBackupBundle.git
```

And then rebuild the cache:
```
cd /kimai/
bin/console cache:clear
bin/console cache:warmup
```
Sometimes the permissions must be set again

```
chown -R :www-data .
chmod -R g+r .
chmod -R g+rw var/
chmod -R g+rw public/avatars/
```

You could also [download it as zip](https://github.com/mxgross/EasyBackupBundle/archive/master.zip) and upload the directory via FTP:

```
/kimai/var/plugins/
├── EasyBackupBundle
│   ├── EasyBackupBundle.php
|   └ ... more files and directories follow here ...
```

Please contact me via [info@maximiliangross.de](mailto:info@maximiliangross.de) for any inquiry.

## Storage

This bundle stores the backups zipped in the directory `var/easy_backup`.
Make sure its writable by your webserver! We don't use the recommended 
`var/data/` directory, because it will be part of the backuped files!

### What files are backed up?

Currently backuped directories and files are:

```
.env
config/packages/local.yaml
var/data/
var/plugins/
templates/invoice/
```

According to the [backup docu](https://www.kimai.org/documentation/backups.html) the Kimai version should be saved to.
Also the current git head.
Therefor a `manifest.json` file with the mentioned information is written and added to the backup.

### What database tables are backuped?

If you use sqlite, the database file is backuped because the `var/data` directory will be backuped by the plugin.

If you use mysql/mariadb the plugin will recognize it by reading the configured database connection url.
Then it will execute a mysqldump command to create a sql dump file, which is added to the backup zip.

The mysqldump command path can be configured via the standard Kimai 2 settings page.

![image](https://user-images.githubusercontent.com/3718449/73966449-4e4d0500-4916-11ea-890e-3008bfb87816.png)

## Permissions

This bundle ships a new permissions, which limit access to the backup screen:

- `easy_backup` - allows access to the backup screen

By default, this are assigned to all users with the role `ROLE_SUPER_ADMIN`.

**Please adjust the permission settings in your user administration.** 

