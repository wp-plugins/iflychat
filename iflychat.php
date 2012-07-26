<?php
/**
 * @package iflychat
 * @version 1.0.3
 */
/*
Plugin Name: iFlyChat
Plugin URI: http://wordpress.org/extend/plugins/iflychat/
Description: One on one chat 
Author: Shashwat Srivastava, Shubham Gupta, Varun Kapoor - iFlyChat Team
Version: 1.0.3
Author URI: https://iflychat.com/
*/

define('DRUPALCHAT_EXTERNAL_HOST', 'http://api.iflychat.com');
define('DRUPALCHAT_EXTERNAL_PORT', '80');
define('DRUPALCHAT_EXTERNAL_A_HOST', 'https://api.iflychat.com');
define('DRUPALCHAT_EXTERNAL_A_PORT', '443');

function iflychat_get_hash_session() {
  $data = uniqid(mt_rand(), TRUE);
  $hash = base64_encode(hash('sha256', $data, TRUE));
  return strtr($hash, array('+' => '-', '/' => '_', '=' => ''));
}

function iflychat_get_user_id() {
  global $current_user;
  get_currentuserinfo();
  global $wpdb;
  if($current_user->ID) {
    return strval($current_user->ID);
  }
  else {
    if((isset($_COOKIE['iflychat_session']) && ($_COOKIE['iflychat_session']))) {
	  $sid = $wpdb->get_col($wpdb->prepare("SELECT sid FROM " . $wpdb->prefix . "iflychat_users WHERE session = %s;", $_COOKIE['iflychat_session']));
	  return "0-" . $sid[0];
	}
	else if((isset($_SESSION['iflychat_session']) && ($_SESSION['iflychat_session']))) {
	  $sid = $wpdb->get_col($wpdb->prepare("SELECT sid FROM " . $wpdb->prefix . "iflychat_users WHERE session = %s;", $_SESSION['iflychat_session']));
	  setcookie('iflychat_session', $new_session, time()+1209600, "/", COOKIE_DOMAIN, false);
	  return "0-" . $sid[0];
	}
	else {
	  $new_session = iflychat_get_hash_session();
	  $name = 'Guest' . time();
	  $wpdb->insert($wpdb->prefix . "iflychat_users", array('session' => $new_session, 'name' => $name, 'time' => time()), array('%s', '%s', '%d'));
	  setcookie('iflychat_session', $new_session, time()+1209600, "/", COOKIE_DOMAIN, false);
	  $_SESSION['iflychat_session'] = $new_session;
	  $sid = $wpdb->get_col($wpdb->prepare("SELECT sid FROM " . $wpdb->prefix . "iflychat_users WHERE session = %s;", $new_session));
	  return "0-". $sid[0];
	}
  }
}

function iflychat_get_user_name() {
  global $current_user;
  get_currentuserinfo();
  global $wpdb;
  if($current_user->ID) {
    return $current_user->display_name;
  }
  else {
    if((isset($_COOKIE['iflychat_session']) && ($_COOKIE['iflychat_session']))) {
	  $sid = $wpdb->get_col($wpdb->prepare("SELECT name FROM " . $wpdb->prefix . "iflychat_users WHERE session = %s;", $_COOKIE['iflychat_session']));
	  return $sid[0];
	}
	else if((isset($_SESSION['iflychat_session']) && ($_SESSION['iflychat_session']))) {
	  $sid = $wpdb->get_col($wpdb->prepare("SELECT name FROM " . $wpdb->prefix . "iflychat_users WHERE session = %s;", $_SESSION['iflychat_session']));
	  return $sid[0];
	}
	else {
	  return "Visitor";
	}
  }
}

