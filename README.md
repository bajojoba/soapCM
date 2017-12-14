# soapCM
PHP script to gather Docsis Cable Modem information via SNMP. Easy to connect to any other web service as it has NBI in SoAP.

> No DHCP information needed.
> Stand-alone service.
> Easy applicable.


# Requirements
- PHP-SOAP
- PHP-JSON
- PHP-SNMP
- APACHE Server with SNMP access to the CMTS and CM
- SoapUi (for testing)

# SoAP Messages
##### getCMTSParams
- When searching for cable modem this is always the first step. It returns the information containing US, Cable modem IP...
##### getCMDsVals
- IP and CM SNMP community should return the downstream information on all channels
##### getCMDsVals
- IP and CM SNMP community should return the upstream information on all channels. Gathered from the Cable Modem. All other infromation are returned via getCMTSParams.
##### getCMProps
- IP and CM SNMP community should return basic Cable Modem infromation, uptime, vendor, firmware...
#### getCMFDBRecords
- CM IP, CM SNMP community and CMTSid returned via getCMTSParams, should return Forwarding Database Records. (IPs and MAC addresses seen on the WAN side of CM)
#### getDeviceIP
- Input of this message is MAC address. It will walk through all of the CMTSes and return the IP address of requested device.
#### getUSDetail
- CMTSid and PTR returned via getCMTSParams, should return detailed upstream parameters directly from the CMTS.

# Instalation
1. Copy docsisWs.php and cmtses.json to your apache directory
```sh
    $cp docsisWs.php /var/www/html/
    $cp cmtses.json /var/www/html/
```
2. Create directory ws in your apache directory
```sh
    $mkdir /var/www/html/ws
```
3. Copy ws/DocsisCMDiag.wsdl to your apache ws/ directory
```sh
    $cp ws/DocsisCMDiag.wsdl /var/www/html/ws
```
4. Edit cmtses.json config file and add your cmtses (vendor Cisco,Casa,Arris and Motorola are suppored)
5. Edit docsisWs.php and ws/DocsisCMDiag.wsdl
``` sh
    Search for modemdiag.cmtsnet.local and replase it with your apache server hostname/ip
```
# Todo
It's written very long time a go for php5.3 so the best thing would be to port it to python or at least new version of PHP7.
- cablemodem reset
- cablemodem upgrade

# Remarks
I'm not programer but a docsis engineer.
This only a partion of a much greater project. The current version that ***I can't release here*** includes Wireless scan, set/get WLAN parameters, get LAN hosts, get WiFi clients with (RSSI signal), get MTA status...
