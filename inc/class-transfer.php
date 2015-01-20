<?php

defined( 'WPINC' ) or die;

require_once plugin_dir_path( __FILE__ ) . 'class-encryption.php';
require_once plugin_dir_path( __FILE__ ) . 'class-ftp.php';
require_once plugin_dir_path( __FILE__ ) . 'class-sftp.php';

/**
 * Class HMBKP_FTP_Backup_Service
 */
class HMBKP_FTP_Backup_Service extends HMBKP_Service {

	/**
	 * Human readable name
	 * @var string
	 */
	public $name = 'FTP';

	/**
	 * FTP credentials
	 * @var Array
	 */
	protected $credentials;

	protected $connection;

	/**
	 * Fire the FTP transfer on the hmbkp_backup_complete
	 *
	 * @see  HM_Backup::do_action
	 *
	 * @param  string $action The action received from the backup
	 *
	 * @return void
	 */
	public function action( $action ) {

		if ( ( 'hmbkp_backup_complete' === $action ) && $this->get_field_value( 'FTP' ) ) {

			$file = $this->schedule->get_archive_filepath();

			$this->credentials = array(
				'host'        => $this->get_field_value( 'hostname' ),
				'username'        => $this->get_field_value( 'username' ),
				'password'        => HMBKP_Encryption::decrypt( $this->get_field_value( 'password' ) ),
				'path' => $this->get_field_value( 'folder' ),
			);

			switch ( $this->get_field_value( 'connection_type' ) ) {
				case 'ftp':
					$this->connection = new HMBKP_FTP( $this->credentials );
					break;
				case 'sftp':
					$this->connection = new HMBKP_SFTP( $this->credentials );
					break;
			}

			$this->do_backup( $file );
		}
	}

	/**
	 * Performs the actual backup
	 *
	 * @param $file
	 */
	public function do_backup( $file ) {

		// Give feedback on progress
		$this->schedule->set_status( sprintf( __( 'Uploading a copy to %s', 'backupwordpress' ), $this->credentials['host'] ) );

		$result = $this->connection->upload( $file, pathinfo( $file, PATHINFO_BASENAME ) );

		if ( is_wp_error( $result ) ) {
			$this->schedule->error( 'FTP', sprintf( __( 'An error occurred: %s', 'backupwordpress' ), $result->get_error_message() ) );
		} else {
			$this->delete_old_backups();
		}

	}

	/**
	 * Frees up space on the specified remote server by only keeping the number of
	 * backup files defined in the max backup settings and deleting older ones.
	 */
	protected function delete_old_backups() {

		// get max backups number
		$max_backups = absint( $this->get_field_value( 'ftp_max_backups' ) );

		// Get the backup folder location from user settings
		$backup_dir = $this->get_field_value( 'folder' );

		// get list of existing remote backups
		$response = $this->connection->dir_file_list();

		if ( false === $response ) {
			return;
		}

		$backup_files = array_filter( $response, array( $this, 'filter_files' ) );

		if ( count( $backup_files ) <= $max_backups ) {
			return;
		}

		krsort( $backup_files );

		$files_to_delete = array_slice( $backup_files, $max_backups );

		foreach ( $files_to_delete as $filename ) {
			$this->connection->delete( $filename );
		}

	}

	/**
	 * Callback to filter an array based on the backup prefix
	 *
	 * @param $element
	 *
	 * @return bool
	 */
	protected function filter_files( $element ) {

		$pattern = implode( '-', array(
				sanitize_title( str_ireplace( array( 'http://', 'https://', 'www' ), '', home_url() ) ),
				$this->schedule->get_id(),
				$this->schedule->get_type(),
			)
		);

		return ( false === ( strpos( $element, $pattern ) ) ) ? false : true;
	}

	/**
	 * Displays the settings form for the FTP backup
	 */
	public function form() {

		$hostname = $this->get_field_value( 'hostname' );

		if ( empty( $hostname ) ) {

			$options = $this->fetch_destination_settings();

			if ( ! empty( $options ) ) {
				$hostname = $options['hostname'];
			}
		}

		$type = $this->get_field_value( 'connection_type' );

		if ( empty( $type ) && ( isset( $options['connection_type'] ) ) ) {
			$type = $options['connection_type'];
		}

		// set a default value if none is set
		if ( empty( $type ) ) {
			$type = 'ftp';
		}

		$username = $this->get_field_value( 'username' );

		if ( empty( $username ) && ( isset( $options['username'] ) ) ) {
			$username = $options['username'];
		}

		$pwd = HMBKP_Encryption::decrypt( $this->get_field_value( 'password' ) );

		if ( empty( $pwd ) && ( isset( $options['password'] ) ) ) {
			$pwd = HMBKP_Encryption::decrypt( $options['password'] );
		}

		$folder = $this->get_field_value( 'folder' );

		if ( empty( $folder ) && ( isset( $options['folder'] ) ) ) {
			$folder = $options['folder'];
		}

		$max_backups = $this->get_field_value( 'ftp_max_backups' );

		if ( empty( $max_backups ) && isset( $options['ftp_max_backups'] ) ) {
			$max_backups = $options['ftp_max_backups'];
		} ?>

		<table class="form-table">

			<tbody>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'FTP' ); ?>"><?php _e( 'Send a copy of each backup to an FTP server', 'backupwordpress' ); ?></label>

