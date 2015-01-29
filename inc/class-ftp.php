<?php
namespace HM\BackUpWordPressFTP;

/**
 * Class HMBKP_FTP
 */
class FTP {

	/**
	 * The FTP connection stream
	 *
	 * @var resource
	 */
	protected $connection;

	protected $options;

	public function __construct( $options ) {

		$this->options = $options;
		$this->options['port'] = 21;
	}

	public function __get( $property ) {
		return $this->$property;
	}

	/**
	 * Uploads a file to a remote location via FTP
	 *
	 * @param     $file_path Full path to local file to upload.
	 * @param     $destination Remote file name.
	 * @param int $size
	 *
	 * @return bool|WP_Error
	 */
	public function upload( $file_path, $destination, $size = 0 ) {

		$logged_in_status = $this->login();

		if ( is_wp_error( $logged_in_status ) ) {
			return $logged_in_status;
		}

		// check if folder exists else create it
		if ( ! $this->is_dir( $this->options['path'] )
		     && ! @ftp_mkdir( $this->connection, $this->options['path'] )
		) {
			return new \WP_Error( 'ftp-write-error', sprintf( 'Unable to create remote directory %s', $this->options['path'] ) );
		}

		@ftp_chdir( $this->connection, $this->options['path'] );

		if ( ! @ftp_put( $this->connection, $destination, $file_path, FTP_BINARY ) ) {
			return new \WP_Error( 'file-transfer-error', sprintf( 'Unable to transfer file %s', $destination ) );
		}

		return true;
	}

	/**
	 * Deletes a file on a remote server via FTP
	 *
	 * @param $path
	 *
	 * @return bool|WP_Error
	 */
	public function delete( $path ) {

		$logged_in_status = $this->login();

		$full_path = trailingslashit( $this->options['path'] ) . $path;

		if ( is_wp_error( $logged_in_status ) ) {
			return $logged_in_status;
		}

		if ( ! @ftp_delete( $this->connection, $full_path ) ) {
			return new \WP_Error( 'ftp-io-error', sprintf( 'Unable to delete file %s', $full_path ) );
		}

		return true;
	}

	/**
	 * Tests the options
	 *
	 * @param $options
	 *
	 * @return WP_Error
	 */
	public function test_options( $options ) {

		$this->connect( $options['host'] );

		if ( ! $this->connection ) {
			return new \WP_Error( 'unsuccessful-connection-error', sprintf( 'Could not connect to %$1s on port %$2s', $options['host'], $options['port'] ) );
		}

		$username = $options['username'];
		$password = ( ! empty( $options['password'] ) ) ? $options['password'] : '';

		if ( ! @ftp_login( $this->connection, $username, $password ) ) {
			return new \WP_Error( 'unsuccessful-login-error', sprintf( 'Could not authenticate with username %1$s and provided password', $username ) );
		}

		$result = $this->close();

		return $result;
	}

	/**
	 * Attempts to authenticate on remote server with provided credentials.
	 * @return bool|WP_Error
	 */
	public function login() {

		$this->connect( $this->options['host'], $this->options['port'] );

		$username = $this->options['username'];
		$password = ( ! empty( $this->options['password'] ) ) ? $this->options['password'] : '';

		if ( ! @ftp_login( $this->connection, $username, $password ) ) {
			return new \WP_Error( 'unsuccessful-ftp-login', sprintf( 'Could not authenticate with username %1$s and provided password', $username ) );
		}

		@ftp_pasv( $this->connection, true );

		return true;
	}

	/**
	 * Free up the FTP connection
	 *
	 * @return bool|WP_Error
	 */
	public function close() {

		if ( ! @ftp_close( $this->connection ) ) {
			return new \WP_Error( 'ftp-disconnect-error', 'Unable to close the FTP connection' );
		}

		return true;
	}

	public function cwd() {

		return @ftp_pwd( $this->connection );
	}

	/**
	 * Opens an FTP conenction
	 *
	 * @param $host
	 * @param $port
	 *
	 * @return WP_Error
	 */
	public function connect( $host, $port = 21 ) {

		if ( ! is_null( $this->connection ) ) {
			return;
		}

		$this->connection = @ftp_connect( $host, $port );

		if ( ! $this->connection ) {
			return new \WP_Error( 'ftp-connection-error', sprintf( 'Could not connect to %1$ss on port %2$s', $host, $port ) );
		}

	}

	/**
	 * Checks if folder exists
	 *
	 * @param $path
	 *
	 * @return bool
	 */
	function is_dir( $path ) {

		$cwd = $this->cwd();

		$result = @ftp_chdir( $this->connection, trailingslashit( $path ) );

		if ( $result && $path == $this->cwd() || $this->cwd() != $cwd ) {

			@ftp_chdir( $this->connection, $cwd );

			return true;

		}

		return false;
	}

	/**
	 * @param string $path
	 *
	 * @return bool|array
	 */
	public function dir_file_list() {

		@ftp_chdir( $this->connection, $this->options['path'] );

		return @ftp_nlist( $this->connection, $this->options['path'] );

	}

}
