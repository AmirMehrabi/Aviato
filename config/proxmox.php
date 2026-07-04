<?php

return [
    'connect_timeout' => (int) env('PROXMOX_CONNECT_TIMEOUT', 15),
    'request_timeout' => (int) env('PROXMOX_REQUEST_TIMEOUT', 30),
];
