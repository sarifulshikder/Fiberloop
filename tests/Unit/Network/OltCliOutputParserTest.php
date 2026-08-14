<?php

namespace Tests\Unit\Network;

use App\Services\Network\OltCliOutputParser;

it('parses VSOL EPON onu info table', function () {
    $output = <<<TXT
Index F/S/P  ONU ID  SN           State
0     0/1/1  1       VSOL00000001 ONLINE
1     0/1/1  2       VSOL00000002 OFFLINE
2     0/1/2  1       VSOL00000003 ONLINE
TXT;

    $onus = OltCliOutputParser::parseOnuTable($output);

    expect($onus)->toHaveCount(3)
        ->and($onus[0]->ponPort)->toBe(1)
        ->and($onus[0]->ponPortName)->toBe('0/1/1')
        ->and($onus[0]->onuId)->toBe('1')
        ->and($onus[0]->serialNumber)->toBe('VSOL00000001')
        ->and($onus[0]->isOnline)->toBeTrue()
        ->and($onus[1]->isOnline)->toBeFalse()
        ->and($onus[2]->ponPort)->toBe(2);
});

it('parses the real VSOL `show onu status` table', function () {
    $output = <<<TXT
ONU-ID  Status  MAC Address  Distance(m)  RTT(TQ)  LastRegTime  LastDeregTime  LastDeregReason  AliveTime  Upgrade
EPON0/1:1  offline  a2:3e:05:23:83:f0  779  571  2026/08/13 16:17:23  2026/08/14 01:53:51  Power Off  09:36:28  N/A
EPON0/1:20  online  c0:7e:40:69:07:23  52  13  2026/08/13 16:17:23  N/A  N/A  N/A  N/A
EPON0/1:3  offline  a2:3e:05:2a:9b:10  350  240  2026/08/12 20:41:07  2026/08/14 00:12:34  Timeout  08:00:00  N/A
TXT;

    $onus = OltCliOutputParser::parseOnuTable($output, '0/1');

    expect($onus)->toHaveCount(3)
        ->and($onus[0]->ponPort)->toBe(1)
        ->and($onus[0]->ponPortName)->toBe('0/1')
        ->and($onus[0]->onuId)->toBe('1')
        ->and($onus[0]->macAddress)->toBe('A2:3E:05:23:83:F0')
        ->and($onus[0]->serialNumber)->toBe('A2:3E:05:23:83:F0')
        ->and($onus[0]->isOnline)->toBeFalse()
        ->and($onus[1]->isOnline)->toBeTrue()
        ->and($onus[1]->macAddress)->toBe('C0:7E:40:69:07:23')
        // "Timeout" must not leak into the serial number.
        ->and($onus[2]->serialNumber)->toBe('A2:3E:05:2A:9B:10')
        ->and($onus[2]->macAddress)->toBe('A2:3E:05:2A:9B:10');
});

it('parses the real VSOL `show onu opm-diag` table with multi-word columns', function () {
    $output = <<<TXT
ONU-ID  Temperature(C)  Supply Voltage(V)  TX Bias Current(mA)  TX Power(dBm)  RX Power(dBm)
EPON0/1:20  52.25  3.33  9.69  2.20  -27.45
EPON0/1:1  51.80  3.31  8.94  2.10  -30.12
EPON0/1:3  53.10  3.34  9.11  1.95  -26.80
TXT;

    $rows = OltCliOutputParser::parseOpticalTable($output, '0/1');

    expect($rows)->toHaveCount(3)
        ->and($rows['1|20']['rx_power_dbm'])->toBe(-27.45)
        ->and($rows['1|20']['tx_power_dbm'])->toBe(2.2)
        ->and($rows['1|20']['temperature_c'])->toBe(52.25)
        ->and($rows['1|20']['voltage_v'])->toBe(3.33)
        ->and($rows['1|20']['tx_bias_ma'])->toBe(9.69)
        ->and($rows['1|1']['rx_power_dbm'])->toBe(-30.12)
        ->and($rows['1|3']['rx_power_dbm'])->toBe(-26.8);
});

it('parses VSOL optical power table with column order preserved', function () {
    $output = <<<TXT
F/S/P  ONU-ID  Temp(°C)  Voltage(V)  RxPower(dBm)  TxPower(dBm)
0/1/1  1       41.2      3.31        -21.50        2.40
0/1/1  2       42.1      3.32        -18.70        2.50
TXT;

    $rows = OltCliOutputParser::parseOpticalTable($output);

    expect($rows)->toHaveCount(2)
        ->and($rows['1|1']['rx_power_dbm'])->toBe(-21.5)
        ->and($rows['1|1']['tx_power_dbm'])->toBe(2.4)
        ->and($rows['1|1']['temperature_c'])->toBe(41.2)
        ->and($rows['1|1']['voltage_v'])->toBe(3.31)
        ->and($rows['1|2']['rx_power_dbm'])->toBe(-18.7);
});

