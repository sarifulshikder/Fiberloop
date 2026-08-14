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
    'default_gigabit_ports' => (int) env('OLT_DEFAULT_GIGABIT_PORTS', 8),

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
            // Gigabit/uplink port status for the "Poll Ports" action. The VSOL
            // shows one block per `gigabitethernet 0/X` port.
            'gigabit_info' => 'show interface gigabitethernet %s',
        ],
        'bdcom' => [
            // BDCOM P33xx EPON OLT (Cisco-like CLI). Verified against a live
            // P3310C on 2026-08-14. %s = PON port number ("1".."4"), used as
            // the `epon0/%s` interface suffix.
            'onu_info' => 'show epon onu-information interface epon0/%s',
            'onu_optical' => 'show epon onu-ctc-optical-transceiver-diagnosis interface epon0/%s',
            'onu_gpon_info' => 'show gpon onu info',
            'onu_gpon_optical' => 'show gpon onu optical-info',
            // ONU descriptions live in the running config under each per-ONU
            // interface (`interface EPON0/1:1` ... `description <name>` /
            // `epon onu description <name>`).
            'onu_descriptions' => 'show running-config',
            // Port status for the "Poll Ports" action. The BDCOM OLT exposes
            // no SNMP service on its management IP (the SNMP service there
            // belongs to the upstream VSOL), so ports are polled over CLI.
            'pon_info' => 'show interface epon0/%s',
            'gigabit_info' => 'show interface GigaEthernet0/%s',
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
