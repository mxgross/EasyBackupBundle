# 🚀 Kimai2 Docker Setup with EasyBackupBundle

This Docker setup provides a ready-to-use **Kimai2** instance, including the **EasyBackupBundle** plugin by [mxgross](https://github.com/mxgross/EasyBackupBundle).  
It also includes the `mysqldump` utility via `mariadb-client`, enabling full backup functionality out of the box.

---

## 📦 Features  

- 🕒 Pre-configured Kimai2 with Apache
- 🔌 EasyBackupBundle installed automatically
- 💾 `mysqldump` available (via `mariadb-client`)
- 🛠️ Customizable via Docker Compose
- 🔒 Separate MariaDB container for persistent data
- 📂 Docker volumes for data persistence

---

## 📁 Project Structure

```
kimai-docker/
├── docker-compose.yml              # Base Docker Compose file
├── docker-compose.override.yml     # Local development and build settings
├── Dockerfile                      # Custom Kimai image with plugin and mysqldump
└── docker-readme.md                # This documentation
```

---

## ⚙️ Setup Instructions

### 1. Clone this repository

```bash
git clone https://example.com/kimai-docker
cd kimai-docker
```

### 2. Build the custom Docker image

```bash
docker-compose build
```

### 3. Start the application

```bash
docker-compose up -d
```

### 4. Access Kimai in your browser

- URL: [http://localhost:8001](http://localhost:8001)
- Default admin login:
  - **Email:** `admin@example.com`
  - **Password:** `changeme`

---

## 🔄 Data Persistence

- `kimai_var`: Stores logs, files, and other application data
- `db_data`: Stores the MariaDB database

---

## 🧪 Useful Commands

### Check if `mysqldump` is available

```bash
docker exec -it kimai mysqldump --version
```

### Manually trigger plugin installation (optional)

```bash
docker exec -u www-data -it kimai bash -c "cd /opt/kimai && bin/console kimai:bundle:install"
```

---

## 🧩 Optional Extensions

- ⏰ Schedule automatic backups using cron jobs
- 🔐 Add SSL support using a reverse proxy (e.g., Traefik or Nginx)
- 📱 Use with mobile apps via Kimai’s REST API
- 🧩 Add more plugins via the Dockerfile

---

## 🧼 Stop and Clean Up

```bash
docker-compose down
docker volume rm kimai_var db_data  # Only if you want to fully reset the environment
```

---

## 📚 Resources

- 🌐 [Kimai2 Official Website](https://www.kimai.org/)
- 📘 [EasyBackupBundle GitHub](https://github.com/mxgross/EasyBackupBundle)
- 🐋 [Kimai Docker Image](https://hub.docker.com/r/kimai/kimai2)

---

## 🙋 Maintainer Notes

Built with ❤️ to simplify local Kimai2 development and automate backups.  
Feel free to contribute, report issues, or customize the setup for your needs.
