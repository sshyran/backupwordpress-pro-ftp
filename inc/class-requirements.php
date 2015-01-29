<?php
namespace HM\BackUpWordPressFTP;

use HM\BackUpWordPress;

/**
 * Class HMBKP_Requirement_Define_HMBKP_FS_METHOD
 */
class HMBKP_Requirement_Define_HMBKP_FS_METHOD extends BackUpWordPress\Requirement {

	/**
	 * @var string
	 */
	var $name = 'HMBKP_FS_METHOD';

	/**
	 * @return string
	 */
	protected function test() {

		return defined( 'HMBKP_FS_METHOD' ) ? HMBKP_FS_METHOD : '';

	}

}

BackUpWordPress\Requirements::register( 'HM\BackUpWordPressFTP\HMBKP_Requirement_Define_HMBKP_FS_METHOD', 'ftp' );

/**
 * Class HMBKP_Requirement_Define_HMBKPP_FTP_LICENSE_STATUS
 */
class HMBKP_Requirement_Define_HMBKPP_FTP_LICENSE_STATUS extends BackUpWordPress\Requirement {

	var $name = 'License Status';

	/**
	 * @return string
	 */
	protected function test() {

		$plugin = Plugin::get_instance();
		$settings = $plugin->fetch_settings();

		return ( isset( $settings['license_status'] ) && 'valid' === $settings['license_status'] ) ? 'License is valid' : 'License is invalid or not set';

	}

}

BackUpWordPress\Requirements::register( 'HM\BackUpWordPressFTP\HMBKP_Requirement_Define_HMBKPP_FTP_LICENSE_STATUS', 'ftp' );
