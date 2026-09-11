# Production operations

Templates assume Linux, PHP at `/usr/bin/php`, project path `/var/www/edoc`, and service user `www-data`. Adjust these values before installation.

## systemd

Copy files from `deploy/systemd` to `/etc/systemd/system`, then run:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now edoc-queue-ocr edoc-queue-default edoc-scheduler edoc-health.timer
systemctl status edoc-queue-ocr edoc-queue-default edoc-scheduler edoc-health.timer
```

Inspect monitoring results with `journalctl -u edoc-health.service`. A non-zero exit means the database, queue latency, recent failed jobs, disk space, private storage, or production debug setting failed its threshold.

## Supervisor

Copy `deploy/supervisor/edoc.conf` to `/etc/supervisor/conf.d/edoc.conf`, adjust paths/users, then run:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

When Supervisor manages workers, set `EDOC_RUN_SCHEDULED_QUEUE_WORKER=false` to avoid duplicate workers. Run `php artisan edoc:production-health --json` from cron or the monitoring agent every five minutes.

## Release verification

Before opening traffic, run against the production `.env`:

```bash
php artisan edoc:v2-preflight --require-write-enabled --dump=/absolute/path/to/database.sql
php artisan edoc:production-health
php artisan queue:restart
```

Store database and `storage/app` backups off-host and verify their SHA-256 hashes before deployment.

## Windows development worker

The OCR worker must run separately from `php artisan serve`. Run it manually with:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/run-ocr-worker.ps1
```

For a workstation that receives OCR jobs continuously, register this script in Windows Task Scheduler at user logon and configure the task to ignore a new instance while the existing worker is running.
If Task Scheduler registration requires administrator rights, add the same hidden PowerShell command to the current user's `HKCU\Software\Microsoft\Windows\CurrentVersion\Run` startup key. The script uses a named mutex and exits immediately when another managed OCR worker is already active.