function iflychat_init() {
  global $current_user;
  get_currentuserinfo();
  $my_settings = array(
      'uid' => iflychat_get_user_id(),
	  'username' => iflychat_get_user_name(),
      'current_timestamp' => time(),
      'polling_method' => "3",
      'pollUrl' => " ",
      'sendUrl' => " ",
      'statusUrl' => " ",
      'status' => "1",
      'goOnline' => 'Go Online',
      'goIdle' => 'Go Idle',
      'newMessage' => 'New chat message!',
      'images' => plugin_dir_url( __FILE__ ) . 'themes/light/images/',
      'sound' => plugin_dir_url( __FILE__ ) . '/swf/sound.swf',
      'noUsers' => "<div class=\"item-list\"><ul><li class=\"drupalchatnousers even first last\">No users online</li></ul></div>",
      'smileyURL' => plugin_dir_url( __FILE__ ) . 'smileys/very_emotional_emoticons-png/png-32x32/',
      'addUrl' => " ",
	  'notificationSound' => get_option('iflychat_notification_sound', 1),
    );
	if($my_settings['polling_method'] == "3") {
	  if (is_ssl()) {
        $my_settings['external_host'] = DRUPALCHAT_EXTERNAL_A_HOST;
        $my_settings['external_port'] = DRUPALCHAT_EXTERNAL_A_PORT;
        $my_settings['external_a_host'] = DRUPALCHAT_EXTERNAL_A_HOST;
        $my_settings['external_a_port'] = DRUPALCHAT_EXTERNAL_A_PORT;		
	  }
	  else {
	    $my_settings['external_host'] = DRUPALCHAT_EXTERNAL_HOST;
        $my_settings['external_port'] = DRUPALCHAT_EXTERNAL_PORT;
		$my_settings['external_a_host'] = DRUPALCHAT_EXTERNAL_A_HOST;
        $my_settings['external_a_port'] = DRUPALCHAT_EXTERNAL_A_PORT;
	  }
	}
    $protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';
    $my_settings['geturl'] = admin_url('admin-ajax.php', $protocol);
  wp_enqueue_script( 'iflychat-emotify', plugin_dir_url( __FILE__ ) . 'js/ba-emotify.js', array('jquery'));	
  wp_enqueue_script( 'iflychat-titlealert', plugin_dir_url( __FILE__ ) . 'js/jquery.titlealert.min.js', array('jquery'));	
  wp_enqueue_script( 'iflychat-ajax', plugin_dir_url( __FILE__ ) . 'js/script.js', array('jquery'));
  wp_localize_script('iflychat-ajax', 'Drupal', array("settings" => array("drupalchat" => $my_settings, "basePath" => get_site_url() . "/")));
}

function _iflychat_get_auth($name) {
  if(get_option('iflychat_api_key') == " ") {
	return null;
  }
  global $current_user;
  get_currentuserinfo();
  if(is_admin()) {
    $role = "admin";
  }
  else {
    $role = "normal";
  }
  
  $data = json_encode(array(
    'uid' => iflychat_get_user_id(),
	'uname' => iflychat_get_user_name(),
    'api_key' => get_option('iflychat_api_key'),
	'image_path' => plugin_dir_url( __FILE__ ) . 'themes/light/images',
	'isLog' => TRUE,
	'whichTheme' => 'blue',
	'enableStatus' => TRUE,
	'role' => $role,
	'validState' => array('available','offline','busy','idle')));
  $options = array(
    'method' => 'POST',
    'body' => $data,
    'timeout' => 15,
    'headers' => array('Content-Type' => 'application/json'),
  );

  $result = wp_remote_head(DRUPALCHAT_EXTERNAL_A_HOST . ':' . DRUPALCHAT_EXTERNAL_A_PORT .  '/p/', $options);
 
  if($result['response']['code'] == 200) {
    $result = json_decode($result['body']);
    //drupal_add_css(DRUPALCHAT_EXTERNAL_A_HOST . ':' . DRUPALCHAT_EXTERNAL_A_PORT .  '/i/' . $result['css'] . '/cache.css', 'external');
    return $result;
  }
  else {
    return null;
  }
}

