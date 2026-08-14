<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SSH CLI connection defaults
    |--------------------------------------------------------------------------
    | Timeouts used by CliTransport when talking to OLTs over SSH.
     */
    'ssh_timeout' => (int) env('OLT_SSH_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Telnet connection defaults (VSOL)
    |--------------------------------------------------------------------------
     */
    'telnet_timeout' => (int) env('OLT_TELNET_TIMEOUT', 15),
    'telnet_port' => (int) env('OLT_TELNET_PORT', 23),
    'default_pon_ports' => (int) env('OLT_DEFAULT_PON_PORTS', 4),

    /*
    |--------------------------------------------------------------------------
    | Per-vendor CLI command tables
    |--------------------------------------------------------------------------
    | These are the shell commands each OLT vendor driver issues to read ONU
    | data over SSH (or telnet, for VSOL). They are deliberately data-driven so
    | a command can be corrected against real hardware without touching driver
    | code.
    |
    | Placeholders:
    |   %s — the PON port identifier (F/S/P), only used by multi-port vendors.
    |
    | IMPORTANT: VSOL commands below were verified against a live VSOL EPON
    | V1600D ("epon olt platform (version 1.00)") on 2026-08-14. ONU commands
    | only exist inside the per-port context `interface epon 0/X`, so each
    | entry is a full navigation sequence executed over a telnet session.
     */
    'commands' => [
        'vsol' => [
            'onu_info' => "configure terminal\ninterface epon %s\nshow onu status\nexit\nexit",
            'onu_autofind' => "configure terminal\ninterface epon %s\nshow onu auto-find\nexit\nexit",
            'onu_optical' => "configure terminal\ninterface epon %s\nshow onu opm-diag\nexit\nexit",
            'onu_basic_info' => "configure terminal\ninterface epon %s\nshow onu basic-info\nexit\nexit",
            // ONU descriptions are only exposed in the running config. They are
            // grouped per PON port as `interface epon 0/X` ... `onu N description <name>`.
            'onu_descriptions' => 'show running-config',
            // PON port status for the "Poll Ports" action (VSOL has no reachable SNMP).
            'pon_info' => "configure terminal\ninterface epon %s\nshow pon info\nexit\nexit",
        ],
        'bdcom' => [
            // BDCOM P33xx / GPON-ONU style CLI (Cisco-like syntax).
            'onu_info' => 'show epon onu info',
            'onu_optical' => 'show epon onu optical-info',
            'onu_gpon_info' => 'show gpon onu info',
            'onu_gpon_optical' => 'show gpon onu optical-info',
        ],
        'huawei' => [
            // Huawei MA5600/MA5800. %s = F/S/P (e.g. 0/1/0).
            'onu_info' => 'display ont info %s all',
            'onu_optical' => 'display ont optical-info %s all',
            'onu_autofind' => 'display ont autofind all',
        ],
        'zte' => [
            // ZTE C320/C600 (GPON). %s = F/S/P (e.g. 1/1/1).
            'onu_info' => 'show gpon onu baseinfo gpon-onu_%s:all',
            'onu_uncfg' => 'show gpon onu uncfg',
            'onu_optical' => 'show gpon onu optical-info gpon-onu_%s:all',
        ],
    ],
];