it('parses BDCOM optical table with rx/tx first column order', function () {
    $output = <<<TXT
port      onu-id  rx-power(dbm)  tx-power(dbm)  temp(°C)  voltage(v)
0/1/1     1       -22.50         2.30           41.0      3.31
0/1/1     2       -19.30         2.40           42.5      3.32
TXT;

    $rows = OltCliOutputParser::parseOpticalTable($output);

    expect($rows)->toHaveCount(2)
        ->and($rows['1|1']['rx_power_dbm'])->toBe(-22.5)
        ->and($rows['1|1']['tx_power_dbm'])->toBe(2.3)
        ->and($rows['1|2']['rx_power_dbm'])->toBe(-19.3);
});

it('parses Huawei ont info table', function () {
    $output = <<<TXT
  F/S/P   ONT-ID  SN             State      Description
  0/1/0   1       HWTC12345678   online     VIP customer
  0/1/0   2       HWTC12345679   offline    -
TXT;

    $onus = OltCliOutputParser::parseOnuTable($output);

    expect($onus)->toHaveCount(2)
        ->and($onus[0]->ponPort)->toBe(0)
        ->and($onus[0]->ponPortName)->toBe('0/1/0')
        ->and($onus[0]->onuId)->toBe('1')
        ->and($onus[0]->serialNumber)->toBe('HWTC12345678')
        ->and($onus[1]->isOnline)->toBeFalse();
});

it('parses Huawei optical table', function () {
    $output = <<<TXT
  F/S/P   ONT-ID  RxPower(dBm)  TxPower(dBm)  Temp(°C)  Voltage(V)
  0/1/0   1       -24.53        2.55          41.2      3.31
  0/1/0   2       -22.10        2.10          40.1      3.30
TXT;

    $rows = OltCliOutputParser::parseOpticalTable($output, '0/1/0');

    expect($rows['0|1']['rx_power_dbm'])->toBe(-24.53)
        ->and($rows['0|1']['tx_power_dbm'])->toBe(2.55)
        ->and($rows['0|2']['rx_power_dbm'])->toBe(-22.1);
});

it('parses ZTE gpon-onu name tokens with embedded port and id', function () {
    $output = <<<TXT
  ONU Name            SN           State
  gpon-onu_1/1/1:1    ZTEG12345678 online
  gpon-onu_1/1/1:2    ZTEG12345679 offline
TXT;

    $onus = OltCliOutputParser::parseOnuTable($output);

    expect($onus)->toHaveCount(2)
        ->and($onus[0]->ponPort)->toBe(1)
        ->and($onus[0]->onuId)->toBe('1')
        ->and($onus[0]->serialNumber)->toBe('ZTEG12345678')
        ->and($onus[1]->isOnline)->toBeFalse();
});

it('parses ZTE optical table with multi-word header columns', function () {
    $output = <<<TXT
ONU Name                 Rx Power(dBm)  Tx Power(dBm)  Temp(°C)  Voltage(V)  Bias(mA)
gpon-onu_1/1/1:1         -22.35         2.41           42.3      3.31        12.4
gpon-onu_1/1/1:2         -21.10         2.55           41.9      3.32        13.1
TXT;

    $rows = OltCliOutputParser::parseOpticalTable($output);

    expect($rows)->toHaveCount(2)
        ->and($rows['1|1']['rx_power_dbm'])->toBe(-22.35)
        ->and($rows['1|1']['tx_power_dbm'])->toBe(2.41)
        ->and($rows['1|1']['temperature_c'])->toBe(42.3)
        ->and($rows['1|1']['voltage_v'])->toBe(3.31)
        ->and($rows['1|1']['tx_bias_ma'])->toBe(12.4)
        ->and($rows['1|2']['rx_power_dbm'])->toBe(-21.1);
});

it('normalizes mac addresses from hex strings', function () {
    expect(OltCliOutputParser::normalizeMac('00 3A 7D 96 7F 0A'))->toBe('00:3A:7D:96:7F:0A')
        ->and(OltCliOutputParser::normalizeMac('00-3A-7D-96-7F-0A'))->toBe('00:3A:7D:96:7F:0A')
        ->and(OltCliOutputParser::normalizeMac('00:3A:7D:96:7F:0A'))->toBe('00:3A:7D:96:7F:0A');
});

it('extracts the trailing integer from an f/s/p identifier', function () {
    expect(OltCliOutputParser::portInt('0/1/7'))->toBe(7)
        ->and(OltCliOutputParser::portInt('1/2/0'))->toBe(0);
});

