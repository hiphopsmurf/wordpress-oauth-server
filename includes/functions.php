<?php
/**
 * WordPress OAuth Functions
 * @author Justin Greer <justin@justin-greer.com>
 * @package WordPress OAuth Server
 */

/**
 * Generate a key
 * A 60 Charecter key is generated by default but should be adjustable in the admin
 * @return [type] [description]
 *
 * @todo Allow more characters to be added to the character list to provide comlex keys
 */
function wo_gen_key($length = 40) {
	$options = get_option("wo_options");
	$user_defined_length = (int) $options["client_id_length"];
	if ($user_defined_length > 0) {
		$length = $user_defined_length;
	}

	$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	$randomString = '';
	for ($i = 0; $i < $length; $i++) {
		$randomString .= $characters[rand(0, strlen($characters) - 1)];
	}
	return $randomString;
}

/**
 * Blowfish Encryptions
 * @param  [type]  $input  [description]
 * @param  integer $rounds [description]
 * @return [type]          [description]
 *
 * REQUIRES ATLEAST 5.3.x
 */
function wo_crypt($input, $rounds = 7) {
	$salt = "";
	$salt_chars = array_merge(range('A', 'Z'), range('a', 'z'), range(0, 9));
	for ($i = 0; $i < 22; $i++) {
		$salt .= $salt_chars[array_rand($salt_chars)];
	}
	return crypt($input, sprintf('$2a$%02d$', $rounds) . $salt);
}

/**
 * Get the client IP multiple ways since REMOTE_ADDR is not always the best way to do so
 * @return [type] [description]
 */
function client_ip(){
	$ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
        $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
 
    return $ipaddress;
}

/**
 * @param  [type] $l [description]
 * @return [type]    [description]
 */
function _vl($l) {
	return md5(basename(__FILE__)) == $l;
}

function edit_client_form($client_id)
{
	global $wpdb;
	$client = $wpdb->get_row("
		SELECT * FROM {$wpdb->prefix}oauth_clients 
		WHERE client_id='{$client_id}'", 
		ARRAY_A);

	return '<div class="wo-popup-inner">
						<h3 class="header">Update '.$client['name'].'</h3>
						<form onsubmit="wo_update_client(this); return false;" action="/" method="post">
							<label>Client Name *</label>
							<input type="text" name="client_name" placeholder="Client Name" value="'.$client['name'].'"/>

							<label>Redirct URI *</label>
							<input type="text" name="redirect_uri" placeholder="Redirect URI" value="'.$client['redirect_uri'].'"/>

							<label>Client Description</label>
							<textarea name="client_description">'.$client['description'].'</textarea>

							<!--<label></label>
							<input type="text" name="redirect_uri" value="Client ID: '.$client['client_id'].'" disabled="disbaled"/>
							<label></label>
							<input type="text" name="redirect_uri" value="Client Sccret: '.$client['client_secret'].'" disabled="disbaled"/>
							-->

							<input type="hidden" name="client_id" value="'.$client_id.'" />
							<input type="submit" class="button button-primary" value="Update Client" />
						</form>
					</div>';
}

/**
 * WordPress OAuth Server Firewall
 * Called if and license is valid and the firewall is enabled
 */
add_action('wo_before_api', 'wordpress_oauth_firewall_init', 10);
function wordpress_oauth_firewall_init() {
	$options = get_option('wo_options');
	if(!_vl($options['license']))
		return;

	if(isset($options['firewall_block_all_incomming']) && $options['firewall_block_all_incomming']){
		$remote_addr = client_ip();
		$whitelist = str_replace(' ', '',$options['firewall_ip_whitelist']); // remove all whitespace
		$whitelist_array = explode(',', $whitelist);
		if(in_array($remote_addr, $whitelist_array))
			return;

		header('Content-Type: application/json');
		$response = array(
			'error' => 'Unauthorized'
			);
		print json_encode($response);
		exit;
	}
}
