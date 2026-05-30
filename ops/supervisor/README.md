# Supervisor setup for Aviato

Use Redis for queues:

- `QUEUE_CONNECTION=redis`
- `REDIS_CLIENT=phpredis`

Install Supervisor on Ubuntu/Debian:

```bash
sudo apt-get update
sudo apt-get install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

Copy the program files into `/etc/supervisor/conf.d/`:

- `aviato-queue-default.conf`
- `aviato-queue-deletions.conf`
- `aviato-queue-provisioning.conf`
- `aviato-queue-backups.conf`
- `aviato-queue-upgrades.conf`
- `aviato-scheduler.conf`

Then reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart aviato-queue-default:*
sudo supervisorctl restart aviato-queue-deletions:*
sudo supervisorctl restart aviato-queue-provisioning:*
sudo supervisorctl restart aviato-queue-backups:*
sudo supervisorctl restart aviato-queue-upgrades:*
sudo supervisorctl restart aviato-scheduler:*
```

Queue worker command:

```bash
/usr/bin/php /var/www/html/aviato/current/artisan queue:work redis --queue=default --sleep=3 --tries=5 --timeout=900 --max-time=3600 --memory=256 --force
```

Scheduler command:

```bash
/usr/bin/php /var/www/html/aviato/current/artisan schedule:work --whisper
```

Dedicated queues:

- `deletions` for VM delete jobs
- `provisioning` for new VM and retry provisioning jobs
- `backups` for manual and scheduled VM backups
- `upgrades` for VM upgrade orders
