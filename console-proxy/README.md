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
