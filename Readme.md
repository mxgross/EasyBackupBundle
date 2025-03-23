### EasyBackup

A Kimai 2 plugin, which allows you to backup your environment with a single click or via cronjob / command line.

If you like the plugin, please feel free to donate me a coffee. I use my precious free time to operate and improve the plugin and don't earn any money from it.

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/donate?hosted_button_id=XQD3PMPANZNG4)

After the installation a new menu entry `EasyBackup` is created. There you can create a new backup
by clicking the `Create Backup` button. Afterwards all created backups will be listed at the page
and you can delete or download the created backup as zip file.

![Kimai2 Easy Backup Plugin Bundle](https://github.com/mxgross/EasyBackupBundle/blob/main/screenshot.jpg?raw=true)

### Installation

First clone it to your Kimai installation `plugins` directory:

For Kimai2 Version < 2.0.0 use branch 'master'.
For 2.0.0 and later use branch 'main'.

```
cd /kimai/var/plugins/
git clone https://github.com/mxgross/EasyBackupBundle.git
```
Set the permissions:
```
sudo chown -R :www-data . &&
sudo chmod -R g+r . &&
sudo chmod -R g+rw var/ &&
sudo chmod -R g+rw public/avatars/ &&
sudo chmod -R o+rw var/plugins/EasyBackupBundle
```

And then rebuild the cache:
```
cd /kimai/
bin/console cache:clear
bin/console cache:warmup
```
Sometimes the permissions must be set again

```
sudo chown -R :www-data . &&
sudo chmod -R g+r . &&
sudo chmod -R g+rw var/ &&
sudo chmod -R g+rw public/avatars/
```

You could also [download it as zip](https://github.com/mxgross/EasyBackupBundle/archive/main.zip) and upload the directory via FTP:

```
/kimai/var/plugins/
├── EasyBackupBundle
│   ├── EasyBackupBundle.php
|   └ ... more files and directories follow here ...
```

Feel free to participate in existing issues or create a issue for any new inquiry.

## Storage

This bundle stores the backups by default zipped inside the Kimai directory in `var/easy_backup`.
Make sure its writable by your webserver! We don't use the recommended 
`var/data/` directory, because it will be part of the backuped files!

### What files are backed up?

Currently per default backuped directories (incl. sub directories) and files are:

```
.env
config/packages/local.yaml
var/data/
var/plugins/
var/invoices/
templates/invoice
var/export/
templates/export/
```
You are free to edit this list via the Kimai settings page. Place each filename or paths in a seperate line. Make sure that there are no empty lines. Root path is your Kimai installation path.

![Update the paths to your needs](https://github.com/mxgross/EasyBackupBundle/blob/main/screenshot_files_and_paths_to_be_backed_up.jpg?raw=true)

According to the [backup docu](https://www.kimai.org/documentation/backups.html) the Kimai version should be saved to.
Also the current git head.
Therefor a `manifest.json` file with the mentioned information is written and added to the backup.

### What database tables are backuped?

If you use sqlite, the database file is backuped because the `var/data` directory will be backuped by the plugin.

If you use mysql/mariadb the plugin will recognize it by reading the configured database connection url.
Then it will execute a mysqldump command to create a sql dump file, which is added to the backup zip.

The mysqldump command can be configured via the standard Kimai settings page.
Per default it is
```
/usr/bin/mysqldump --user={user} --password={password} --host={host} --port={port} --single-transaction --force {database}
```

On a windows system with XAMPP as webserver the command could look like this
```
C:\xampp\mysql\bin\mysqldump --user={user} --password={password} --host={host} --port={port} --single-transaction --force {database}
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
With one click you can restore your system to the state it had when the backup was created.
Caution: All database entries created between the backup and now will get lost. Your Kimai version is not affected from the restore. If the backup was not created in the same version were it is restored, this may lead to inconsistencies.

Files contained in the backup may overwrite already existing files.

## Scheduled backups via command line or cronjob
There is a command to also trigger automated backups. 
Example for a backup every Sunday at 4am could be:
```0 4 * * SUN php /var/www/kimai2/bin/console EasyBackup:backup > /home/YourUsername/Documents/EasyBackupCron.log```

Maybe you need to specify a absolute path to php on your environment, e.g. `/usr/bin/php`.
Make sure to also set the right path to your kimai2 location and your .log file.

If you don't need a log after successfully setting up your cronjob, you can use `> /dev/null` as output.

Give [https://crontab.guru/](https://crontab.guru/) a try if you struggle how to define the right time syntax for your cronjob.
Some also need to specify the user before the php command.

## If your receive the warning: git 	fatal: detected dubious ownership in repository
Execute this git command:
```git config --system --add safe.directory /var/www/kimai/var/plugins/EasyBackupBundle```

## Common errors and their solution
Fore some issues in older versions, I have recorded some possible solutions on a new wiki page.
[Wiki page: Common-errors-and-their-solution](https://github.com/mxgross/EasyBackupBundle/wiki/Common-errors-and-their-solution)

