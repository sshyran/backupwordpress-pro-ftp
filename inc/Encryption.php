<?php namespace HM\BackUpWordPress;

/**
 * Encrypt sensitive info
 */
class Encryption {

	/**
	 *
	 * Encrypt a string (Passwords)
	 *
	 * @param string $string value to encrypt
	 *
	 * @return string encrypted string
	 */
	public static function encrypt( $string ) {

		if ( empty( $string ) )
			return $string;

		//only encrypt if needed
		if ( strpos( $string, '$HMBKP$ENC1$' ) !== FALSE or strpos( $string, '$HMBKP$RIJNDAEL$' ) !== FALSE )
			return $string;

		$key = md5( DB_NAME . DB_USER . DB_PASSWORD );

		if ( ! function_exists( 'mcrypt_encrypt' ) ) {
			$result = '';
			for ( $i = 0; $i < strlen( $string ); $i ++ ) {
				$char    = substr( $string, $i, 1 );
				$keychar = substr( $key, ( $i % strlen( $key ) ) - 1, 1 );
				$char    = chr( ord( $char ) + ord( $keychar ) );
				$result .= $char;
			}

			return '$HMBKP$ENC1$' . base64_encode( $result );
		}

		return '$HMBKP$RIJNDAEL$' . base64_encode( mcrypt_encrypt( MCRYPT_RIJNDAEL_256, md5( $key ), $string, MCRYPT_MODE_CBC, md5( md5( $key ) ) ) );
	}

	/**
	 *
	 * Decrypt a string (Passwords)
	 *
	 * @param string $string value to decrypt
	 *
	 * @return string decrypted string
	 */
	public static function decrypt( $string ) {

		if ( empty( $string ) )
			return $string;

		$key = md5( DB_NAME . DB_USER . DB_PASSWORD );

		if ( strpos( $string, '$HMBKP$ENC1$' ) !== FALSE ) {
			$string = str_replace( '$HMBKP$ENC1$', '', $string );
			$result = '';
			$string = base64_decode( $string );
			for ( $i = 0; $i < strlen( $string ); $i ++ ) {
				$char    = substr( $string, $i, 1 );
				$keychar = substr( $key, ( $i % strlen( $key ) ) - 1, 1 );
				$char    = chr( ord( $char ) - ord( $keychar ) );
				$result .= $char;
			}

			return $result;
		}

		if ( function_exists( 'mcrypt_encrypt' ) && strpos( $string, '$HMBKP$RIJNDAEL$' ) !== FALSE) {
			$string = str_replace( '$HMBKP$RIJNDAEL$', '', $string );

			return rtrim( mcrypt_decrypt( MCRYPT_RIJNDAEL_256, md5( $key ), base64_decode( $string ), MCRYPT_MODE_CBC, md5( md5( $key ) ) ), "\0" );
		}

		return $string;
	}
}