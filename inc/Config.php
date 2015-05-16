<?php

$container['addon_class'] = 'HM\\BackUpWordPress\\Addon';
$container['checklicense_class'] = 'HM\\BackUpWordPress\\CheckLicense';
$container['addon_version'] = '2.1.0';
$container['min_bwp_version'] = '3.1.4';
$container['edd_download_file_name'] = 'BackUpWordPress To FTP';
$container['addon_settings'] = 'hmbkpp_ftp_settings';
$container['service_class'] = 'FTPBackUpService';
$container['updater_class'] = 'HM\\BackUpWordPress\\PluginUpdater';
$container['prefix'] = 'ftp';