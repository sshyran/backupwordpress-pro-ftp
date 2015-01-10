<?php

/**
 * Class SFTP
 * @package WPRemote\Backups\Locations
 */
class HMBKP_SFTP {

	protected $connection;

	protected $sftp;

	protected $options;

	public function __construct( $options ) {

		$this->options = $options;
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
	 * @returns bool|\WP_error
	 */
	public function upload( $file_path, $destination, $size = 0 ) {

		$logged_in_status = $this->login();

		if ( is_wp_error( $logged_in_status ) ) {
			return $logged_in_status;
		}

		$cwd = $this->cwd();

		// check if folder exists else create it
		if ( ! $this->is_dir( $this->options['path'] ) ) {
			if ( ! @ssh2_sftp_mkdir( $this->sftp, $this->options['path'], 0755, true ) ) {
				return new \WP_Error( 'sftp-write-error', sprintf( 'Unable to create remote directory %s', $this->options['path'] ) );
			}
		}

		$remote_path = ( strlen( $this->options['path'] ) > 0 ) ? $this->options['path'] . '/' : '';

		if ( false === ( $remote_file = @fopen( 'ssh2.sftp://' . $this->sftp . '/' . $cwd . '/' . $remote_path . $destination, 'w' ) ) ) {
			return new \WP_Error( 'open-file-error', sprintf( 'Unable to open file %1$s/%2$s', $cwd, $destination ) );
		}

		if ( false === ( $local_file = @fopen( $file_path, 'r' ) ) ) {
			return new \WP_Error( 'open-file-error', sprintf( 'Unable to open file %s', $local_file ) );
		}

		$writtenBytes = @stream_copy_to_stream( $local_file, $remote_file );

		if ( $writtenBytes === 0 ) {
			return new \WP_Error( 'write-file-error', 'Unable to transfer the file' );
		}

		@fclose( $remote_file );

		@fclose( $local_file );

		return true;
	}

	/**
	 * Deletes a file on a remote server via SFTP
	 *
	 * @param $path
	 *
	 * @return bool|\WP_Error
	 */
	public function delete( $path ) {

		$logged_in_status = $this->login();

		if ( is_wp_error( $logged_in_status ) ) {
			return $logged_in_status;
		}

		$cwd = $this->cwd();

		$remote_path = ( strlen( $this->options['path'] ) > 0 ) ? $this->options['path'] . '/' : '';

		if ( false == @ssh2_sftp_unlink( $this->sftp, $cwd . '/' . $remote_path . $path ) ) {
			return new \WP_Error( 'delete-file-error', sprintf( 'Unable to delete file %1$s/%2$s', $cwd, $path ) );
		}

		return true;
	}

	/**
	 * Tests the location options
	 *
	 * @param $options
	 *
	 * @return \WP_Error
	 */
	public function test_options( $options ) {

		$this->connect( $options['host'], $options['port'] );

		if ( ! $this->connection ) {
			return new \WP_Error( 'unsuccessful-connection-error', sprintf( 'Could not connect to %$1s on port %$2s', $options['host'], $options['port'] ) );
		}

		$username = $options['username'];

		$password = '';

		if ( ! empty( $options['password'] ) ) {
			$password = $options['password'];
		}

		if ( ! @ssh2_auth_password( $this->connection, $username, $password ) ) {
			return new \WP_Error( 'sftp-login-error', sprintf( 'Could not authenticate with username %1$s and provided password', $username ) );
		}

		$sftp = @ssh2_sftp( $this->connection );

		if ( ! $sftp ) {
			return new \WP_Error( 'sftp-init-error', 'There was an error creating an SFTP connection' );
		}

		return $this->close();
	}

	/**
	 * Returns the link to download the backup file
	 *
	 * @param $path
	 *
	 * @return string
	 */
	public function get_download_url( $path ) {

		$full_path = trailingslashit( $this->options['path'] ) . $path;

		// ssh2.sftp://user:pass@example.com:22/path/to/filename
		$url = 'ssh2.sftp://'
		       . $this->options['username']
		       . ':'
		       . $this->options['password']
		       . '@'
		       . $this->options['host']
		       . $this->options['port']
		       . trailingslashit( $this->cwd() )
		       . $full_path;

		return $url;
	}

	/**
	 * Login to remote server via SFTP
	 *
	 * @return bool|\WP_Error
	 */
	public function login() {

		$this->connect( $this->options['host'], $this->options['port'] );

		$username = $this->options['username'];

		$password = ( ! empty( $this->options['password'] ) ) ? $this->options['password'] : '';

		if ( ! @ssh2_auth_password( $this->connection, $username, $password ) ) {
			return new \WP_Error( 'unsuccessful-sftp-login', sprintf( 'Could not authenticate with username %1$s and provided password', $username ) );
		}

		$this->sftp = @ssh2_sftp( $this->connection );

		if ( ! $this->sftp ) {
			return new \WP_Error( 'sftp-connection-error', 'There was an error creating an SFTP connection' );
		}

		return true;
	}

	/**
	 * Close the open connection
	 *
	 * @return bool|\WP_Error
	 */
	public function close() {

		if ( ! @fclose( $this->sftp ) ) {
			return new \WP_Error( 'ftp-disconnect-error', 'Unable to close the SFTP stream' );
		}

		if ( ! @ftp_close( $this->connection ) ) {
			return new \WP_Error( 'ftp-disconnect-error', 'Unable to close the SFTP connection' );
		}

		return true;
	}

	/**
	 * Gets the current working directory
	 *
	 * @return string
	 */
	public function cwd() {

		return ssh2_sftp_realpath( $this->sftp, '.' );
	}

	/**
	 * Open an SFTP connection
	 *
	 * @throws \Exception
	 */
	public function connect( $host, $port ) {

		if ( ! is_null( $this->connection ) ) {
			return;
		}

		$this->connection = ssh2_connect( $host, $port );

		if ( ! $this->connection ) {
			return new \WP_Error( 'sftp-connection-error', sprintf( 'Could not connect to host %1$s on port %$2s', $host, $port ) );
		}

	}

	/**
	 * Checks path exists
	 *
	 * @param $path
	 *
	 * @return bool
	 */
	public function is_dir( $path ) {

		$path = ltrim( $path, '/' );

		return is_dir( 'ssh2.sftp://' . $this->sftp . '/' . $path );
	}

	public function dir_file_list( $path ) {

		$entries = array();

		$handle = opendir( "ssh2.sftp://$this->sftp/$path" );

		while ( false !== ( $entry = readdir( $handle ) ) ) {

			if ( ! $this->is_dir( $entry ) ) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

}
