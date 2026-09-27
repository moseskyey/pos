# Deploying DukaPOS

This guide installs DukaPOS on a fresh **Ubuntu 22.04 / 24.04** VPS. The stack is Nginx, PHP 8.4-FPM, MySQL 8
(MariaDB 10.11+ works too),
Supervisor for the queue worker, cron for the scheduler, and Let's Encrypt for SSL. Replace `pos.example.co.tz`
with your domain, and `CHANGE_ME` with strong passwords.

A 1 vCPU / 2 GB RAM server comfortably runs several branches.

---

## 1. Server packages

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y software-properties-common curl git unzip nginx mysql-server supervisor certbot python3-certbot-nginx

# PHP 8.4 (required by the locked Symfony 8 / spatie packages; Ubuntu ships 8.3, so add the ondrej/php PPA)
sudo add-apt-repository -y ppa:ondrej/php && sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
    php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# Node.js 22 (only needed to build CSS/JS)
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install -y nodejs
```

Set the server timezone so logs and cron match your shop's hours:

```bash
sudo timedatectl set-timezone Africa/Dar_es_Salaam
```

## 2. Database

DukaPOS keeps one **central** database (businesses, plans, subscriptions, platform admins) and gives every
business **its own database**, created automatically when the business signs up (`dukapos_t1`, `dukapos_t2`, …).
The database user therefore needs permission to create databases with that prefix:

```bash
sudo mysql <<'SQL'
CREATE DATABASE dukapos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dukapos'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON dukapos.* TO 'dukapos'@'localhost';
GRANT ALL PRIVILEGES ON `dukapos\_t%`.* TO 'dukapos'@'localhost';   -- one database per business
FLUSH PRIVILEGES;
SQL
```

The prefix is `TENANT_DB_PREFIX` in `.env` (default `dukapos_t`). If you change it, change the `GRANT` to match.

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

Migrate the central database, create your **platform admin** account (you will be prompted), then build the assets:

```bash
php artisan migrate --force
php artisan db:seed --force                # starter subscription plans (editable later)
php artisan dukapos:create-admin           # the platform admin: manages every business and subscription
php artisan storage:link

npm ci && npm run build

php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache

sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Then:
1. Sign in at `https://pos.example.co.tz/admin` with the admin account.
2. Under **Settings**, set the trial length, grace period, support contacts and (optionally) your FastLipa account
   for subscription payments. Under **Plans**, adjust prices and limits.
3. Businesses sign up themselves at `/register`, or you add one under **Businesses → Add business**
   (or `php artisan tenants:create`). Each gets its own database, a first branch and till, and an owner login.

Business owners then set up the rest in their own account: **Settings → Business profile** (name, TIN/VRN, logo),
**Branches**, **Users**.

> Want to try it with sample data first? Set `APP_ENV=demo` and run `php artisan migrate:fresh --seed`. It creates
> the admin `admin@dukapos.test` (password `password`) and a demo business with a year of demo data.
> Never do this on a live database.

### Upgrading an install from before multi-business

Just run `./deploy.sh` (section 12). On the first deploy it runs `php artisan tenants:adopt --auto`, which turns the
existing shop into business #1 **in place**: nothing is moved or copied, its users sign in as before, printed
receipt QR codes and old WhatsApp links keep working, and so does the old FastLipa callback URL. The shop starts on a
normal trial; give it a paid period from **Admin → Businesses → Record a payment** (or adopt manually with
`php artisan tenants:adopt --paid-until=2027-12-31`). Then create your admin with `php artisan dukapos:create-admin`.

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

    # The service worker must always be re-checked so till updates reach every device.
    location = /sw.js {
        add_header Cache-Control "no-cache";
        access_log off;
    }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
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

The scheduler runs (per-business jobs run once for every business through `tenants:run`):

| Time | Job |
|---|---|
| every minute | `tenants:run dukapos:reconcile-payments`: re-checks each business's mobile money payments stuck in pending |
| every minute | `billing:reconcile`: re-checks subscription payments to the platform |
| 01:00 | `tenants:run backup:clean`: removes business backups older than `BACKUP_KEEP_DAYS` |
| 01:30 | `tenants:run "backup:run --only-db"`: nightly backup of every business database |
| 03:00 | `platform:backup`: nightly backup of the central database |
| 06:00 | `dukapos:recurring-expenses`: posts rent, salaries, LUKU and similar when they fall due |
| 07:00 | `dukapos:stock-alerts`: low-stock and expiry alerts |
| 09:00 | `platform:backup --monitor`: alerts if the latest platform backup is too old |
| 09:15 | `billing:reminders`: tells owners 7, 3 and 1 days before their trial or subscription ends |
| Monday 08:00 | `dukapos:debt-alerts`: overdue customer debts |

Stock, expense and debt jobs skip businesses whose subscription has ended.

Useful commands:

```bash
php artisan tenants:migrate --force                      # migrate every business database (deploy.sh does this)
php artisan tenants:run "dukapos:stock-alerts" --tenant=4  # any command, for one or all businesses
php artisan tenants:create                               # add a business from the command line
```

## 8. Backups

Each business has its own backups under `storage/app/private/tenants/<id>/dukapos-<id>/`, which its owner can
download from **Settings → Backups**. A business's backup holds only its own database and files. The central
database is backed up to `storage/app/private/dukapos-platform/`. Backups need the database dump tool on the server:

```bash
sudo apt install -y mysql-client     # provides mysqldump (sqlite3 if you run on SQLite)
```

