<?php
/**
 * Plugin Name:       Simple SiteGround SMTP
 * Plugin URI:        https://instagram.com/noman_imtiaz11
 * Description:       A very simple, lightweight and secure plugin to route WordPress emails through SiteGround (or any) SMTP server. No bloat, just what you need.
 * Version:           1.0.0
 * Author:            Abdullah Al Noman
 * Author URI:        https://instagram.com/noman_imtiaz11
 * License:           GPL-2.0+
 * Text Domain:       simple-siteground-smtp
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SSGSMTP_OPTION_KEY', 'ssgsmtp_settings' );

/**
 * Get the saved settings, merged with defaults.
 */
function ssgsmtp_get_settings() {
	$defaults = array(
		'host'       => 'mail.yourdomain.com', // SiteGround: usually mail.<yourdomain>.com
		'port'       => '465',
		'encryption' => 'ssl', // ssl, tls, or none
		'auth'       => '1',
		'username'   => '',
		'password'   => '',
		'from_email' => '',
		'from_name'  => get_bloginfo( 'name' ),
	);

	$saved = get_option( SSGSMTP_OPTION_KEY, array() );
	return wp_parse_args( $saved, $defaults );
}

/**
 * Hook into PHPMailer to configure SMTP for every outgoing email.
 */
add_action( 'phpmailer_init', 'ssgsmtp_configure_phpmailer' );
function ssgsmtp_configure_phpmailer( $phpmailer ) {
	$s = ssgsmtp_get_settings();

	if ( empty( $s['host'] ) ) {
		return; // Not configured yet — let WordPress use default mail().
	}

	$phpmailer->isSMTP();
	$phpmailer->Host = $s['host'];
	$phpmailer->Port = (int) $s['port'];

	if ( 'none' !== $s['encryption'] ) {
		$phpmailer->SMTPSecure = $s['encryption']; // 'ssl' or 'tls'
	} else {
		$phpmailer->SMTPSecure = '';
		$phpmailer->SMTPAutoTLS = false;
	}

	if ( '1' === $s['auth'] ) {
		$phpmailer->SMTPAuth = true;
		$phpmailer->Username = $s['username'];
		$phpmailer->Password = ssgsmtp_decrypt( $s['password'] );
	} else {
		$phpmailer->SMTPAuth = false;
	}

	if ( ! empty( $s['from_email'] ) && is_email( $s['from_email'] ) ) {
		$phpmailer->setFrom( $s['from_email'], $s['from_name'], false );
	}
}

/* -------------------------------------------------------------------------
 * Lightweight password encryption at rest.
 * Uses WordPress salts as the key so the raw password is never stored plainly.
 * ---------------------------------------------------------------------- */

function ssgsmtp_crypt_key() {
	// Derive a stable 32-byte key from WP auth salts.
	$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : '' );
	return hash( 'sha256', 'ssgsmtp' . $material, true );
}

function ssgsmtp_encrypt( $plaintext ) {
	if ( '' === $plaintext || ! function_exists( 'openssl_encrypt' ) ) {
		return $plaintext;
	}
	$iv        = openssl_random_pseudo_bytes( 16 );
	$encrypted = openssl_encrypt( $plaintext, 'aes-256-cbc', ssgsmtp_crypt_key(), OPENSSL_RAW_DATA, $iv );
	return base64_encode( $iv . $encrypted );
}

function ssgsmtp_decrypt( $stored ) {
	if ( '' === $stored || ! function_exists( 'openssl_decrypt' ) ) {
		return $stored;
	}
	$raw = base64_decode( $stored, true );
	if ( false === $raw || strlen( $raw ) < 17 ) {
		return $stored; // Not encrypted (or corrupt) — return as-is.
	}
	$iv        = substr( $raw, 0, 16 );
	$encrypted = substr( $raw, 16 );
	$decrypted = openssl_decrypt( $encrypted, 'aes-256-cbc', ssgsmtp_crypt_key(), OPENSSL_RAW_DATA, $iv );
	return ( false === $decrypted ) ? $stored : $decrypted;
}

