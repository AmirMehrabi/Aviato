# Websockify Console Proxy

Run `websockify` on each Proxmox node and expose it through the public customer
portal reverse proxy.

Laravel writes one-time token mappings to `/etc/aviato-console/tokens` over SSH.
Each token maps to the temporary local VNC port returned by Proxmox `vncproxy`.
Laravel also maintains `/etc/aviato-console/tokens.expires` and prunes expired
entries each time a new console token is published.

Example token line:

```text
random-token: 127.0.0.1:5900
```

Install on each Proxmox node:

```bash
apt update
apt install -y websockify novnc
install -d -m 700 /etc/aviato-console
touch /etc/aviato-console/tokens
chmod 600 /etc/aviato-console/tokens
```

Create an SSH key for Laravel on the app server and authorize it on each Proxmox
node. The private key must be readable by the PHP web user, usually `www-data`.

```bash
install -d -m 700 -o www-data -g www-data /var/www/.ssh
sudo -u www-data ssh-keygen -t ed25519 -f /var/www/.ssh/aviato_console -N ''
ssh-copy-id -i /var/www/.ssh/aviato_console.pub root@192.168.111.168
```

Set Laravel:

```env
CONSOLE_WEBSOCKIFY_SSH_USER=root
CONSOLE_WEBSOCKIFY_SSH_KEY=/var/www/.ssh/aviato_console
```

Test from the app server as the PHP web user:

```bash
sudo -u www-data ssh -i /var/www/.ssh/aviato_console -o BatchMode=yes -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null root@192.168.111.168 'echo ok'
```

Copy `websockify-proxmox.service.example` to:

```text
/etc/systemd/system/aviato-websockify.service
```

Then run:

```bash
systemctl daemon-reload
systemctl enable --now aviato-websockify
```

On the public web server, add one Nginx location per Proxmox server ID using
`nginx.conf.example`.
