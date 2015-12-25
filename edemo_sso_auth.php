<?php
	/*
		Plugin Name: Edemo SSO authentication
		Plugin URI: 
		Description: Allows you connect to the Edemo SSO server, and autenticate the users, who acting on your site
		Version: 0.01
		Author: Claymanus
		Author URI:
	*/

### Version
define( 'EDEMO_SSO_VERSION', 0.01 );

class eDemoSSO {

	const    SSO_DOMAIN = 'sso.edemokraciagep.org';
	const SSO_TOKEN_URI = 'sso.edemokraciagep.org/v1/oauth2/token';
	const  SSO_AUTH_URI = 'sso.edemokraciagep.org/v1/oauth2/auth';
	const  SSO_USER_URI = 'sso.edemokraciagep.org/v1/users/me';
	const  SSO_USERS_URI = 'sso.edemokraciagep.org/v1/users';
	const     QUERY_VAR = 'sso_callback';
	const     USER_ROLE = 'eDemo_SSO_role';
	const  CALLBACK_URI = 'sso_callback';
	const   USERMETA_ID = 'eDemoSSO_ID'; 
	const USERMETA_TOKEN = 'eDemoSSO_refresh_token';
	const USERMETA_ASSURANCES = 'eDemoSSO_assurances';
	const  WP_REDIR_VAR = 'wp_redirect';
	const SSO_LOGIN_URL = 'sso.edemokraciagep.org/static/login.html';

	static $callbackURL;
	public $error_message;
	public $auth_message;
	static $appkey;
	static $allowBind;
	private $secret;
	private $sslverify;
	private $access_token;
	private $refresh_token;