it('parses the real VSOL `show onu basic-info` table', function () {
    $output = <<<TXT
ONU-ID      VendorID  Model     ID            hwVer     SwVer               Type                Interface Type
------      --------  -----     --            -----     --------            ---------           ---------
EPON0/1:1   EDBC      EPON      A23E052383F0  393.A     V3R017C10S125       HGU                 1FE+1POTS
EPON0/1:5   VSOL      D401      8014A8D38A58  V2.8S     V6.0.4P1T8          SFU                 1GE
EPON0/1:3   00000000  00000000  C07E405500C5  V1.2      V1.0.5              SFU                 4FE
TXT;

    $rows = OltCliOutputParser::parseBasicInfoTable($output);

    expect($rows)->toHaveCount(3)
        ->and($rows['1|1']['vendor_id'])->toBe('EDBC')
        ->and($rows['1|1']['firmware_version'])->toBe('V3R017C10S125')
        ->and($rows['1|1']['hardware_version'])->toBe('393.A')
        ->and($rows['1|1']['ONU_type'])->toBe('HGU')
        ->and($rows['1|1']['interface_type'])->toBe('1FE+1POTS')
        ->and($rows['1|1']['mac_address'])->toBe('A2:3E:05:23:83:F0')
        ->and($rows['1|5']['vendor_id'])->toBe('VSOL')
        ->and($rows['1|5']['ONU_type'])->toBe('SFU')
        ->and($rows['1|3']['ONU_type'])->toBe('SFU');
});

it('parses VSOL onu descriptions from the running config', function () {
    $output = <<<TXT

Current configuration:
!
hostname epon-olt
!
interface epon 0/1
onu 1 description Joshim_shop
onu 2 description Ismail_1no
onu 3 ctc eth 1 vlan pvid 1 pri 0
interface epon 0/2
onu 1 description Isa_Dhalipara
onu 2 description Jannatul
interface epon 0/4
onu 1 description idc082@shohidul
TXT;

    $rows = OltCliOutputParser::parseDescriptionsTable($output);

    expect($rows)->toHaveCount(5)
        ->and($rows['1|1'])->toBe('Joshim_shop')
        ->and($rows['1|2'])->toBe('Ismail_1no')
        ->and($rows['2|1'])->toBe('Isa_Dhalipara')
        ->and($rows['2|2'])->toBe('Jannatul')
        ->and($rows['4|1'])->toBe('idc082@shohidul')
        ->and($rows)->not->toHaveKey('1|3');
});

it('parses VSOL `show pon info` into admin and link status', function () {
    $output = <<<TXT

****************EPON0/1****************
 PON Link status     : enable
 PON Admin Status    : enable
 Encryption Mode     : disable
 Encryption Key Time : 0(ms)
 PON MAX RTT         : 14500(TQ)
TXT;

    $info = OltCliOutputParser::parsePonInfo($output);

    expect($info['admin_status'])->toBe(1)
        ->and($info['oper_status'])->toBe(1);
});

it('maps a disabled pon link to status 2', function () {
    $output = <<<TXT
 PON Link status     : disable
 PON Admin Status    : enable
TXT;

    $info = OltCliOutputParser::parsePonInfo($output);

    expect($info['admin_status'])->toBe(1)
        ->and($info['oper_status'])->toBe(2);
});

it('parses a live VSOL gigabitethernet interface block', function () {
    $output = <<<TXT
Interface gigabitEthernet0/1's information.
    GigabitEthernet0/1 current state : Up
    Description: UpR-Link
    Hardware Type is 10 Gigabit Ethernet, Hardware address is 0:0:0:0:0:0
    The Maximum Transmit Unit is 1500
    Media type is twisted pair, loopback not set
    Port hardware type is 10 Gigabit,SFP+
    Current link speed: 10000Mbps,  Current link mode: full-duplex
TXT;

    $info = OltCliOutputParser::parseGigabitethernetInfo($output);

    expect($info['state'])->toBe(1)
        ->and($info['description'])->toBe('UpR-Link')
        ->and($info['hardware_type'])->toBe('10 Gigabit Ethernet')
        ->and($info['high_speed'])->toBe(10000)
        ->and($info['mtu'])->toBe(1500);
});

it('parses a down gigabitethernet port without a description', function () {
    $output = <<<TXT
Interface gigabitEthernet0/4's information.
    GigabitEthernet0/4 current state : Down
    Hardware Type is 10 Gigabit Ethernet, Hardware address is 0:0:0:0:0:0
    The Maximum Transmit Unit is 1500
    Current link speed: 10000Mbps,  Current link mode: full-duplex
TXT;

    $info = OltCliOutputParser::parseGigabitethernetInfo($output);

    expect($info['state'])->toBe(2)
        ->and($info['description'])->toBeNull()
        ->and($info['high_speed'])->toBe(10000);
});
