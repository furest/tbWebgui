<?php

define('RASPI_VERSION', '1.0');
define('RASPI_CONFIG', '/etc/raspap');
define('RASPI_CONFIG_NETWORKING', RASPI_CONFIG.'/networking');
define('RASPI_ADMIN_DETAILS', RASPI_CONFIG.'/raspap.auth');
define('RASPI_WIFI_CLIENT_INTERFACE', 'wlan1');
define('RASPI_WIFI_HOTSPOT_INTERFACE', 'wlan0');
define('RASPI_HIDDEN_INTERFACES', ['eth0', 'vxlan0', 'wlan0', 'br0', 'lo', 'tun0']);
define('TWINBRIDGE_DIR', '/etc/tbClient');
define('TWINBRIDGE_SERVER_HOSTNAME', 'tfe.furest.be');

// Constants for configuration file paths.
// These are typical for default RPi installs. Modify if needed.
define('RASPI_DNSMASQ_CONFIG', '/etc/dnsmasq.conf');
define('RASPI_DNSMASQ_LEASES', '/var/lib/misc/dnsmasq.leases');
define('RASPI_HOSTAPD_CONFIG', '/etc/hostapd/hostapd.conf');
define('RASPI_DHCPCD_CONFIG', '/etc/dhcpcd.conf');
define('RASPI_WPA_SUPPLICANT_CONFIG', '/etc/wpa_supplicant/wpa_supplicant.conf');
define('RASPI_HOSTAPD_CTRL_INTERFACE', '/var/run/hostapd');
define('RASPI_WPA_CTRL_INTERFACE', '/var/run/wpa_supplicant');
define('RASPI_OPENVPN_CLIENT_CONFIG', '/etc/openvpn/client.conf');
define('RASPI_OPENVPN_SERVER_CONFIG', '/etc/openvpn/server.conf');
define('RASPI_TORPROXY_CONFIG', '/etc/tor/torrc');

// Optional services, set to true to enable.
define('RASPI_WIFICLIENT_ENABLED', true);
define('RASPI_TWINBRIDGE_ENABLED', true);
define('RASPI_HOTSPOT_ENABLED', true);
define('RASPI_NETWORK_ENABLED', true);
define('RASPI_MOBILE_ENABLED', true);
define('RASPI_DHCP_ENABLED', false);
define('RASPI_OPENVPN_ENABLED', false);
define('RASPI_TORPROXY_ENABLED', false);
define('RASPI_CONFAUTH_ENABLED', true);
define('RASPI_CHANGETHEME_ENABLED', false);
define('RASPI_VNSTAT_ENABLED', false);
define('RASPI_SYSTEM_ENABLED', false);
define('RASPI_ABOUT_ENABLED', false);

// Locale settings
define('LOCALE_ROOT', 'locale');
define('LOCALE_DOMAIN', 'messages');