**Keep a copy off the server.** Either:
- add an S3-compatible disk (AWS S3, DigitalOcean Spaces or Wasabi): fill in the `AWS_*` values and set `BACKUP_DISKS=local,s3`, or
- download the latest backup from the Backups page every week.

To restore one business (its database name is shown on its page in the admin panel):

```bash
unzip 2026-01-01-01-30-00.zip -d /tmp/restore
mysql -u dukapos -p dukapos_t4 < /tmp/restore/db-dumps/*.sql
```

Restore the central database the same way into `dukapos` from a `dukapos-platform` backup.

## 9. Mobile money & SMS

- **FastLipa**:
  1. Under **Settings → Payment methods**, enter your FastLipa secret token as the API key, keep the base URL
     `https://api.fastlipa.com`, and set the gateway to FastLipa.
  2. DukaPOS sends the business's own webhook URL (`https://pos.example.co.tz/api/payments/callback/fastlipa/<business id>`,
     shown on the same settings page) with every payment request. If the FastLipa dashboard also has a webhook
     setting, use that URL there. The old URL without the business ID still works for a business adopted from a
     single-shop install.
  3. Make sure the queue worker (§6) and the scheduler (§7) are running. Webhooks are acknowledged immediately and
     then confirmed against FastLipa's status API on the queue, so a forged webhook can never mark a payment paid.
  4. FastLipa can report a payment as **failed** and then **completed** a few minutes later. DukaPOS keeps
     re-checking failed payments for `FASTLIPA_RECHECK_FAILED_MINUTES` (default 30). If the money arrives after
     the cashier gave up, the payment is marked completed and the cashier and branch managers get an alert, so
     the sale can be completed or the customer refunded.
  5. Optionally restrict callbacks to FastLipa's IP addresses with `FASTLIPA_ALLOWED_IPS` in `.env`.
- **Beem SMS**: enter the API key, secret and approved sender ID under **Settings → SMS, alerts & fiscal**.

Callbacks are logged in the `payment_callbacks` table. To replay one: `php artisan payments:replay-callback {id}`.

## 10. Subscriptions and the platform admin

- **Plans, trials and grace:** set under **Admin → Plans** and **Admin → Settings**. A business can work during its
  trial, while paid up, and for the grace period after expiry; after that every page leads to its **Subscription**
  page until it renews. Nothing is ever deleted when a subscription ends.
- **Paying by mobile money:** enter the platform's own FastLipa API key under **Admin → Settings → Subscription
  payments** and switch it on. Owners then pay from **Settings → Subscription** with an STK push. Webhooks go to
  `https://pos.example.co.tz/api/billing/callback/fastlipa`, are confirmed against FastLipa's status API before they
  count, and `billing:reconcile` picks up anything a webhook missed.
- **Cash or bank payments:** record them on the business's page (**Record a payment**). The subscription is extended
  from the current paid-until date, and the owner can download the invoice.
- **Support:** from a business's page you can extend access, change the plan, suspend or restore it, reset a user's
  password, deactivate users, or **Login as owner** to see what they see (logged in both activity logs).
- **Plan limits** (branches, active users, products) apply as soon as a plan is assigned; leave a limit empty for
  unlimited.
- More admins: **Admin → Admins**. Support admins manage businesses and payments; only super admins can change
  settings, FastLipa keys, admins and refunds.

## 11. Offline till, receipt printers and US dollars

- **HTTPS is required** for the offline till (service workers) and for direct printing (WebUSB/WebSerial).
  Section 5 covers this. On a local network without a domain, use the browser on the same machine through
  `http://localhost`, which browsers treat as secure.
- **Offline till:** each till must open the POS once while online, which downloads the product list. The list
  refreshes every time the till is online. If the connection drops, the POS shows **Open offline till**.
  Sales made offline sync by themselves when the connection returns. Managers can find them under
  **Sales → filter "Offline sales" → Needs review**.
- **Receipt printer:**
  1. Set Settings → Receipts → Printing to *Direct to thermal printer (ESC/POS)*.
  2. On each till, in Chrome or Edge, click **Connect printer** on the POS screen and choose the USB or serial printer.
  3. The cash drawer must be plugged into the printer's RJ11 drawer port.
  4. On Windows, USB printers that already use a vendor driver may need the WinUSB driver (Zadig) before
     WebUSB can reach them. Serial/COM printers work as they are.
- **US dollars:** set the rate under Settings → Currency & tax, then turn on *Cash (USD)* under Payment methods.
  Update the rate whenever it changes; each payment stores the rate it used.

## 12. Updating

Use `deploy.sh` from the project root to update the app. It enables maintenance mode, pulls the code, installs
dependencies, migrates the central database and every business database, builds assets, rebuilds the caches and
restarts the queue workers:

```bash
cd /var/www/dukapos && ./deploy.sh            # deploys the current branch
./deploy.sh main                              # or a specific branch
```

## 13. Hardening checklist

- `APP_DEBUG=false` and `APP_ENV=production`. Lazy-loading guards and debug output are then off.
- Keep `.env` readable only by the deploy user and `www-data`: `chmod 640 .env`.
- Firewall: `sudo ufw allow OpenSSH && sudo ufw allow 'Nginx Full' && sudo ufw enable`.
- Ask every owner and manager to enable two-factor authentication (My profile → Two-factor authentication) and to set a PIN.
- Give platform admins long, unique passwords (at least 10 characters), keep super admins to a minimum, and review
  **Admin → Activity log** regularly.
- Review **Settings → Activity log** regularly for voids, price overrides and below-cost sales.
- Test a restore from backup every quarter.