function iflychat_submit_uth() {
  $user_name = NULL;
  $json = NULL;
  if(iflychat_get_user_id()) {
    $user_name = iflychat_get_user_name(); 
  }
  if($user_name) {
    $json = _iflychat_get_auth($user_name);  
    $json->name = $user_name;
  }
  $response = json_encode($json);
  header("Content-Type: application/json");
  echo $response;
  exit;
}

function iflychat_install() {
  global $wpdb;
  $table_name = $wpdb->prefix . "iflychat_users"; 
  $sql = "CREATE TABLE IF NOT EXISTS $table_name (
      sid int(11) NOT NULL AUTO_INCREMENT,
	  session varchar(128) NOT NULL,
	  name varchar(30) NOT NULL,
      time int(11) NOT NULL,
	  PRIMARY KEY sid (sid),
	  UNIQUE KEY session (session)
  );";
  require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  dbDelta($sql);
}

function iflychat_uninstall() {
  delete_option('iflychat_api_key');
  global $wpdb;
  $iflychat_table = $wpdb->prefix . "iflychat_users";
  //Delete any options that's stored also?
  //delete_option('wp_yourplugin_version');
  $wpdb->query("DROP TABLE IF EXISTS $iflychat_table");
}
/*
// add the admin options page
add_action('admin_menu', 'iflychat_admin_add_page');
function iflychat_admin_add_page() {
  add_options_page('iFlyChat Plugin Configuration Page', 'iFlyChat Menu', 'manage_options', 'iflychat', 'iflychat_options_page');
}
// display the admin options page
function iflychat_options_page() {
  echo '<div>
    <h2>iFlyChat Plugin Configuration Page</h2>
    Configure iFlyChat Plugin here.
    <form action="options.php" method="post">';
  settings_fields('iflychat_options');
  do_settings_sections('iflychat');
  submit_button();
  echo '</form></div>';
}
add_action('admin_init', 'iflychat_admin_init');
function iflychat_admin_init(){
  register_setting('iflychat_options', 'iflychat_options', 'iflychat_options_validate');
  add_settings_section('iflychat_main', 'iFlyChat Main Settings', 'iflychat_main_section_text', 'iflychat');
  add_settings_field('iflychat_api_key', 'iflyChat API Key', 'iflychat_setting_string', 'iflychat', 'iflychat_main');
}
function plugin_section_text() {
  echo '<p>iFlyChat Main Settings</p>';
} 
function iflychat_setting_string() {
  $options = get_option('iflychat_options');
  echo "<input id='plugin_text_string' name='plugin_options[text_string]' size='40' type='text' value='{$options['text_string']}' />";
}
*/

/*

HOW TO USE OPTIONS ARRAY

each option needs a name, default value, description, and input_type (dropdown or text)
dropdown options need a data field that takes a single dimensional associative array as its value

name - code friendly name of option
default - set the default value
desc - description of the option, will show up on the options page next to the option
input type - dropdown or text
data - single dimensional assoc. array as variable or create your own array within, see examples


*/


function iflychat_set_options(){
	
	//call custom functions if you need special data (or not so special data…)
	//$cat_data = iflychat_get_categories();

	
	$options = array(
		/*'post_category' => array ( //option 'slug'
			'name' => 'timeline_post_category', 
			'default' => '0', 
			'desc' => 'Select a post category for your timeline:', 
			'input_type' => 'dropdown', 
			'data' => $cat_data //data should be single dimensional assoc array
			),*/
		'api_key' => array ( 
			'name' => 'iflychat_api_key', 
			'default' => ' ', 
			'desc' => 'Please enter the API key by registering at <a href="https://iflychat.com">iFlyChat.com</a>', 
			'input_type' => 'text'
			),
		/*'include_images' => array ( 
			'name' => 'timeline_include_images', 
			'default' => 'no', 
			'desc' => 'Do you want to include featured image thumbnails?', 
			'input_type' => 'dropdown', 
			'data' => array( //manual dropdown options
				'yes' => 'yes', 
				'no' => 'no')
				),
		'post_order' => array ( 
			'name' => 'timeline_order_posts' , 
			'default' => 'DESC', 
			'desc' => 'How do you want to order your posts?', 
			'input_type' => 'dropdown', 
			'data' => array(
				'Ascending' => 'ASC', 
				'Descending' => 'DESC') 
			)
			*/
	);

	return $options;
	
}