/* -------------------------------------------------------------------------
 * Admin settings page
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', 'ssgsmtp_admin_menu' );
function ssgsmtp_admin_menu() {
	add_options_page(
		'Simple SiteGround SMTP',
		'SMTP Settings',
		'manage_options',
		'simple-siteground-smtp',
		'ssgsmtp_settings_page'
	);
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ssgsmtp_action_links' );
function ssgsmtp_action_links( $links ) {
	$url = admin_url( 'options-general.php?page=simple-siteground-smtp' );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">Settings</a>' );
	return $links;
}

function ssgsmtp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$notice = '';

	// Handle "Save settings".
	if ( isset( $_POST['ssgsmtp_save'] ) && check_admin_referer( 'ssgsmtp_save_settings' ) ) {
		$current = ssgsmtp_get_settings();

		$new = array(
			'host'       => sanitize_text_field( wp_unslash( $_POST['host'] ?? '' ) ),
			'port'       => sanitize_text_field( wp_unslash( $_POST['port'] ?? '465' ) ),
			'encryption' => in_array( $_POST['encryption'] ?? '', array( 'ssl', 'tls', 'none' ), true ) ? $_POST['encryption'] : 'ssl',
			'auth'       => isset( $_POST['auth'] ) ? '1' : '0',
			'username'   => sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) ),
			'from_email' => sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) ),
			'from_name'  => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
		);

		// Only update the password if a new one was typed.
		$typed_password = (string) wp_unslash( $_POST['password'] ?? '' );
		if ( '' !== $typed_password ) {
			$new['password'] = ssgsmtp_encrypt( $typed_password );
		} else {
			$new['password'] = $current['password'];
		}

		update_option( SSGSMTP_OPTION_KEY, $new );
		$notice = 'Settings saved.';
	}

	// Handle "Send test email".
	if ( isset( $_POST['ssgsmtp_test'] ) && check_admin_referer( 'ssgsmtp_send_test' ) ) {
		$to      = sanitize_email( wp_unslash( $_POST['test_to'] ?? '' ) );
		$success = false;
		if ( is_email( $to ) ) {
			$success = wp_mail(
				$to,
				'Test email from ' . get_bloginfo( 'name' ),
				"Success! Your Simple SiteGround SMTP plugin is working.\n\nSent: " . current_time( 'mysql' )
			);
		}
		$notice = $success
			? 'Test email sent to ' . esc_html( $to ) . '. Check the inbox.'
			: 'Test email FAILED to send. Double-check your SMTP settings below.';
	}

	$s = ssgsmtp_get_settings();
	?>
	<div class="wrap">
		<h1>Simple SiteGround SMTP</h1>

		<?php if ( $notice ) : ?>
			<div class="notice notice-info is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
		<?php endif; ?>

		<p>Route all WordPress emails through your SiteGround SMTP server. For SiteGround, create an email account in Site Tools &rarr; Email &rarr; Accounts, then use these values:</p>
		<ul style="list-style: disc; margin-left: 20px;">
			<li><strong>Host:</strong> <code>mail.yourdomain.com</code></li>
			<li><strong>Port / Encryption:</strong> <code>465</code> with <code>SSL</code> (or <code>587</code> with <code>TLS</code>)</li>
			<li><strong>Username:</strong> the full email address you created</li>
			<li><strong>Password:</strong> that email account's password</li>
		</ul>

		<form method="post" action="">
			<?php wp_nonce_field( 'ssgsmtp_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="host">SMTP Host</label></th>
					<td><input name="host" id="host" type="text" class="regular-text" value="<?php echo esc_attr( $s['host'] ); ?>" placeholder="mail.yourdomain.com" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="port">SMTP Port</label></th>
					<td><input name="port" id="port" type="number" class="small-text" value="<?php echo esc_attr( $s['port'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row">Encryption</th>
					<td>
						<label><input type="radio" name="encryption" value="ssl" <?php checked( $s['encryption'], 'ssl' ); ?> /> SSL</label> &nbsp;
						<label><input type="radio" name="encryption" value="tls" <?php checked( $s['encryption'], 'tls' ); ?> /> TLS</label> &nbsp;
						<label><input type="radio" name="encryption" value="none" <?php checked( $s['encryption'], 'none' ); ?> /> None</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Authentication</th>
					<td><label><input type="checkbox" name="auth" value="1" <?php checked( $s['auth'], '1' ); ?> /> Use SMTP authentication (recommended)</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="username">SMTP Username</label></th>
					<td><input name="username" id="username" type="text" class="regular-text" value="<?php echo esc_attr( $s['username'] ); ?>" placeholder="you@yourdomain.com" autocomplete="off" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="password">SMTP Password</label></th>
					<td>
						<input name="password" id="password" type="password" class="regular-text" value="" placeholder="<?php echo $s['password'] ? '••••••••••  (leave blank to keep current)' : 'Enter password'; ?>" autocomplete="new-password" />
						<p class="description">Stored encrypted using your site's security keys. Leave blank to keep the existing password.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="from_email">From Email</label></th>
					<td><input name="from_email" id="from_email" type="email" class="regular-text" value="<?php echo esc_attr( $s['from_email'] ); ?>" placeholder="you@yourdomain.com" />
						<p class="description">Best to match your SMTP username so emails aren't marked as spam.</p></td>
				</tr>
				<tr>
					<th scope="row"><label for="from_name">From Name</label></th>
					<td><input name="from_name" id="from_name" type="text" class="regular-text" value="<?php echo esc_attr( $s['from_name'] ); ?>" /></td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings', 'primary', 'ssgsmtp_save' ); ?>
		</form>

		<hr />

		<h2>Send a Test Email</h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'ssgsmtp_send_test' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="test_to">Send to</label></th>
					<td><input name="test_to" id="test_to" type="email" class="regular-text" value="<?php echo esc_attr( wp_get_current_user()->user_email ); ?>" /></td>
				</tr>
			</table>
			<?php submit_button( 'Send Test Email', 'secondary', 'ssgsmtp_test' ); ?>
		</form>
	</div>
	<?php
}
