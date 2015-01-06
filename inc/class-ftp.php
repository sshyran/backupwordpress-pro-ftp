<?php

defined( 'WPINC' ) or die;

require_once HMBKP_FTP_PLUGIN_PATH . 'inc/class-encryption.php';

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
	 * Whether to show this service in the tabbed interface of destinations
	 * @var boolean
	 */
	public $is_tab_visible = true;

	/**
	 * FTP credentials
	 * @var Array
	 */
	protected $credentials;

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

		// it seems WP_Filesystem isn't loaded for Cron jobs
		require_once( ABSPATH . 'wp-admin/includes/file.php' );

		if ( ( 'hmbkp_backup_complete' === $action ) && $this->get_field_value( 'FTP' ) ) {

			$file = $this->schedule->get_archive_filepath();

			$this->credentials = array(
				'hostname'        => $this->get_field_value( 'hostname' ),
				'username'        => $this->get_field_value( 'username' ),
				'password'        => HMBKP_Encryption::decrypt( $this->get_field_value( 'password' ) ),
				'port'            => (int) $this->get_field_value( 'port' ),
				'ssl'             => $this->get_field_value( 'ssl' ),
				'connection_type' => $this->get_field_value( 'connection_type' )
			);

			// hook into the get_filesystem_method function to determine which one to use and avoid direct method
			add_filter( 'filesystem_method', array( $this, 'change_method' ), 15, 2 );

			// attempt a connection
			if ( WP_Filesystem( $this->credentials ) ) {
				$this->do_backup( $file );
			} else {
				$this->schedule->error( 'FTP', __( 'Unable to transfer the backup with the current settings', 'backupwordpress-pro-ftp' ) );
			}
		}
	}

	/**
	 * Determine appropriate filesystem connection method
	 *
	 * @param $method
	 * @param $args credentials and connection settings
	 *
	 * @return string Connection method
	 */
	public function change_method( $method, $args ) {

		// if method wasn't set in the wp-config
		if ( defined( 'HMBKP_FS_METHOD' ) && in_array( HMBKP_FS_METHOD, array( 'ssh2', 'ftpext', 'ftpsockets' ) ) ) {

			$method = HMBKP_FS_METHOD;

		} elseif ( 'direct' == $method ) {

			// determine FTP method
			if ( 'ftp' === $args['connection_type'] && extension_loaded( 'ftp' ) ) {
				$method = 'ftpext';

			} elseif ( 'ftp' === $args['connection_type'] && ( extension_loaded( 'sockets' ) || function_exists( 'fsockopen' ) ) ) {
				$method = 'ftpsockets';

			} elseif ( 'sftp' === $args['connection_type'] && extension_loaded( 'ssh2' ) && function_exists( 'stream_get_contents' ) ) {
				$method = 'ssh2';

			} else {
				$method = '';
			}
		}

		return $method;
	}

	/**
	 * Returns the current working directory on the remote server.
	 * @return string
	 */
	private function get_cwd() {

		global $wp_filesystem;

		$cwd = trim( untrailingslashit( $wp_filesystem->cwd() ) );

		if ( $wp_filesystem->is_dir( $cwd ) ) {
			return trailingslashit( $cwd );
		}

		return '';

	}

	/**
	 * Performs the actual backup
	 *
	 * @param $file
	 */
	public function do_backup( $file ) {

		global $wp_filesystem;

		// Get the backup folder location from user settings
		$backup_dir = $this->get_field_value( 'folder' );

		// Build backup file path
		$destination = $wp_filesystem->cwd() . $backup_dir . '/' . pathinfo( $file, PATHINFO_BASENAME );

		// SSH needs root directory in path, use cwd()
		if ( 'ssh2' === $wp_filesystem->method ) {
			$backup_dir  = $this->get_cwd() . $backup_dir;
			$destination = $this->get_cwd() . $destination;
		}

		// Attempt to create the remote folder where backup file will be stored
		if ( ! $wp_filesystem->exists( $backup_dir ) ) {

			if ( ! $wp_filesystem->mkdir( $backup_dir ) ) {

				$message = sprintf( __( 'Could not create directory %s', 'backupwordpress-pro-ftp' ), '/' . trailingslashit( $backup_dir ) );

				$this->schedule->error( 'FTP', $message );

				return;

			}
		}

		// Give feedback on progress
		$this->schedule->set_status( sprintf( __( 'Uploading a copy to %s', 'backupwordpress-pro-ftp' ), $this->credentials['hostname'] ) );
		
		// Write the contents of the local zip backup to remote server
		if ( ! $wp_filesystem->copy( $file, $destination, true ) ) {

			$message = sprintf( __( 'Error copying file %s to %s', 'backupwordpress-pro-ftp' ), $file, $destination );

			$this->schedule->error( 'FTP', $message );

			return;

		} else {
			$this->delete_old_backups();
		}

	}

	/**
	 * Frees up space on the specified remote server by only keeping the number of
	 * backup files defined in the max backup settings and deleting older ones.
	 */
	protected function delete_old_backups() {

		global $wp_filesystem;

		// hook into the get_filesystem_method function to determine which one to use and avoid direct method
		add_filter( 'filesystem_method', array( $this, 'change_method' ), 15, 2 );

		// get max backups number
		$max_backups = absint( $this->get_field_value( 'ftp_max_backups' ) );

		// Get the backup folder location from user settings
		$backup_dir = $this->get_field_value( 'folder' );

		// get list of existing remote backups
		$response = wp_list_pluck( $wp_filesystem->dirlist( $backup_dir ), 'name' );

		$backup_files = array_filter( $response, array( $this, 'filter_files' ) );

		if ( count( $backup_files ) <= $max_backups ) {
			return;
		}

		krsort( $backup_files );

		$files_to_delete = array_slice( $backup_files, $max_backups );

		// @TODO : actually delete the files!
		foreach ( $files_to_delete as $filename ) {
			$filetest = $this->get_cwd() . trailingslashit( $backup_dir ) . $filename;
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

		$port = $this->get_field_value( 'port' );

		if ( empty( $port ) && ( isset( $options['port'] ) ) ) {
			$port = $options['port'];
		}

		$ssl = $this->get_field_value( 'ssl' );

		if ( empty( $ssl ) && ( isset( $options['ssl'] ) ) ) {
			$port = $options['ssl'];
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

					<label for="<?php echo $this->get_field_name( 'FTP' ); ?>"><?php _e( 'Send a copy of each backup to an FTP server', 'backupwordpress-pro-ftp' ); ?></label>

				</th>

				<td>

					<input id="<?php echo $this->get_field_name( 'FTP' ); ?>" type="checkbox" <?php checked( $this->get_field_value( 'FTP' ) ); ?> name="<?php echo $this->get_field_name( 'FTP' ); ?>" value="1"/>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'hostname' ); ?>"><?php _e( 'Host name    ', 'backupwordpress-pro-ftp' ); ?></label>

				</th>

				<td>

					<input type="text" id="<?php echo $this->get_field_name( 'hostname' ); ?>" name="<?php echo $this->get_field_name( 'hostname' ); ?>" value="<?php echo esc_attr( $hostname ); ?>"/>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="HMBKP_FTP_Backup_Service[connection_type]"><?php _e( 'Connection type', 'backupwordpress-pro-ftp' ); ?></label>

				</th>

				<td>

					<select name="HMBKP_FTP_Backup_Service[connection_type]" id="HMBKP_FTP_Backup_Service[connection_type]" <?php disabled( defined( 'HMBKP_FS_METHOD' ) ); ?>>

						<option <?php selected( $type, 'ftp' ); ?> value="ftp"><?php _e( 'FTP', 'backupwordpress-pro-ftp' ); ?></option>

						<?php if ( extension_loaded( 'ssh2' ) && function_exists( 'stream_get_contents' ) ) : ?>

							<option <?php selected( $type, 'sftp' ); ?> value="sftp"><?php _e( 'SFTP', 'backupwordpress-pro-ftp' ); ?></option>

							<option <?php selected( $type, 'ssh2' ); ?> value="ssh2"><?php _e( 'SSH2', 'backupwordpress-pro-ftp' ); ?></option>

						<?php endif; ?>

					</select>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'port' ); ?>"><?php _e( 'Port', 'backupwordpress-pro-ftp' ); ?></label>

				</th>

				<td>

					<input type="text" id="<?php echo $this->get_field_name( 'port' ); ?>" name="<?php echo $this->get_field_name( 'port' ); ?>" value="<?php echo ! empty( $port ) ? esc_attr( $port ) : 21; ?>"/>

					<p class="description"><?php _e( 'Port (e.g. 21).', 'backupwordpress-pro-ftp' ); ?></p>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'ssl' ); ?>"><?php _e( 'Force SSL', 'backupwordpress-pro-ftp' ); ?></label>
				</th>

				<td>

					<input type="checkbox" id="<?php echo $this->get_field_name( 'ssl' ); ?>" name="<?php echo $this->get_field_name( 'ssl' ); ?>" value="1" <?php checked( $ssl ); ?> />
				</td>

			</tr>

			<tr>
				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'username' ); ?>"><?php _e( 'Username', 'backupwordpress-pro-ftp' ); ?></label>

				</th>

				<td>

					<input type="text" id="<?php echo $this->get_field_name( 'username' ); ?>" name="<?php echo $this->get_field_name( 'username' ); ?>" value="<?php echo esc_attr( $username ); ?>"/>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'password' ); ?>"><?php _e( 'Password', 'backupwordpress-pro-ftp' ); ?></label>

				</th>

				<td>

					<input type="password" id="<?php echo $this->get_field_name( 'password' ); ?>" name="<?php echo $this->get_field_name( 'password' ); ?>" value="<?php echo esc_attr( $pwd ); ?>"/>

				</td>

			</tr>

			<tr>

				<th scope="row">

					<label for="<?php echo $this->get_field_name( 'folder' ); ?>"><?php _e( 'Folder', 'backupwordpress-pro-ftp' ); ?></label>

				</th>

				<td>

					<input type="text" id="<?php echo $this->get_field_name( 'folder' ); ?>" name="<?php echo $this->get_field_name( 'folder' ); ?>" value="<?php echo ! empty( $folder ) ? $folder : sanitize_title_with_dashes( get_bloginfo( 'name' ) ); ?>"/>

					<p class="description"><?php _e( 'The folder to save the backups to, it will be created automatically if it doesn\'t already exist.', 'backupwordpress-pro-ftp' ); ?></p>
				</td>
			</tr>

			<tr>
				<th scope="row">
					<label for="<?php echo $this->get_field_name( 'ftp_max_backups' ); ?>"><?php _e( 'Max backups', 'backupwordpress-pro-ftp' ); ?></label>
				</th>
				<td>
					<input  class="small-text" type="number" min="1" step="1" id="<?php echo $this->get_field_name( 'ftp_max_backups' ); ?>" name="<?php echo $this->get_field_name( 'ftp_max_backups' ); ?>" value="<?php echo( empty( $max_backups ) ? 3 : $max_backups ); ?>"/>

					<p class="description"><?php _e( 'The maximum number of backups to store.', 'backupwordpress-pro-ftp' ); ?></p>
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

		global $wp_filesystem;

		$errors = array();

		if ( ! isset( $new_data['FTP'] ) ) {
			return;
		} // Destination was disabled

		if ( isset( $new_data['hostname'] ) ) {
			if ( empty( $new_data['hostname'] ) ) {
				$errors['hostname'] = __( 'Please provide a valid hostname', 'backupwordpress-pro-ftp' );
			}
		}

		if ( isset( $new_data['port'] ) ) {
			if ( empty( $new_data['port'] ) ) {
				$errors['port'] = __( 'Please provide a valid port number', 'backupwordpress-pro-ftp' );
			}
		}

		if ( isset( $new_data['connection_type'] ) ) {
			if ( empty( $new_data['connection_type'] ) ) {
				$errors['connection_type'] = __( 'Please provide a valid connection type', 'backupwordpress-pro-ftp' );
			}
		}

		if ( defined( 'HMBKP_FS_METHOD' ) && HMBKP_FS_METHOD ) {
			$new_data['connection_type'] = HMBKP_FS_METHOD;
		}

		if ( isset( $new_data['username'] ) ) {
			if ( empty( $new_data['username'] ) ) {
				$errors['username'] = __( 'Please provide a valid username', 'backupwordpress-pro-ftp' );
			}
		}

		if ( isset( $new_data['password'] ) ) {

			if ( empty( $new_data['password'] ) ) {
				$errors['password'] = __( 'Please provide a valid password', 'backupwordpress-pro-ftp' );
			} else {
				$new_data['password'] = HMBKP_Encryption::encrypt( $new_data['password'] );
			}
		}

		if ( isset( $new_data['folder'] ) ) {
			if ( empty( $new_data['folder'] ) ) {
				$errors['folder'] = __( 'Please provide a valid folder path', 'backupwordpress-pro-ftp' );
			}
		}

		if ( isset( $new_data['ftp_max_backups'] ) ) {
			if ( empty( $new_data['ftp_max_backups'] ) || ! ctype_digit( $new_data['ftp_max_backups'] ) ) {
				$errors['ftp_max_backups'] = __( 'Max backups must be a number', 'backupwordpress-pro-ftp' );
			}
		}

		$credentials             = $new_data;
		$credentials['password'] = HMBKP_Encryption::decrypt( $new_data['password'] );

		// Hook into the get_filesystem_method function to determine which one to use and avoid direct method
		if ( ! has_filter( 'filesystem_method', array( $this, 'change_method' ) ) ) {
			add_filter( 'filesystem_method', array( $this, 'change_method' ), 15, 2 );
		}

		if ( empty( $errors ) && ! WP_Filesystem( $credentials ) ) {

			$fs_errors = $wp_filesystem->errors->get_error_messages();

			$message = implode( ' ', $fs_errors );

			$errors['hostname'] = $message;

		}

		return $errors;

	}

	/**
	 * The words to append to the main schedule sentence
	 * @return string The words that will be appended to the main schedule sentence
	 */
	public function display() {

		if ( $this->is_service_active() ) {
			return sprintf( __( '%1$s %2$s', 'backupwordpress-pro-ftp' ), $this->name, $this->get_field_value( 'hostname' ) );
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

		require_once HMBKP_FTP_PLUGIN_PATH . 'inc/class-requirements.php';

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

		require_once HMBKP_FTP_PLUGIN_PATH . 'inc/class-requirements.php'; ?>

		<h3><?php _e( 'FTP', 'backupwordpress-pro-ftp' ); ?></h3>

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
