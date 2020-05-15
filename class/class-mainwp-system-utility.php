<?php
/**
 * MainWP System Utility Helper
 *
 * @package MainWP/Dashboard
 */

namespace MainWP\Dashboard;

// phpcs:disable WordPress.DB.RestrictedFunctions, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- Using cURL functions.

/**
 * MainWP System Utility
 */
class MainWP_System_Utility {

	/**
	 * Method get_class_name()
	 *
	 * Get Class Name.
	 *
	 * @return object
	 */
	public static function get_class_name() {
		return __CLASS__;
	}

	/**
	 * Method is_admin()
	 *
	 * Check if current user is an administrator.
	 *
	 * @return boolean True|False.
	 */
	public static function is_admin() {
		global $current_user;
		if ( 0 === $current_user->ID ) {
			return false;
		}

		if ( 10 == $current_user->wp_user_level || ( isset( $current_user->user_level ) && 10 == $current_user->user_level ) || current_user_can( 'level_10' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Method get_primary_backup()
	 *
	 * Check if using Legacy Backup Solution.
	 *
	 * @return mixed False|$enable_legacy_backup.
	 */
	public static function get_primary_backup() {
		$enable_legacy_backup = get_option( 'mainwp_enableLegacyBackupFeature' );
		if ( ! $enable_legacy_backup ) {
			return get_option( 'mainwp_primaryBackup', false );
		}
		return false;
	}

	/**
	 * Method get_notification_email()
	 *
	 * Check if user wants to recieve MainWP Notification Emails.
	 *
	 * @param null $user User Email Address.
	 *
	 * @return mixed null|User Email Address.
	 */
	public static function get_notification_email( $user = null ) {
		if ( null == $user ) {
			global $current_user;
			$user = $current_user;
		}

		if ( null == $user ) {
			return null;
		}

		if ( ! ( $user instanceof WP_User ) ) {
			return null;
		}

		$userExt = MainWP_DB_Common::instance()->get_user_extension();
		if ( '' != $userExt->user_email ) {
			return $userExt->user_email;
		}

		return $user->user_email;
	}

	/**
	 * Method get_base_dir()
	 *
	 * Get the base upload directory.
	 *
	 * @return string basedir/
	 */
	public static function get_base_dir() {
		$upload_dir = wp_upload_dir();

		return $upload_dir['basedir'] . DIRECTORY_SEPARATOR;
	}

	/**
	 * Method get_icons_dir()
	 *
	 * Get MainWP icons directory,
	 * if it doesn't exist create it.
	 *
	 * @return array $dir, $url
	 */
	public static function get_icons_dir() {
		$hasWPFileSystem = self::get_wp_file_system();
		global $wp_filesystem;

		$dirs = self::get_mainwp_dir();
		$dir  = $dirs[0] . 'icons' . DIRECTORY_SEPARATOR;
		$url  = $dirs[1] . 'icons/';
		if ( ! $wp_filesystem->exists( $dir ) ) {
			$wp_filesystem->mkdir( $dir, 0777, true );
		}
		if ( ! $wp_filesystem->exists( $dir . 'index.php' ) ) {
			$wp_filesystem->touch( $dir . 'index.php' );
		}
		return array( $dir, $url );
	}

	/**
	 * Method get_mainwp_dir()
	 *
	 * Get the MainWP directory,
	 * if it doesn't exite create it.
	 *
	 * @return array $dir, $url
	 */
	public static function get_mainwp_dir() {
		$hasWPFileSystem = self::get_wp_file_system();
		global $wp_filesystem;

		$upload_dir = wp_upload_dir();
		$dir        = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'mainwp' . DIRECTORY_SEPARATOR;
		$url        = $upload_dir['baseurl'] . '/mainwp/';
		if ( ! $wp_filesystem->exists( $dir ) ) {
			$wp_filesystem->mkdir( $dir, 0777, true );
		}
		if ( ! $wp_filesystem->exists( $dir . 'index.php' ) ) {
			$wp_filesystem->touch( $dir . 'index.php' );
		}

		return array( $dir, $url );
	}

	/**
	 * Method get_download_dir()
	 *
	 * @param mixed $what What url.
	 * @param mixed $filename File Name.
	 *
	 * @return void
	 */
	public static function get_download_url( $what, $filename ) {
		$specificDir = self::get_mainwp_specific_dir( $what );
		$mwpDir      = self::get_mainwp_dir();
		$mwpDir      = $mwpDir[0];
		$fullFile    = $specificDir . $filename;

		return admin_url( '?sig=' . md5( filesize( $fullFile ) ) . '&mwpdl=' . rawurlencode( str_replace( $mwpDir, '', $fullFile ) ) );
	}

	/**
	 * Method get_mainwp_specific_dir()
	 *
	 * Get MainWP Specific directory,
	 * if it doesn't exist create it.
	 *
	 * Update .htaccess.
	 *
	 * @param null $dir Current MainWP directory.
	 *
	 * @return string $newdir
	 */
	public static function get_mainwp_specific_dir( $dir = null ) {
		if ( MainWP_System::instance()->is_single_user() ) {
			$userid = 0;
		} else {
			global $current_user;
			$userid = $current_user->ID;
		}

		$hasWPFileSystem = self::get_wp_file_system();

		global $wp_filesystem;

		$dirs   = self::get_mainwp_dir();
		$newdir = $dirs[0] . $userid . ( null != $dir ? DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR : '' );

		if ( $hasWPFileSystem && ! empty( $wp_filesystem ) ) {

			if ( ! $wp_filesystem->is_dir( $newdir ) ) {
				$wp_filesystem->mkdir( $newdir, 0777, true );
			}

			if ( null != $dirs[0] . $userid && ! $wp_filesystem->exists( trailingslashit( $dirs[0] . $userid ) . '.htaccess' ) ) {
				$file_htaccess = trailingslashit( $dirs[0] . $userid ) . '.htaccess';
				$wp_filesystem->put_contents( $file_htaccess, 'deny from all' );
			}
		} else {

			if ( ! file_exists( $newdir ) ) {
				mkdir( $newdir, 0777, true );
			}

			if ( null != $dirs[0] . $userid && ! file_exists( trailingslashit( $dirs[0] . $userid ) . '.htaccess' ) ) {
				$file = fopen( trailingslashit( $dirs[0] . $userid ) . '.htaccess', 'w+' );
				fwrite( $file, 'deny from all' );
				fclose( $file );
			}
		}

		return $newdir;
	}

	/**
	 * Method get_mainwp_specific_url()
	 *
	 * get MainWP specific URL.
	 *
	 * @param mixed $dir MainWP Directory.
	 *
	 * @return string MainWP URL.
	 */
	public static function get_mainwp_specific_url( $dir ) {
		if ( MainWP_System::instance()->is_single_user() ) {
			$userid = 0;
		} else {
			global $current_user;
			$userid = $current_user->ID;
		}
		$dirs = self::get_mainwp_dir();

		return $dirs[1] . $userid . '/' . $dir . '/';
	}

	/**
	 * Method get_wp_file_system()
	 *
	 * Get WP file system & define Global Variable FS_METHOD.
	 *
	 * @return boolean $init True.
	 */
	public static function get_wp_file_system() {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			ob_start();
			if ( file_exists( ABSPATH . '/wp-admin/includes/screen.php' ) ) {
				include_once ABSPATH . '/wp-admin/includes/screen.php';
			}
			if ( file_exists( ABSPATH . '/wp-admin/includes/template.php' ) ) {
				include_once ABSPATH . '/wp-admin/includes/template.php';
			}
			$creds = request_filesystem_credentials( 'test' );
			ob_end_clean();
			if ( empty( $creds ) ) {
				define( 'FS_METHOD', 'direct' );
			}
			$init = \WP_Filesystem( $creds );
		} else {
			$init = true;
		}

		return $init;
	}

	/**
	 * Method can_edit_website()
	 *
	 * Check if current user can edit Child Site.
	 *
	 * @param mixed $website Child Site.
	 *
	 * @return mixed true|false|userid
	 */
	public static function can_edit_website( &$website ) {
		if ( null == $website ) {
			return false;
		}

		if ( MainWP_System::instance()->is_single_user() ) {
			return true;
		}

		global $current_user;

		return ( $website->userid == $current_user->ID );
	}

	/**
	 * Method get_current_wpid()
	 *
	 * Get current Child Site ID.
	 *
	 * @return string $current_user->current_site_id Current Child Site ID.
	 */
	public static function get_current_wpid() {
		global $current_user;

		return $current_user->current_site_id;
	}

	/**
	 * Method set_current_wpid()
	 *
	 * Set the current Child Site ID.
	 *
	 * @param mixed $wpid Child Site ID.
	 */
	public static function set_current_wpid( $wpid ) {
		global $current_user;
		$current_user->current_site_id = $wpid;
	}

	/**
	 * Method get_page_id()
	 *
	 * Get current Page ID.
	 *
	 * @param null $screen Current Screen ID.
	 *
	 * @return string $page Current page ID.
	 */
	public static function get_page_id( $screen = null ) {

		if ( empty( $screen ) ) {
			$screen = get_current_screen();
		} elseif ( is_string( $screen ) ) {
			$screen = convert_to_screen( $screen );
		}

		if ( ! isset( $screen->id ) ) {
			return;
		}

		$page = $screen->id;

		return $page;
	}

	/**
	 * Method get_child_response()
	 *
	 * Get response from Child Site.
	 *
	 * @param mixed $data
	 *
	 * @return json $data|true
	 */
	public static function get_child_response( $data ) {
		if ( is_serialized( $data ) ) {
			return unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore -- for compatability.
		} else {
			return json_decode( $data, true );
		}
	}

	/**
	 * Method maybe_unserialyze()
	 *
	 * Check if $data is serialized,
	 * if it isn't then base64_decode it.
	 *
	 * @param mixed $data Data to check.
	 *
	 * @return mixed $data.
	 */
	public static function maybe_unserialyze( $data ) {
		if ( '' == $data || is_array( $data ) ) {
			return $data;
		} elseif ( is_serialized( $data ) ) {
			// phpcs:ignore -- for compatability.
			return maybe_unserialize( $data );
		} else {
			// phpcs:ignore -- for compatability.
			return maybe_unserialize( base64_decode( $data ) );
		}
	}

	/**
	 * Method get_openssl_conf()
	 *
	 * Get dashboard openssl configuration.
	 */
	public static function get_openssl_conf() {

		if ( defined( 'MAINWP_CRYPT_RSA_OPENSSL_CONFIG' ) ) {
			return MAINWP_CRYPT_RSA_OPENSSL_CONFIG;
		}

		$setup_conf_loc = '';
		if ( MainWP_Settings::is_local_window_config() ) {
			$setup_conf_loc = get_option( 'mwp_setup_opensslLibLocation' );
		} elseif ( get_option( 'mainwp_opensslLibLocation' ) != '' ) {
			$setup_conf_loc = get_option( 'mainwp_opensslLibLocation' );
		}
		return $setup_conf_loc;
	}

}