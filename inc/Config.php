<?php

$container['addon_class'] = 'HM\\BackUpWordPress\\Addon';
$container['checklicense_class'] = 'HM\\BackUpWordPress\\CheckLicense';
$container['addon_version'] = '2.1.8';
$container['min_bwp_version'] = '3.4.3';
$container['edd_download_file_name'] = 'BackUpWordPress To FTP';
$container['addon_settings_key'] = 'hmbkpp_ftp_settings';
$container['addon_settings_defaults'] = array( 'license_key' => '', 'license_status' => '', 'license_expired' => '', 'expiry_date' => '' );
$container['service_class'] = 'FTPBackUpService';
$container['updater_class'] = 'HM\\BackUpWordPress\\PluginUpdater';
$container['prefix'] = 'ftp';
$container['plugin_name'] = 'ftp';
