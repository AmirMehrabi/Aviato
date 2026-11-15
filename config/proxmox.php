<?php

return [
    // Exact former node names, grouped by Proxmox server ID. Targets must exist in the live cluster.
    'deletion_node_aliases' => json_decode((string) env('PROXMOX_DELETION_NODE_ALIASES', ''), true) ?? [],

    'connect_timeout' => (int) env('PROXMOX_CONNECT_TIMEOUT', 15),
    'request_timeout' => (int) env('PROXMOX_REQUEST_TIMEOUT', 30),
];
