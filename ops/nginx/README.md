# S3 production host

The S3 endpoint is a separate virtual host:

```text
https://s3.aviato.ir/{bucket}/{object-key}
```

The DNS record should point to the Aviato production server. On production, run the following once as a privileged operator:

```bash
sudo certbot certonly --nginx -d s3.aviato.ir
sudo install -d -o deploy -g www-data -m 2770 /var/lib/aviato/s3-data
sudo install -m 644 /var/www/html/aviato/current/ops/nginx/aviato-s3.conf.example /etc/nginx/sites-available/aviato-s3.conf
sudo ln -sfn /etc/nginx/sites-available/aviato-s3.conf /etc/nginx/sites-enabled/aviato-s3.conf
sudo nginx -t
sudo systemctl reload nginx
```

The repository also exposes the manual Deployer task:

```bash
vendor/bin/dep nginx:s3 production -vvv
```

That task installs and validates the vhost, but it assumes the certificate and `/var/lib/aviato/s3-data` already exist.