//create settings page
function iflychat_settings() {
	?>
		<div class="wrap">	
			<h2><?php _e('iflychat Settings', iflychat_NAME_UNIQUE); ?></h2>
		<?php
		if (isset($_GET['updated']) && $_GET['updated'] == 'true') {
			?>
			<div id="message" class="updated fade"><p><strong><?php _e('Settings Updated', iflychat_NAME_UNIQUE); ?></strong></p></div>
			<?php
		}
		?>
			<form method="post" action="<?php echo esc_url('options.php');?>">
				<div>
					<?php settings_fields('iflychat-settings'); ?>
				</div>
				
				<?php
					$options = iflychat_set_options();
					
					?>
				<table class="form-table">
				<?php foreach($options as $option){ ?>
					<?php 
						//if option type is a dropdown, do this
						if ( $option['input_type'] == 'dropdown'){ ?>
							<tr valign="top">
				        		<th scope="row"><?php _e($option['desc'], iflychat_NAME_UNIQUE); ?></th>
				        			<td><select id="<?php echo $option['name']; ?>" name="<?php echo $option['name']; ?>">
				        					<?php foreach($option['data'] as $opt => $value){ ?>
												<option <?php if(get_option($option['name']) == $value){ echo 'selected="selected"';}?> name="<?php echo $option['name']; ?>" value="<?php echo $value; ?>"><?php echo $opt ; ?></option>
												<?php } //endforeach ?>
										</select>
									</td>
					        </tr>
				    <?php 
				    	//if option type is text, do this
				    	}elseif ( $option['input_type'] == 'text'){ ?>
				    		<tr valign="top">
				        		<th scope="row"><?php _e($option['desc'], iflychat_NAME_UNIQUE); ?></th>
				        			<td><input id="<?php echo $option['name']; ?>" name="<?php echo $option['name']; ?>" value="<?php echo get_option($option['name']); ?>" />
									</td>
					        </tr>
			     <?php 
			     		
			     		}else{} //endif
			     		
			     	} //endforeach ?>
			        
			    </table>
			    <p class="submit"><input type="submit" class="button-primary" value="<?php _e('Update', iflychat_NAME_UNIQUE); ?>" /></p>
			</form>
		</div>
	<?php
}

//register settings loops through options
function iflychat_register_settings()
{
	$options = iflychat_set_options(); //get options array
	
	foreach($options as $option){
		register_setting('iflychat-settings', $option['name']); //register each setting with option's 'name'
		
		if (get_option($option['name']) === false) {
			add_option($option['name'], $option['default'], '', 'yes'); //set option defaults
		}
	}

	if (get_option('iflychat_promote_plugin') === false) {
		add_option('iflychat_promote_plugin', '0', '', 'yes');
	}

}
add_action( 'admin_init', 'iflychat_register_settings' );


//add settings page
function iflychat_settings_page() {	
	add_options_page('iflychat Settings', 'iflychat Settings', 'manage_options', iflychat_NAME_UNIQUE, 'iflychat_settings');
}
add_action("admin_menu", 'iflychat_settings_page');


//add_action( 'admin_head', 'dolly_css' );
add_action('init', 'iflychat_init');
add_action( 'wp_ajax_nopriv_iflychat-get', 'iflychat_submit_uth' );
add_action( 'wp_ajax_iflychat-get', 'iflychat_submit_uth' );
register_activation_hook(__FILE__,'iflychat_install');
register_deactivation_hook( __FILE__, 'iflychat_uninstall');
?>
