<?php
namespace HM\BackUpWordPressFTP;

use HM\BackUpWordPress;

/**
 * Class Define_FS_Method
 */
class Define_FS_Method extends BackUpWordPress\Requirement {

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

BackUpWordPress\Requirements::register( 'HM\BackUpWordPressFTP\Define_FS_Method', 'ftp' );

/**
 * Class Define_License_Status
 */
class Define_License_Status extends BackUpWordPress\Requirement {

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

BackUpWordPress\Requirements::register( 'HM\BackUpWordPressFTP\Define_License_Status', 'ftp' );
