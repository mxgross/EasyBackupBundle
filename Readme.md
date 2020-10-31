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
Set the permissions:
```
sudo chown -R :www-data .
sudo chmod -R g+r .
sudo chmod -R g+rw var/
sudo chmod -R g+rw public/avatars/
```

And then rebuild the cache:
```
cd /kimai/
bin/console cache:clear
bin/console cache:warmup
```
Sometimes the permissions must be set again

```
sudo chown -R :www-data .
sudo chmod -R g+r .
sudo chmod -R g+rw var/
sudo chmod -R g+rw public/avatars/
```

You could also [download it as zip](https://github.com/mxgross/EasyBackupBundle/archive/master.zip) and upload the directory via FTP:

```
/kimai/var/plugins/
├── EasyBackupBundle
│   ├── EasyBackupBundle.php
|   └ ... more files and directories follow here ...
```

Feel free to participate in existing issues or create a issue for any new inquiry.

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
%kimai.invoice.documents%
```

According to the [backup docu](https://www.kimai.org/documentation/backups.html) the Kimai version should be saved to.
Also the current git head.
Therefor a `manifest.json` file with the mentioned information is written and added to the backup.

### What database tables are backuped?

If you use sqlite, the database file is backuped because the `var/data` directory will be backuped by the plugin.

If you use mysql/mariadb the plugin will recognize it by reading the configured database connection url.
Then it will execute a mysqldump command to create a sql dump file, which is added to the backup zip.

The mysqldump command can be configured via the standard Kimai 2 settings page.
Per default it is
```
/usr/bin/mysqldump --user={user} --password={password} --host={host} --port={port} --single-transaction --force {database}
```
You can remove or add parameters here if you need to. The variables in the curly braces will be replaced during the execution of the backup. All information for these variables are gathered from the DATABASE_URL defined in the .env file.
```
# DATABASE_URL=mysql://user:password@host:port/database
# For example:
DATABASE_URL=mysql://JohnDoe:MySecret1234@127.0.0.1:3306/kimai2
```

## Permissions

This bundle ships a new permissions, which limit access to the backup screen:

- `easy_backup` - allows access to the backup screen

By default, this are assigned to all users with the role `ROLE_SUPER_ADMIN`.

**Please adjust the permission settings in your user administration.** 

## Restore
Currently the plugin has no automation to restore a backup per click. You have to do it by hand. Just copy the backuped directories and files into your Kimai2 installation. Some hints can be found here: [Official Kimai2 backup and restore docu](https://www.kimai.org/documentation/backups.html)

If you are using a mysql/mariadb database you can import the backuped .sql file with tools like phpMyAdmin or by the command
```
mysql -u username -p database_name < file.sql
```
For additional information please lookup the mysql commands documentation.