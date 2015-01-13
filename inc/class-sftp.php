<?php

/**
 * Class SFTP
 * @package WPRemote\Backups\Locations
 */
class HMBKP_SFTP {

	protected $connection;

	protected $options;

	public function __construct( $options ) {

		$this->options = $options;
		$this->options['port'] = 22;
	}

	public function __get( $property ) {
		return $this->$property;
	}

	/**
	 * Uploads a file to a remote location via SFTP
	 *
	 * @param     $file_path
	 * @param     $destination
	 * @param int $size
	 *
	 * @returns bool|WP_error
	 */
	public function upload( $file_path, $destination, $size = 0 ) {

		$logged_in_status = $this->login();

		if ( is_wp_error( $logged_in_status ) ) {
			return $logged_in_status;
		}

		// check if folder exists else create it
		if ( ! $this->connection->is_dir( $this->options['path'] ) ) {

			if ( ! $this->connection->mkdir( $this->options['path'] ) ) {

				return new WP_Error( 'sftp-write-error', sprintf( 'Unable to create remote directory %s', $this->options['path'] ) );

			}
		}

		$this->connection->put( $this->options['path'] . '/' . $destination, $file_path );

		return true;
	}

	/**
	 * Tests the location options
	 *
	 * @param $options
	 *
	 * @return WP_Error
	 */
	public function test_options( $options ) {

		$this->connect( $options['host'] );

		if ( ! ( $this->connection instanceof Net_SFTP ) ) {
			return new WP_Error( 'unsuccessful-connection-error', sprintf( 'Could not connect to %$1s on port %$2s', $options['host'], $options['port'] ) );
		}

		$username = $options['username'];

		$password = '';

		if ( ! empty( $options['password'] ) ) {
			$password = $options['password'];
		}

		$login_status = $this->login( $username, $password );

		if ( is_wp_error( $login_status ) ) {
			return $login_status;
		}
	}

	/**
	 * Open an SFTP connection
	 *
	 * @throws \Exception
	 */
	public function connect( $host, $port = 22 ) {

		if ( ! is_null( $this->connection ) ) {
			return;
		}

		$this->connection = new Net_SFTP( $host );

		if ( ! $this->connection ) {
			return new WP_Error( 'sftp-connection-error', sprintf( 'Could not connect to host %1$s on port %$2s', $host, $port ) );
		}

	}

	public function login() {

		$this->connect( $this->options['host'] );

		if ( ! ( $this->connection instanceof Net_SFTP ) ) {
			return new WP_Error( 'unsuccessful-connection-error', sprintf( 'Could not connect to %$1s on port %$2s', $this->options['host'], $this->options['port'] ) );
		}

		if ( ! $this->connection->login( $this->options['username'], $this->options['password'] ) ) {
			return new WP_Error( 'sftp-login-error', sprintf( 'Could not login with username %s and provided password', $this->options['username'] ) );
		}
	}
	
	public function dir_file_list() {
		return $this->connection->nlist( $this->options['path'] );
	}

	public function delete( $filename ) {
		return $this->connection->delete( $filename );
	}

}