	function __construct() {
		
		$self=$this;
		add_option('eDemoSSO_appkey', '', '', 'yes');
		add_option('eDemoSSO_secret', '', '', 'yes');
		add_option('eDemoSSO_appname', '', '', 'yes');
		add_option('eDemoSSO_sslverify', '', '', 'yes');
		add_option('eDemoSSO_allowBind', '', '', 'yes');
    
		self::$callbackURL = get_site_url( "", "", "https" )."/".self::CALLBACK_URI;
		self::$appkey = get_option('eDemoSSO_appkey');
		self::$allowBind = get_option('eDemoSSO_allowBind');
		$this->secret = get_option('eDemoSSO_secret');
		$this->sslverify = get_option('eDemoSSO_sslverify');
        
		
		### Adding sso callback function to rewrite rules
		add_action( 'generate_rewrite_rules', array( $this, 'add_rewrite_rules' ) );
		add_action( 'login_footer', array( $this, 'add_login_button' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'the_content', array( $this, 'the_content_filter' ) );

		### Plugin activation hooks
		register_activation_hook( __FILE__, array( $this, 'plugin_activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'plugin_deactivation' ) );
		
		
		add_action( 'parse_request', array( $this, 'parse_request' ) );
		add_shortcode('SSOsignit', array( $this, 'sign_it' ) );	
		
		### Adding admin page
		add_action('admin_menu', array( $this, 'addAdminPage' ) );

		### Create Text Domain For Translations
		add_action( 'plugins_loaded', array( $this, 'textdomain' ) );
	
		### Show SSO data
		add_action( 'show_user_profile', array ( $this, 'show_SSO_user_profile' ) );
		add_action( 'edit_user_profile', array ( $this, 'show_SSO_user_profile' ) );
		add_action( 'wp_login', array ( $this, 'get_SSO_assurances'), 10, 1);
		
		### registering widgets
		add_action( 'widgets_init', array ( $this, 'register_widgets' ) );
	}
//	static function get_appkey() {return this->$appkey;}
	
//	static function get_callbackURL() {return self->$callbackURL;}
	
	static function user_has_SSO($user_id) {
		return get_user_meta($user_id,self::USERMETA_ID, true)!='';
	}
	
	function register_widgets() {
		register_widget( 'eDemoSSO_login' );
	}
	
	function get_refresh_token($user_id) {
		return get_user_meta($user_id,self::USERMETA_TOKEN, true);
	}

	function get_SSO_assurances($user_login) {
		$user=get_user_by('login',$user_login);
		$refresh_token=$this->get_refresh_token($user->ID);
		if ($this->access_token == '' and $refresh_token!='' ) {
			if ( $token=$this->request_new_token($refresh_token) ) {
				$this->access_token=$token['access_token'];	
				if ( $user_data = $this->requestUserData( ) ) {
					if ( $ssoUsers = get_users( array('meta_key' => self::USERMETA_ID, 'meta_value' => $user_data['userid']) ) ) {
						$ssoUser=$ssoUsers[0]->data;
//						if ($ssoUser[]) {
							
//						}
					}
				}				
			}
		}
	}
	
	function SSO_client_token_requiest() {
// not implemented yet
	}
	
	function add_login_button() { ?>
	<div align="center">
		<a href="https://<?=self::SSO_AUTH_URI?>?response_type=code&client_id=<?=self::$appkey?>&redirect_uri=<?=urlencode(self::$callbackURL.'?'.self::WP_REDIR_VAR.'=/&SSO_action=login')?>">
			<div class="btn">SSO login</div>
		</a>
	</div>
	<?php }
 
function show_SSO_user_profile( $user ) { ?>
 
    <h3><?= __( 'SSO user data' )?></h3>

    <table class="form-table">
 
        <tr>
            <th>SSO id</th>
            <td><?= get_user_meta($user->ID,self::USERMETA_ID, true) ?></td>
		</tr>
		<tr>
            <th>SSO token</th>
            <td><?= get_user_meta($user->ID,self::USERMETA_TOKEN, true) ?></td>
		</tr>
		<tr>			
			<th>SSO assurances</th>
            <td><?= get_user_meta($user->ID,self::USERMETA_ASSURANCES, true) ?></td>
        </tr>
 
    </table>
<?php }


	function textdomain() {
		load_plugin_textdomain( 'eDemoSSO' );
	}
	
	//
	// Options/admin panel
	//

	// Add page to options menu.
	function addAdminPage() 
	{
	  // Add a new submenu under Options:
		add_options_page('eDemo SSO Options', 'eDemo SSO', 'manage_options', 'edemosso', array( $this, 'displayAdminPage'));
	}

	// Display the admin page.
	function displayAdminPage() {
		
		if (isset($_POST['edemosso_update'])) {
//			check_admin_referer();    // EZT MAJD MEG KELLENE NÉZNI !!!!!

			// Update options 
			$this->sslverify = isset($_POST['EdemoSSO_sslverify']);
			self::$appkey    = $_POST['EdemoSSO_appkey'];
			$this->secret    = $_POST['EdemoSSO_secret'];
			$this->appname   = $_POST['EdemoSSO_appname'];
			self::$allowBind = $_POST['EdemoSSO_allowBind'];
			update_option( 'eDemoSSO_appkey'   , self::$appkey   );
			update_option( 'eDemoSSO_secret'   , $this->secret   );
			update_option( 'eDemoSSO_appname'  , $this->appname  );
			update_option( 'eDemoSSO_sslverify', $this->sslverify);
			update_option( 'eDemoSSO_allowBind', self::$allowBind);

			// echo message updated
			echo "<div class='updated fade'><p>Options updated.</p></div>";
		}		
		?>
		<div class="wrap">

			<h2><?= __( 'eDemo SSO Authentication Options' ) ?></h2>
			<form method="post">
				<fieldset class='options'>
					<table class="editform" cellspacing="2" cellpadding="5" width="100%">
						<tr>
							<th width="30%" valign="top" style="padding-top: 10px;">
								<label for="EdemoSSO_appname"><?= __( 'Application name:' ) ?></label>
							</th>
							<td>
								<input type='text' size='16' maxlength='30' name='EdemoSSO_appname' id='EdemoSSO_appname' value='<?= get_option('eDemoSSO_appname'); ?>' />
								<?= __( 'Used for registering the application' ) ?>
							</td>
						</tr>
						<tr>
							<th width="30%" valign="top" style="padding-top: 10px;">
								<label for="EdemoSSO_appkey"><?= __( 'Application key:' ) ?></label>
							</th>
							<td>
								<input type='text' size='40' maxlength='40' name='EdemoSSO_appkey' id='EdemoSSO_appkey' value='<?= self::$appkey; ?>' />
								<?= __( 'Application key.' ) ?>
							</td>
						</tr>
						<tr>
							<th width="30%" valign="top" style="padding-top: 10px;">
								<label for="EdemoSSO_secret"><?= __( 'Application secret:' ) ?></label>
							</th>
							<td>
								<input type='text' size='40' maxlength='40' name='EdemoSSO_secret' id='EdemoSSO_secret' value='<?= $this->secret; ?>' />
								<?= __( 'Application secret.' ) ?>
							</td>
						</tr>
						<tr>
							<th width="30%" valign="top" style="padding-top: 10px;">
								<label for="EdemoSSO_sslverify"><?= __( 'Allow verify ssl certificates:' ) ?></label>
							</th>
							<td>
								<input type='checkbox' name='EdemoSSO_sslverify' id='EdemoSSO_sslverify' <?= (($this->sslverify)?'checked':''); ?> />
								<?= __( "If this set, the ssl certificates will be verified during the communication with sso server. Uncheck is recommended if your site has no cert, or the issuer isn't validated." ) ?>
							</td>
						</tr>
						<tr>
							<th>
								<label for="eDemoSSO_callbackURI"><?= __( 'eDemo_SSO callback URL:' ) ?></label>
							</th>
							<td>
								<?= self::$callbackURL ?>
							</td>
						</tr>
						<tr>
							<th width="30%" valign="top" style="padding-top: 10px;">
								<label for="EdemoSSO_allowBind"><?= __( 'SSO account bindind:' ) ?></label>
							</th>
							<td>
								<input type='checkbox' name='EdemoSSO_allowBind' id='EdemoSSO_allowBind' <?= ((self::$allowBind)?'checked':''); ?> />
								<?= __( "If this set, a SSO account can be binded with the given Wordpress account. User gets a 'bind' button on his datasheet and in the SSO login widget.") ?>
							</td>
						</tr>
						<tr>
							<td colspan="2">
							<p class="submit"><input type='submit' name='edemosso_update' value='<?= __( 'Update Options' ) ?>' /></p>
							</td>
						</tr>
					</table>
				</fieldset>
			</form>
		</div>
		<?php
	}
	
	//
	// Actual functionality
	//
	
  // shortcode for 'sign it' function
 	// [SSOsignit text="Sign it if you agree with" thanks="Thank you" signed="Has been signed"]
	
  function sign_it( $atts )	{
    $a = shortcode_atts( array(
        'text'   => 'Sign it if you agree with',
        'thanks' => 'Thanks for your sign',
        'signed' => 'You signed yet, thanks',
          ), $atts );

	if ( !is_user_logged_in() ) {
		return '<a href="https://'.self::SSO_AUTH_URI.'?response_type=code&client_id='.self::$appkey.'&redirect_uri='.urlencode(self::$callbackURL.'?wp_redirect='.$_SERVER['REQUEST_URI'].'&signed=true').'"><div class="btn">'.$a['text'].'</div></a>';
    }
    elseif ( isset( $_GET['signed'] ) ) {
      if ($this->is_signed()) return '<div class="button SSO_signed">'.$a['signed'].'</div>';
      else {
        $this->do_sign_it();
        return '<div class="button SSO_signed">'.$a['thanks'].'</div>';
      }
    } 
    return '<a href="'.get_permalink().'?signed=true"><div class="btn">'.$a['text'].'</div></a>';
	}

  // saving the signing event in database
  function do_sign_it(){}
  
  // checking if is it signed yet
  function is_signed(){ 
    return true ;
  }
  
	//
	// Hooks
	//


	function add_rewrite_rules() {
		global $wp_rewrite;
		$rules = array( self::CALLBACK_URI.'(.+?)$' => 'index.php$matches[1]&'.self::CALLBACK_URI.'=true',
                    self::CALLBACK_URI.'$'      => 'index.php?'.self::CALLBACK_URI.'=true&'  );
		$wp_rewrite->rules = $rules + (array)$wp_rewrite->rules;
	}

	function plugin_activation() {

		// Adding new user role "eDemo_SSO_role" only with "read" capability 
	  
		add_role( self::USER_ROLE, 'eDemo_SSO user', array( 'read' => true, 'level_0' => true ) );

		// Adding new rewrite rules     
    
		global $wp_rewrite;
		$wp_rewrite->flush_rules(); // force call to generate_rewrite_rules()
	}
	
	function plugin_deactivation() {
	
		// Removing SSO rewrite rules  
		remove_action( 'generate_rewrite_rules', array( $this, 'rewrite_rules' ) );
		global $wp_rewrite;
		$wp_rewrite->flush_rules(); // force call to generate_rewrite_rules()
	}

  function parse_request( &$wp )
  {
    if ( array_key_exists( self::QUERY_VAR, $wp->query_vars ) ) {
         if (isset($_GET[self::WP_REDIR_VAR])) {
			$expl_uri=explode('?',$_GET[self::WP_REDIR_VAR]);
          $_SERVER['REQUEST_URI']="/".$expl_uri[0]."?SSO_code=".$_GET['code'].(isset($_GET['SSO_action'])?('&SSO_action='.$_GET['SSO_action']):'');
          $wp->parse_request();
        }
    }
    if ( array_key_exists( 'SSO_code', $_GET) ) {
        $this->auth_message=$this->callback_process();
     }
    return;
  }	
  
  //
  // displaying auth error message in the top of content
  //
  
  // we will found out what is the best way to display this (pop-up or anithing else) 
  
  function the_content_filter( $content ) {
    echo "<div class='updated '><p>".$this->auth_message."</p></div>";
    return $content;
  }

  //
  // our query var filter adds the SSO query var to the query. Used for identifying the call of the callback url.
  //

	function query_vars( $public_query_vars ) { 
		array_push( $public_query_vars, self::QUERY_VAR );
		return $public_query_vars;
	}
  
  //
  // Commumication with oauth server
  //

  // The main callback function controlls the whole authentication process
   
	function callback_process() {

		if (isset($_GET['SSO_code'])) {
			if ( $token = $this->requestToken( $_GET['SSO_code'] ) ) {
				$this->access_token=$token['access_token'];
				$this->refresh_token=$token['refresh_token'];
				if ( $user_data = $this->requestUserData( ) and isset($_GET['SSO_action']) ) {
					$ssoUser = get_users( array('meta_key' => self::USERMETA_ID, 'meta_value' => $user_data['userid']) );
					switch ($_GET['SSO_action']){ 
						case 'register':
							if (!$ssoUser) {
								if ( $user_id=$this->registerUser($user_data, $token)) {
									$ssoUser[0]=get_user_by('id',$user_id);
								}
								else $this->error_message=$user_id;
							}
						case 'login':
							if ( $ssoUser ) {
								$this->error_message=($this->signinUser($ssoUser[0]))?__('You are signed in'):__("Can't log in");
							}
							else {
								$expl_uri=explode('?',$_SERVER['REQUEST_URI']);
								$ssoAuthHref='https://'.eDemoSSO::SSO_AUTH_URI.'?response_type=code&client_id='.eDemoSSO::$appkey.'&redirect_uri='.urlencode(eDemoSSO::$callbackURL.'?'.eDemoSSO::WP_REDIR_VAR.'='.$expl_uri[0]);
								$this->error_message=__('this user not registered yet. Would you like to <a href="'.$ssoAuthHref.urlencode('&SSO_action=register').'">register</a>?');
							}
							break;
						case 'refresh':
							if ( $ssoUser = get_users( array('meta_key' => self::USERMETA_ID, 'meta_value' => $user_data['userid']) ) ) {
								$this->refreshUserMeta($user_id, Array(	'userid' => $user_data['userid'],
																		'refresh_token' => $token['refresh_token'],
																		'assurances' => $user_data['assurances'] ));
								$this->error_message=__("User's SSO data has been updated successfully");
							}
							else $this->error_message=__("User not found");
							break;
						case 'binding':
							if (is_user_logged_in()) {
								$delete_action='';
								if ( $ssoUser = get_users( array('meta_key' => self::USERMETA_ID, 'meta_value' => $user_data['userid']) ) ) {
									require_once(ABSPATH.'wp-admin/includes/user.php');
									wp_delete_user($ssoUser[0]->ID,get_current_user_id());
									$delete_action=__('Old SSO user has been erased, its data has been reassigned to the current user. ');
								}
								$this->refreshUserMeta(get_current_user_id(), Array(	'userid' => $user_data['userid'],
																						'refresh_token' => $token['refresh_token'],
																						'assurances' => $user_data['assurances'] ));
								$this->error_message=$delete_action.__("SSO account has been binded successfully");
							}							
							break;
					}
				}
			}
		}
		else $this->error_message = __('Invalid page request - missing code');
		return $this->error_message;
	}
  
  // token requesting phase
  function request_new_token($refresh_token) {
	      $response = wp_remote_post( 'https://'.self::SSO_TOKEN_URI, array(
                 'method' => 'POST',
                'timeout' => 30,
            'redirection' => 1,
	          'httpversion' => '1.0',
	             'blocking' => true,
	              'headers' => array(),
	                 'body' => array(  'grant_type' => 'refresh_token',
				                       'refresh_token' => $refresh_token),
	              'cookies' => array(),
	            'sslverify' => $this->sslverify ) );
    if ( is_wp_error( $response )  ) {
      $this->error_message = $response->get_error_message();
      return false;
    }
    else {
      $body = json_decode( $response['body'], true );
      if (!empty($body)){
        if ( isset( $body['error'] ) ) {
          $this->error_message = __("The SSO-server's response: "). $body['error'];
          return false;
        }
        else {
			error_log($body);
			return $body;
		}
      }
        $this->error_message = __("Unexpected response cames from SSO Server");
        return false;
    }
  }
 
  function requestToken( $code ) {
    $response = wp_remote_post( 'https://'.self::SSO_TOKEN_URI, array(
                 'method' => 'POST',
                'timeout' => 30,
            'redirection' => 10,
	          'httpversion' => '1.0',
	             'blocking' => true,
	              'headers' => array(),
	                 'body' => array( 'code' => $code,
				                      'grant_type' => 'authorization_code',
				                       'client_id' => self::$appkey,
			                     'client_secret' => $this->secret,
			                      'redirect_uri' => self::$callbackURL ),
	              'cookies' => array(),
	            'sslverify' => $this->sslverify ) );
    if ( is_wp_error( $response )  ) {
      $this->error_message = $response->get_error_message();
      return false;
    }
    else {
      $body = json_decode( $response['body'], true );
      if (!empty($body)){
        if ( isset( $body['errors'] ) ) {
          $this->error_message = __("The SSO-server's response: "). $body['errors'];
          return false;
        }
        else return $body;
      }
        $this->error_message = __("Unexpected response cames from SSO Server");
        return false;
    }
  }
  
  // user data requesting phase, called if we have a valid token
  
  function requestUserData( ) {
	if ($this->access_token=='') return false;
    $response = wp_remote_get( 'https://'.self::SSO_USER_URI, array(
                    'timeout' => 30,
                'redirection' => 10,
                'httpversion' => '1.0',
                   'blocking' => true,
                    'headers' => array( 'Authorization' => 'Bearer '.$this->access_token ),
                    'cookies' => array(),
                  'sslverify' => $this->sslverify ) );
    if ( is_wp_error( $response ) ) {
      $this->error_message = $response->get_error_message();
      return false;
    }
    elseif ( isset( $response['body'] ) ) {
        $body = json_decode( $response['body'], true );
        if (!empty($body)) {
			return $body;
		}
    }
	$this->error_message=__("Invalid response has been came from SSO server");
    return false;
  }
  
  //
  //  Wordpress User function
  //
  
  //  Registering the new user
  
	function registerUser($user_data, $token){

	// registering new user
        $display_name=explode('@',$user_data['email']);
        $user_id = wp_insert_user( array( 'user_login' => $user_data['userid'],
                                          'user_email' => $user_data['email'],
                                          'display_name' => $display_name[0],
										  'user_pass' => null,
                                          'role' => self::USER_ROLE ));
	//On success
        if( !is_wp_error($user_id) ) {
			$this->refreshUserMeta($user_id, Array(	'userid' => $user_data['userid'],
													'refresh_token' => $token['refresh_token'],
													'assurances' => $user_data['assurances'] ));
			return $user_id;
		}
        else {
			$this->error_message=$user_id->get_error_message(); 
			return false;
		}
	}
  
	function refreshUserMeta($user_id, $data){
		update_user_meta( $user_id, self::USERMETA_ID, $data['userid'] );
		update_user_meta( $user_id, self::USERMETA_TOKEN, $data['refresh_token'] );
		update_user_meta( $user_id, self::USERMETA_ASSURANCES, json_encode($data['assurances']) );
		return;
	}
  
  //  Logging in the user
  
	function signinUser($user) {
		wp_set_current_user( $user->ID, $user->data->user_login );
		wp_set_auth_cookie( $user->ID );
		do_action( 'wp_login', $user->data->user_login );
		return get_current_user_id()==$user->ID;
	}
   
} // end of class declaration

if (!isset($eDemoSSO)) { $eDemoSSO = new eDemoSSO(); } 

class eDemoSSO_login extends WP_Widget {

	function __construct() {
		// Instantiate the parent object
		parent::__construct( false, 'eDemoSSO login' );
	}

	function widget( $args, $instance ) {
		// Widget output
		$expl_uri=explode('?',$_SERVER['REQUEST_URI']);
		$ssoAuthHref='https://'.eDemoSSO::SSO_AUTH_URI.'?response_type=code&client_id='.eDemoSSO::$appkey.'&redirect_uri='.urlencode(eDemoSSO::$callbackURL.'?'.eDemoSSO::WP_REDIR_VAR.'='.$expl_uri[0]);
		if (is_user_logged_in()) {
			if (eDemoSSO::$allowBind and !eDemoSSO::user_has_SSO(get_current_user_id())) {
				echo '<p><a href="'.$ssoAuthHref.urlencode('&SSO_action=binding').'">Bind SSO account</a></p>';
			}
			echo '<p><a href="/wp-admin/profile.php">'.__('Show user profile').'</a></p>';
			echo '<p><a href="'.wp_logout_url( $expl_uri[0] ).'">'.__('Logout').'</a></p>';
		}
		else {
			echo '<p><a href="'.$ssoAuthHref.urlencode('&SSO_action=login').'">'.__('Login with SSO').'</a></p>';
			echo '<p><a href="'.$ssoAuthHref.urlencode('&SSO_action=register').'">'.__('Register with SSO').'</a></p>';
		}
	}

	function update( $new_instance, $old_instance ) {
		// Save widget options
	}

	function form( $instance ) {
		// Output admin widget options form
	}
}
//delete_user_meta(1, 'eDemoSSO_ID')
?>