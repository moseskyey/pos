# Deploying DukaPOS

This guide installs DukaPOS on a fresh **Ubuntu 22.04 / 24.04** VPS. The stack is Nginx, PHP 8.3-FPM, MySQL 8,
Supervisor for the queue worker, cron for the scheduler, and Let's Encrypt for SSL. Replace `pos.example.co.tz`
with your domain, and `CHANGE_ME` with strong passwords.

A 1 vCPU / 2 GB RAM server comfortably runs several branches.

---

## 1. Server packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip nginx mysql-server supervisor certbot python3-certbot-nginx

# PHP 8.3 (Ubuntu 24.04 ships it; on 22.04 add the ondrej/php PPA first)
sudo add-apt-repository -y ppa:ondrej/php   # 22.04 only
sudo apt install -y php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-gd php8.3-intl php8.3-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node.js 20 (only needed to build CSS/JS)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash - && sudo apt install -y nodejs
```

Set the server timezone so logs and cron match your shop's hours:

```bash
sudo timedatectl set-timezone Africa/Dar_es_Salaam
```

## 2. Database

```bash
sudo mysql <<'SQL'
CREATE DATABASE dukapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dukapos'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON dukapos.* TO 'dukapos'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## 3. Application

```bash
sudo mkdir -p /var/www && sudo chown $USER:www-data /var/www
cd /var/www
git clone https://github.com/moseskyey/pos.git dukapos
cd dukapos

composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Edit `.env`:

```dotenv
APP_NAME=DukaPOS
APP_ENV=production
APP_DEBUG=false
APP_URL=https://pos.example.co.tz

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dukapos
DB_USERNAME=dukapos
DB_PASSWORD=CHANGE_ME

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database

MAIL_MAILER=smtp            # for low-stock emails, password resets and backup alerts
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS="pos@example.co.tz"

BACKUP_DISKS=local          # add s3 (and AWS_* values) to keep an off-site copy
BACKUP_NOTIFY_EMAIL=owner@example.co.tz
```

Migrate, create your owner account and first branch (you will be prompted), then build the assets:

```bash
php artisan migrate --force
php artisan dukapos:create-owner          # creates roles, the first branch + till, and the owner login
php artisan storage:link

npm ci && npm run build

php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache

sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Sign in, then set up the rest in the app:
- **Settings → Business profile**: name, TIN/VRN, logo.
- **Branches**: more branches and tills.
- **Users**: your staff.

> Want to try it with sample data first? Set `APP_ENV=demo` and run `php artisan migrate:fresh --seed`.
> Never do this on a live database.

## 4. Nginx

`/etc/nginx/sites-available/dukapos`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name pos.example.co.tz;
    root /var/www/dukapos/public;

    index index.php;
    charset utf-8;
    client_max_body_size 12M;          # Excel imports up to 10 MB

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;
}
```

```bash
sudo ln -s /etc/nginx/sites-available/dukapos /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

## 5. SSL (Let's Encrypt)

```bash
sudo certbot --nginx -d pos.example.co.tz --redirect -m owner@example.co.tz --agree-tos -n
```

Certbot installs a renewal timer. You can test it with `sudo certbot renew --dry-run`.

## 6. Queue worker (Supervisor)

Report exports, SMS and notifications run on the queue. Create `/etc/supervisor/conf.d/dukapos-worker.conf`:

```ini
[program:dukapos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/dukapos/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=1800
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/dukapos/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start "dukapos-worker:*"
```

## 7. Scheduler (cron)

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/dukapos && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs:

| Time | Job |
|---|---|
| every minute | `dukapos:reconcile-payments`: re-checks mobile money payments stuck in pending |
| 01:00 | `backup:clean`: removes backups older than `BACKUP_KEEP_DAYS` |
| 01:30 | `backup:run --only-db`: nightly database backup |
| 06:00 | `dukapos:recurring-expenses`: posts rent, salaries, LUKU and similar when they fall due |
| 07:00 | `dukapos:stock-alerts`: low-stock and expiry alerts |
| 09:00 | `backup:monitor`: alerts if the latest backup is too old |
| Monday 08:00 | `dukapos:debt-alerts`: overdue customer debts |

## 8. Backups

Backups are stored under `storage/app/private/dukapos/` on the `local` disk and can be downloaded from
**Settings → Backups**. They need the database dump tool on the server:

```bash
sudo apt install -y mysql-client     # provides mysqldump (sqlite3 if you run on SQLite)
```

**Keep a copy off the server.** Either:
- add an S3-compatible disk (AWS S3, DigitalOcean Spaces or Wasabi): fill in the `AWS_*` values and set `BACKUP_DISKS=local,s3`, or
- download the latest backup from the Backups page every week.

To restore from a backup:

```bash
unzip 2026-01-01-01-30-00.zip -d /tmp/restore
mysql -u dukapos -p dukapos < /tmp/restore/db-dumps/mysql-dukapos.sql
```

## 9. Mobile money & SMS

- **FastLipa**:
  1. Enter the API key, base URL and webhook secret under **Settings → Payment methods**, and set the gateway to FastLipa.
  2. Register the callback URL `https://pos.example.co.tz/api/payments/callback/fastlipa` with FastLipa.
  3. Optionally restrict callbacks to FastLipa's IP addresses with `FASTLIPA_ALLOWED_IPS` in `.env`.
- **Beem SMS**: enter the API key, secret and approved sender ID under **Settings → SMS, alerts & fiscal**.

Callbacks are logged in the `payment_callbacks` table. To replay one: `php artisan payments:replay-callback {id}`.

## 10. Updating

Use `deploy.sh` from the project root to update the app. It enables maintenance mode, pulls the code, installs
dependencies, migrates, builds assets, rebuilds the caches and restarts the queue workers:

```bash
cd /var/www/dukapos && ./deploy.sh            # deploys the current branch
./deploy.sh main                              # or a specific branch
```

## 11. Hardening checklist

- `APP_DEBUG=false` and `APP_ENV=production`. Lazy-loading guards and debug output are then off.
- Keep `.env` readable only by the deploy user and `www-data`: `chmod 640 .env`.
- Firewall: `sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable`.
- Ask every owner and manager to enable two-factor authentication (My profile → Two-factor authentication) and to set a PIN.
- Review **Settings → Activity log** regularly for voids, price overrides and below-cost sales.
- Test a restore from backup every quarter.