				</th>

				<td>

					<input id="<?php echo $this->get_field_name( 'FTP' ); ?>" type="checkbox" <?php checked( $this->get_field_value( 'FTP' ) ); ?> name="<?php echo $this->get_field_name( 'FTP' ); ?>" value="1"/>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'hostname' ); ?>"><?php _e( 'Server', 'backupwordpress' ); ?></label>

				</th>

				<td>

					<input type="text" id="<?php echo $this->get_field_name( 'hostname' ); ?>" name="<?php echo $this->get_field_name( 'hostname' ); ?>" value="<?php echo esc_attr( $hostname ); ?>"/>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="HMBKP_FTP_Backup_Service[connection_type]"><?php _e( 'Connection type', 'backupwordpress' ); ?></label>

				</th>

				<td>

					<select name="HMBKP_FTP_Backup_Service[connection_type]" id="HMBKP_FTP_Backup_Service[connection_type]" <?php disabled( defined( 'HMBKP_FS_METHOD' ) ); ?>>

						<option <?php selected( $type, 'ftp' ); ?> value="ftp"><?php _e( 'FTP', 'backupwordpress' ); ?></option>

						<?php if ( extension_loaded( 'ssh2' ) && function_exists( 'stream_get_contents' ) ) : ?>

							<option <?php selected( $type, 'sftp' ); ?> value="sftp"><?php _e( 'SFTP', 'backupwordpress' ); ?></option>

						<?php endif; ?>

					</select>

				</td>

			</tr>


			<tr>
				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'username' ); ?>"><?php _e( 'Username', 'backupwordpress' ); ?></label>

				</th>

				<td>

					<input type="text" id="<?php echo $this->get_field_name( 'username' ); ?>" name="<?php echo $this->get_field_name( 'username' ); ?>" value="<?php echo esc_attr( $username ); ?>"/>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'password' ); ?>"><?php _e( 'Password', 'backupwordpress' ); ?></label>

				</th>

				<td>

					<input type="password" id="<?php echo $this->get_field_name( 'password' ); ?>" name="<?php echo $this->get_field_name( 'password' ); ?>" value="<?php echo esc_attr( $pwd ); ?>"/>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'folder' ); ?>"><?php _e( 'Folder', 'backupwordpress' ); ?></label>

				</th>

				<td>

					<input type="text" id="<?php echo $this->get_field_name( 'folder' ); ?>" name="<?php echo $this->get_field_name( 'folder' ); ?>" value="<?php echo ! empty( $folder ) ? $folder : sanitize_title_with_dashes( get_bloginfo( 'name' ) ); ?>"/>

					<p class="description"><?php _e( 'The folder to save the backups to, it will be created automatically if it doesn\'t already exist.', 'backupwordpress' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="<?php echo $this->get_field_name( 'ftp_max_backups' ); ?>"><?php _e( 'Max backups', 'backupwordpress' ); ?></label>
				</th>
				<td>
					<input  class="small-text" type="number" min="1" step="1" id="<?php echo $this->get_field_name( 'ftp_max_backups' ); ?>" name="<?php echo $this->get_field_name( 'ftp_max_backups' ); ?>" value="<?php echo( empty( $max_backups ) ? 3 : $max_backups ); ?>"/>

					<p class="description"><?php _e( 'The maximum number of backups to store.', 'backupwordpress' ); ?></p>
				</td>
			</tr>

			</tbody>

		</table>

		<input type="hidden" name="is_destination_form" value="1"/>

	<?php
	}

	/**
	 * Output the Constant help text.
	 */
	public static function constant() {
		?>

		<tr<?php if ( defined( 'HMBKP_FS_METHOD' ) ) { ?> class="hmbkp_active"<?php } ?>>

			<td><code>HMBKP_FS_METHOD</code></td>

			<td>

				<?php if ( defined( 'HMBKP_FS_METHOD' ) ) { ?>
					<p><?php printf( __( 'You\'ve set it to: %s', 'hmbkp' ), '<code>' . HMBKP_FS_METHOD . '</code>' ); ?></p>
				<?php } ?>

				<p><?php printf( __( 'The transport method for all backup transfers. Must be one of %s, %s, %s.', 'backupwordpress-ftp' ), '<code>ftpext</code>', '<code>ftpsockets</code>', '<code>ssh2</code>' ); ?> <?php _e( 'e.g.', 'backupwordpress-ftp' ); ?>
					<code>define( 'HMBKP_FS_METHOD', 'ssh2' );</code></p>

			</td>

		</tr>

	<?php
	}

	/**
	 * Define as an empty function as we are using form
	 */
	public function field() {
	}

	/**
	 * Validate the data before saving, if there are errors, return them to the user
	 *
	 * @param  array $new_data the new data thats being saved
	 * @param  array $old_data the old data thats being overwritten
	 *
	 * @return array           any errors encountered
	 */
	public function update( &$new_data, $old_data ) {

		$errors = array();

		if ( ! isset( $new_data['FTP'] ) ) {
			return;
		} // Destination was disabled

		if ( isset( $new_data['hostname'] ) ) {
			if ( empty( $new_data['hostname'] ) ) {
				$errors['hostname'] = __( 'Please provide a valid hostname', 'backupwordpress' );
			}
		}

		if ( isset( $new_data['port'] ) ) {
			if ( empty( $new_data['port'] ) ) {
				$errors['port'] = __( 'Please provide a valid port number', 'backupwordpress' );
			}
		}

		if ( isset( $new_data['connection_type'] ) ) {
			if ( empty( $new_data['connection_type'] ) ) {
				$errors['connection_type'] = __( 'Please provide a valid connection type', 'backupwordpress' );
			}
		}

		if ( defined( 'HMBKP_FS_METHOD' ) && HMBKP_FS_METHOD ) {
			$new_data['connection_type'] = HMBKP_FS_METHOD;
		}

		if ( isset( $new_data['username'] ) ) {
			if ( empty( $new_data['username'] ) ) {
				$errors['username'] = __( 'Please provide a valid username', 'backupwordpress' );
			}
		}

		if ( isset( $new_data['password'] ) ) {

			if ( empty( $new_data['password'] ) ) {
				$errors['password'] = __( 'Please provide a valid password', 'backupwordpress' );
			} else {
				$test_pw = $new_data['password'];
				$new_data['password'] = HMBKP_Encryption::encrypt( $new_data['password'] );
			}
		}

		if ( isset( $new_data['folder'] ) ) {
			if ( empty( $new_data['folder'] ) ) {
				$errors['folder'] = __( 'Please provide a valid folder path', 'backupwordpress' );
			}
		}

		if ( isset( $new_data['ftp_max_backups'] ) ) {
			if ( empty( $new_data['ftp_max_backups'] ) || ! ctype_digit( $new_data['ftp_max_backups'] ) ) {
				$errors['ftp_max_backups'] = __( 'Max backups must be a number', 'backupwordpress' );
			}
		}

		if ( empty ( $errors ) ) {
			$this->credentials = array(
				'host'        => $new_data['hostname'],
				'username'        => $new_data['username'],
				'password'        => $test_pw,
				'path' => $this->get_field_value( 'folder' ),
				//'ssl'             => $new_data['ssl'],
			);

			switch ( $this->get_field_value( 'connection_type' ) ) {
				case 'ftp':
					$this->connection = new HMBKP_FTP( $this->credentials );
					break;
				case 'sftp':
					$this->connection = new HMBKP_SFTP( $this->credentials );
					break;
				default:
					// throw an error
					break;
			}

			$result = $this->connection->test_options( $this->credentials );

			if ( is_wp_error( $result ) ) {
				$this->schedule->error( 'FTP', sprintf( __( 'An error occurred: %s', 'backupwordpress' ), $result->get_error_message() ) );
			}

			return $errors;

		}
	}

	/**
	 * The words to append to the main schedule sentence
	 * @return string The words that will be appended to the main schedule sentence
	 */
	public function display() {

		if ( $this->is_service_active() ) {
			return sprintf( __( '%1$s %2$s', 'backupwordpress' ), $this->name, $this->get_field_value( 'hostname' ) );
		}

	}

	/**
	 * Used to determine if the service is in use or not
	 * @return bool True if service is active
	 */
	public function is_service_active() {
		return (bool) $this->get_field_value( 'FTP' );
	}

	/**
	 * FTP specific data to send to Intercom.
	 *
	 * @return array
	 */
	public static function intercom_data() {

		require_once plugin_dir_path( __FILE__ ) . 'class-requirements.php';

		$info = array();

		foreach ( HMBKP_Requirements::get_requirements( 'ftp' ) as $requirement ) {
			$info[ $requirement->name() ] = $requirement->result();
		}

		return $info;
	}

	/**
	 * FTP specific data to show in the admin help tab.
	 */
	public static function intercom_data_html() {

		require_once plugin_dir_path( __FILE__ ) . 'class-requirements.php'; ?>

		<h3><?php _e( 'FTP', 'backupwordpress' ); ?></h3>

		<table class="fixed widefat">

			<tbody>

			<?php foreach ( HMBKP_Requirements::get_requirements( 'ftp' ) as $requirement ) : ?>

				<tr>
					<td><?php echo $requirement->name(); ?></td>
					<td>
						<pre><?php echo $requirement->result(); ?></pre>
					</td>
				</tr>

			<?php endforeach; ?>

			</tbody>

		</table>

	<?php
	}

} // end HMBKP_FTP_Backup_Service

HMBKP_Services::register( __FILE__, 'HMBKP_FTP_Backup_Service' );
