# Supervisor setup for Aviato

Use Redis for queues and Horizon for worker management:

- `QUEUE_CONNECTION=redis`
- `REDIS_CLIENT=phpredis`
- `HORIZON_ALLOWED_EMAILS=admin@aviato.ir,ops@aviato.ir`

Install Supervisor on Ubuntu/Debian:

```bash
sudo apt-get update
sudo apt-get install -y supervisor
sudo systemctl enable supervisor
sudo systemctl start supervisor
```

Copy the program files into `/etc/supervisor/conf.d/`:

- `aviato-horizon.conf`
- `aviato-scheduler.conf`

The older per-queue worker files in `ops/supervisor/` are now legacy and are
not meant to be installed alongside Horizon.

Then reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart aviato-horizon:*
sudo supervisorctl restart aviato-scheduler:*
```

Horizon command:

```bash
/usr/bin/php /var/www/html/aviato/current/artisan horizon
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
