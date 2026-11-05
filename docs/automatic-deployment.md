# GitHub automatic deployment

Pushes to `AmirMehrabi/Aviato` branch `master` deploy through an independent receiver on production. GitHub signatures are validated with HMAC SHA256 before accepting requests. Other branches, deletions and other events are ignored. Requests are persisted and coalesced, deployments run serially, and failed deployments retry after five minutes. The normal Deployer health check and rollback remain in use.

Add a repository webhook in GitHub Settings → Webhooks:

- Payload URL: `https://aviato.ir/hooks/github/deploy`
- Content type: `application/json`
- Secret: retrieve on production with `sudo cat /etc/aviato-deploy/secret` (do not commit it).
- SSL verification: enabled.
- Events: just push.
- Active: enabled.

Logs: `sudo journalctl -u aviato-deploy -f`. Deployment tooling checkout: `/opt/aviato-deployer`. State: `/var/lib/aviato-deploy`.

`composer deploy` remains available from a clean local master matching origin/master as a manual backup. Avoid starting it during a webhook deployment; Deployer's deployment lock protects against simultaneous releases. To pause automatic deployments use `sudo systemctl stop aviato-deploy`.

If the public hostname returns a browser challenge (HTTP 467), exempt `/hooks/github/deploy` from the CDN/WAF browser challenge before testing GitHub deliveries. Signature validation remains required.
