### EasyBackup

A Kimai 2 plugin, which allows you to backup your environment with a single click or via cronjob / command line.

If you like the plugin, please feel free to donate me a coffee. I use my precious free time to operate and improve the plugin and don't earn any money from it.

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/donate?hosted_button_id=XQD3PMPANZNG4)

Thanks for one coffee in 4 years now. This plugin is not maintained any longer, feel free to fork and improve it by yourself.

After the installation a new menu entry `EasyBackup` is created. There you can create a new backup
by clicking the `Create Backup` button. Afterwards all created backups will be listed at the page
and you can delete or download the created backup as zip file.

![Kimai2 Easy Backup Plugin Bundle](https://github.com/mxgross/EasyBackupBundle/blob/main/screenshot.jpg?raw=true)

### 🔧 Local Docker Environment (for testing/development)

A ready-to-use Docker setup for Kimai + this plugin is available in the [`docker/`](./docker/) folder.

```bash
cd docker
docker-compose build
docker-compose up -d
```

Test execution
`docker exec -u www-data -w /opt/kimai kimai ./vendor/bin/phpunit --testdox var/plugins/EasyBackupBundle`


### Installation

```markdown
# EasyBackup

EasyBackup is a Kimai 2 plugin that lets you back up your Kimai installation with a single click or via cron / the command line.

If you find the plugin useful, feel free to buy me a coffee — I maintain this project in my spare time. Note: this plugin is no longer actively maintained; contributions and forks are welcome.

[![paypal](https://www.paypalobjects.com/en_US/i/btn/btn_donate_LG.gif)](https://www.paypal.com/donate?hosted_button_id=XQD3PMPANZNG4)

After installation a new menu entry `EasyBackup` is added. From there you can create a backup using the `Create Backup` button. All created backups are listed on the page and can be downloaded (ZIP) or removed.

![Kimai2 Easy Backup Plugin Bundle](https://github.com/mxgross/EasyBackupBundle/blob/main/screenshot.jpg?raw=true)

### 🔧 Local Docker Environment (for testing / development)

This repository includes a Docker setup to run Kimai with the plugin mounted for local development.

Build and start the containers from the repository root:

```bash
docker compose build --no-cache
docker compose up -d
```

Run plugin tests inside the running Kimai container:

```bash
docker exec -u www-data -w /opt/kimai kimai ./vendor/bin/phpunit --testdox var/plugins/EasyBackupBundle
```

---

## Installation

Clone the plugin into your Kimai installation's `var/plugins/` directory:

```bash
cd /path/to/kimai/var/plugins/
git clone https://github.com/mxgross/EasyBackupBundle.git
```

Branch selection:
- For Kimai < 2.0.0 use the `master` branch.
- For Kimai >= 2.0.0 use the `main` branch.

Set appropriate file permissions for the webserver user (example):

```bash
sudo chown -R :www-data .
sudo chmod -R g+r .
sudo chmod -R g+rw var/
sudo chmod -R g+rw public/avatars/
sudo chmod -R o+rw var/plugins/EasyBackupBundle
```

Rebuild the application cache:

```bash
cd /path/to/kimai
bin/console cache:clear
bin/console cache:warmup
```

You can also [download the ZIP archive](https://github.com/mxgross/EasyBackupBundle/archive/main.zip) and upload the plugin folder via FTP.

If you have questions or find issues, please open an issue or contribute via pull requests.

## Storage

By default backups are created as ZIP files under `var/easy_backup`. Ensure the directory is writable by the webserver. The plugin intentionally does not use `var/data/` because that directory is included in the backups.

### Files included in the backup

The default files and directories that are backed up (including subdirectories) are:

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

You can edit this list in the Kimai settings page. Add one file or path per line and avoid empty lines. Paths are relative to your Kimai installation root.

![Update the paths to your needs](https://github.com/mxgross/EasyBackupBundle/blob/main/screenshot_files_and_paths_to_be_backed_up.jpg?raw=true)

The plugin also saves the Kimai version and the current Git HEAD in a `manifest.json` file that is added to the backup.

### Database backups

- For SQLite the database file is included because `var/data/` is part of the backup.
- For MySQL / MariaDB the plugin detects the connection from `DATABASE_URL` and runs `mysqldump` to produce a SQL dump that is then added to the ZIP.

The default `mysqldump` command (configurable in Kimai settings) is:

```
/usr/bin/mysqldump --user={user} --password={password} --host={host} --port={port} --single-transaction --force {database}
```

On Windows/XAMPP it might look like:

```
C:\xampp\mysql\bin\mysqldump --user={user} --password={password} --host={host} --port={port} --single-transaction --force {database}
```

The placeholders in curly braces are replaced from your `DATABASE_URL` configuration.

Example `DATABASE_URL`:

```
# DATABASE_URL=mysql://user:password@host:port/database
# Example:
DATABASE_URL=mysql://JohnDoe:MySecret1234@127.0.0.1:3306/kimai2
```

## Permissions

The bundle adds a permission to control access to the backup UI:

- `easy_backup` — allows access to the backup screen

By default this permission is granted to users with `ROLE_SUPER_ADMIN`. Please adjust the permission settings in your user administration as needed.

## Restore

Restoring a backup will revert your system to the state at the time the backup was created. Warning: any database entries or files created after the backup will be lost. Restoring a backup from a different Kimai version may cause incompatibilities.

Files included in the backup may overwrite existing files during restore.

## Scheduled backups (cron)

You can trigger automated backups via cron. Example — every Sunday at 04:00:

```cron
0 4 * * SUN php /var/www/kimai2/bin/console EasyBackup:backup > /home/YourUsername/Documents/EasyBackupCron.log
```

You may need to use the absolute path to `php` (for example `/usr/bin/php`) and set appropriate paths for your Kimai installation and log file. If you don't need a log, redirect output to `/dev/null`.

If you need help with cron syntax, try: https://crontab.guru/

## Git "dubious ownership" warning

If you see the following Git warning:

```
fatal: detected dubious ownership in repository
```

Run:

```bash
git config --system --add safe.directory /var/www/kimai/var/plugins/EasyBackupBundle
```

## Common errors and troubleshooting

For some issues in older plugin versions a list of common problems and solutions is available on the project wiki:

[Common errors and their solution](https://github.com/mxgross/EasyBackupBundle/wiki/Common-errors-and-their-solution)

---

### 🐳 Docker Development Notes

The included Docker Compose files start a full Kimai instance with MariaDB and mount the plugin for local development.

- Plugin folder inside container: `/opt/kimai/var/plugins/EasyBackupBundle`
- Environment: `APP_ENV=dev`, `APP_DEBUG=1`
- PHPUnit is available in the environment

Run tests like this:

```bash
docker exec -u www-data -w /opt/kimai kimai ./vendor/bin/phpunit --testdox var/plugins/EasyBackupBundle
```

---
```
