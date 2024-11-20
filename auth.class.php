<?php
// Authentication Classes
/* Sessions:
 * 	user_access
 * 	user_id
 * 	user_name
 * 	user_login_type
 * 	user_account
 * 	user_pass_expired
 * 	home_url
 * 	password_url
 * 	redirect_url
 */
/* Required Tables:
 * 	auth_groups
 * 	auth_group_access
 * 	auth_login_types
 * 	auth_permissions
 * 	auth_users
 * 	auth_user_access
 * 	auth_user_groups
 */

require_once(dirname(__FILE__) .'/base.class.php');

if (file_exists(dirname(__FILE__) .'/../config.inc.php')) {
	require_once(dirname(__FILE__) .'/../config.inc.php');
} else { // rely on pathing to find it for systems using shared bushido
	require_once('config.inc.php');
}


// -----------------------------------------------------------------------------
//	Password
// -----------------------------------------------------------------------------
class Password extends Base {
	// Password related functions
	
	public function sql_hash($password) {
		// Create SQL string for hashing a password
		
		$password = trim($password);
		
		return "SHA1(". $this->db->qstr($password . BUSHIDO_SALT_STRING) .")";
	}
	
	public function get_key($username) {
		// Get key for encrypted password
		
		$username = trim($username);
		
		$key = md5(strtolower($username) . BUSHIDO_SALT_STRING);
		return $key;
	}
	
	public function sql_encrypt($username, $password) {
		// Return SQL for encrypting the provided password
		
		$username = trim($username);
		$password = trim($password);
		
		$sql = "AES_ENCRYPT(". $this->db->qstr($password) .", ". 
			$this->db->qstr($this->get_key($username)) .")";
		return $sql;
	}
	
	public function sql_decrypt() {
		// Return SQL for decrypting the stored password
		// ** Will cause an SQL error if multiple password or username fields present
		
		$sql = "AES_DECRYPT(`password`, ".
			"MD5(CONCAT(LOWER(`username`), ". $this->db->qstr(BUSHIDO_SALT_STRING) .")))";
		return $sql;
	}
	
	public function generate($length=8) {
		// Generate a random password
		// $length characters long, lower case, no vowels or 'l'
		
		if (!$length or !ctype_digit($length)) {
			$length = 8;
		}
		$cut = round($length / 2);	// use roughly half letters, half numbers
		$letters = array('b','c','d','f','g','h','j','k','m','n','p','q','r','s','t','v','w','x','y','z');
		shuffle($letters);
		$letters = array_slice($letters, 0, $cut);
		$numbers = range(0, 9);
		shuffle($numbers);
		$numbers = array_slice($numbers, 0, $length - $cut);
		$password = array_merge($letters, $numbers);
		shuffle($password);
		return implode('', $password);
	}
	
	public function get_user_id($username) {
		// Return user id or false for username
		
		$username = trim($username);
		
		$sql = "SELECT id FROM auth_users ".
			"WHERE username = ". $this->db->qstr($username);
		$results = $this->db->Execute($sql);
		if ($this->db->results_check($results, true)) {
			return $results->fields['id'];
		} else {
			return false;
		}
	}
	
	public function de_dup_username($username, $increment=null) {
		// Make sure username doesnt exist and add a number if it does
		
		if ($this->get_user_id($username . $increment)) {
			if ($increment == null) {
				$increment = 0;
			}
			return $this->de_dup_username($username, $increment + 1);
		}
		
		return $username . $increment;
	}
}


// -----------------------------------------------------------------------------
//	Auth
// -----------------------------------------------------------------------------
class Auth extends Password {
	// Handle user access related session variables
	
	function __construct($hash=true, $alt_db=false) {
		// Check for session user_access, create if it doesnt exist
		
		parent::__construct($alt_db);
		if (!isset($_SESSION['user_access']) or !is_array($_SESSION['user_access'])) {
			$this->default_access();
		}
		// Type of password encryption used, sha1 hashing or aes encryption
		$this->hash = $hash;
	}
	
	public function get_available_groups() {
		// Return a list of group ids / descriptions
		
		$sql = "SELECT `desc`, id FROM auth_groups";
		$results = $this->db->GetAssoc($sql);
		if ($this->db->results_check($results, true)) {
			return $results;
		} else {
			return false;
		}
	}
	
	public function get_available_permissions() {
		// Return a list of permission ids / descriptions
		
		$sql = "SELECT `desc`, id FROM auth_permissions";
		$results = $this->db->GetAssoc($sql);
		if ($this->db->results_check($results, true)) {
			return $results;
		} else {
			return false;
		}
	}
	
	public function default_access() {
		// Define guest level access
		
		$_SESSION['user_access'] = array("guest");
	}
	
	public function logout() {
		// redirect_url must survive!
		if (isset($_SESSION['redirect_url']) and $_SESSION['redirect_url']) {
			$redirect_url = $_SESSION['redirect_url'];
		}
		
		// Set session to blank array and reset id
		$_SESSION = array();
		session_regenerate_id(true);
		$this->default_access();
		
		// Revive redirect_url
		if (isset($redirect_url)) {
			$_SESSION['redirect_url'] = $redirect_url;
		}
	}
	
	public function get_user_groups($user_id) {
		// Return array of group ids assigned to user id
		
		$sql = "SELECT group_id FROM auth_user_groups ".
			"WHERE user_id = ". $this->db->qstr($user_id);
		$results = $this->db->GetCol($sql);
		if ($this->db->results_check($results, true)) {
			return $results;
		} else {
			return array();
		}
	}
	
	public function get_user_permissions($user_id) {
		// Return array of permission ids assigned directly to user id
		// (not through groups)
		
		$sql = "SELECT permission_id FROM auth_user_access ".
			"WHERE user_id = ". $this->db->qstr($user_id);
		$results = $this->db->GetCol($sql);
		if ($this->db->results_check($results, true)) {
			return $results;
		} else {
			return array();
		}
	}
	
	public function get_user_access($user_id) {		
		// Return the named permissions array for the user_id (including groups)
		// Used for setting sessions
		
		$sql = "SELECT DISTINCT permiss.name FROM auth_group_access AS access ".
			"LEFT JOIN auth_permissions AS permiss ".
			"ON permiss.id = access.permission_id ".
			"WHERE access.group_id IN ( ".
				"SELECT group_id FROM auth_user_groups WHERE user_id = ". $this->db->qstr($user_id) .
			" ) ".
			"UNION ".
			"SELECT DISTINCT permiss.name FROM auth_user_access AS access ".
			"LEFT JOIN auth_permissions AS permiss ".
			"ON permiss.id = access.permission_id ".
			"WHERE access.user_id = ". $this->db->qstr($user_id);
		$results = $this->db->GetCol($sql);
		if ($this->db->results_check($results, true)) {
			return $results;
		} else {
			return array();
		}
	}
	
	public function session_setup($user_id) {
		// Define user_access session based on groups
		
		$this->default_access();	// reset access
		
		$access = $this->get_user_access($user_id);
		if ($access) {
			$_SESSION['user_access'] = array_merge($_SESSION['user_access'], $access);
			return true;
		} else {
			return false;
		}		
	}
	
	public function check_access($user_access, $permissions) {
		// Return true if permissions are found in user_access
		/* $user_access must be an array
		 * $permissions may be a string, array, or multi-dimensional array
		 * if permissions is a string: there is one required permission
		 * if permissions is an array:
		 * 	each element represents an "or" condition
		 * if permissions is a multi dimensional array:
		 * 	each sub array represents an "or" condition, 
		 * 	with elements in the sub-arrays as "and" conditions
		 * 
		 * Examples:
		 * -- foo is required
		 * 	$permissions = "foo"
		 * -- foo OR bar are required
		 * 	$permissions = array("foo", "bar")
		 * -- (foo AND bar) OR admin is required
		 * 	$permissions = array(array("foo", "bar"), "admin")
		 */
		
		if (!$permissions) {
			return false;
		}
		
		// Fit everything into a multi-dimensional array
		if (!is_array($permissions)) {
			$permissions = array($permissions);
		}
		foreach ($permissions as &$element) {
			if (!is_array($element)) {
				$element = array($element);
			}
		}
		
		// Check for required permissions
		foreach ($permissions as $or_array) {
			$good = true;
			foreach ($or_array as $permission) {
				if (array_search($permission, $user_access) === false) {
					$good = false;
				}
			}
			if ($good) {
				return true;
			}
		}
		
		return false;		
	}
	
	public function user_access($permissions) {
		// Check access for logged in user
		
		return $this->check_access($_SESSION['user_access'], $permissions);		
	}
	
	public function page_access($permissions) {
		// Return to home/login page if access requirements are not met
		
		if ($this->user_access($permissions) !== true) {
			if (isset($_SESSION['home_url']) and $_SESSION['home_url'] != $_SERVER['PHP_SELF']) {
				// Logged in but going somewhere they shouldn't
				$_SESSION['redirect_url'] = "";
				header('Location: '. $_SESSION['home_url']);
				exit;
			} else {
				// Not logged in yet
				if (isset($_POST) and count($_POST)) {
					// Don't redirect if there are post variables
					// User is likely in the middle of a form and should start over
					$_SESSION['redirect_url'] = "";
				} else {
					$_SESSION['redirect_url'] = $_SERVER['PHP_SELF'];
					if ($_SERVER['QUERY_STRING']) {
						$_SESSION['redirect_url'] .= "?". $_SERVER['QUERY_STRING'];
					}
				}
				header('Location: '. BUSHIDO_LOGIN_URL);
				exit;
			}
		}
	}
		
	public function add_user($username, $account, $password, $login_type, $group=false) {
		// Add a new account, add to group (optional)
		
		$username = trim($username);
		$password = trim($password);
		
		$sql = "INSERT INTO auth_users SET ".
			"username = ". $this->db->qstr($username) .", ".
			"account = ". $this->db->qstr($account) .", ".
			"`password` = ". 
				($this->hash ? $this->sql_hash($password) : $this->sql_encrypt($username, $password)) .
				", ".
			"pass_date = NOW(), ".
			"login_type = ". $this->db->qstr($login_type) .", ".
			"active = 1, ".
			"create_date = NOW()";
		$results = $this->db->Execute($sql);
		if ($this->db->results_check($results) == false) {
			return false;
		}
		$user_id = $this->db->Insert_ID();
		
		if ($group) {
			if (!$this->add_user_group($user_id, $group)) {
				return false;
			}
		}
		
		return $user_id;
	}
	
	public function change_login_type($user_id, $login_type) {
		// Update DB with new login_type
		
		$sql = "UPDATE auth_users SET ".
			"login_type = ". $this->db->qstr($login_type) ." ".
			"WHERE id = ". $this->db->qstr($user_id);
		$results = $this->db->Execute($sql);
		
		return $this->db->results_check($results);
	}
	
	public function add_user_group($user_id, $group_id) {
		// Add a group to user
		
		$sql = "INSERT INTO auth_user_groups SET ".
			"user_id = ". $this->db->qstr($user_id) .", ".
			"group_id = ". $this->db->qstr($group_id);
		$results = $this->db->Execute($sql);
		
		return $this->db->results_check($results);
	}
	
	public function add_user_permission($user_id, $permission_id) {
		// Add a permission directly to user
		
		$sql = "INSERT INTO auth_user_access SET ".
			"user_id = ". $this->db->qstr($user_id) .", ".
			"permission_id = ". $this->db->qstr($permission_id);
		$results = $this->db->Execute($sql);
		
		return $this->db->results_check($results);
	}
	
	public function rem_user_group($user_id, $group_id) {
		// Remove a group from user
		
		$sql = "DELETE FROM auth_user_groups ".
			"WHERE user_id = ". $this->db->qstr($user_id) ." ".
			"AND group_id = ". $this->db->qstr($group_id);
		$results = $this->db->Execute($sql);
		
		return $this->db->results_check($results);
	}
	
	public function rem_user_permission($user_id, $permission_id) {
		// Remove a permission from user
		
		$sql = "DELETE FROM auth_user_access ".
			"WHERE user_id = ". $this->db->qstr($user_id) ." ".
			"AND permission_id = ". $this->db->qstr($permission_id);
		$results = $this->db->Execute($sql);
		
		return $this->db->results_check($results);
	}
	
	public function remove_user($user_id) {
		// Remove a user account from the system
		
		if ($user_id and ctype_digit($user_id)) {
			// Remove the groups
			$sql = "DELETE FROM auth_user_groups ".
				"WHERE user_id = ". $this->db->qstr($user_id);
			$results = $this->db->Execute($sql);
			if ($this->db->results_check($results) == false) {
				return false;
			}
			// Remove the main record
			$sql = "DELETE FROM auth_users ".
				"WHERE id = ". $this->db->qstr($user_id);
			$results = $this->db->Execute($sql);
			if ($this->db->results_check($results) == false) {
				return false;
			}
			return true;
		}
		return false;
	}
	
	public function change_password($user_id, $username, $new_pass, $pass_date=null) {
		// Change password for $user_id
		// ** This function does not validate the old password
		
		$username = trim($username);
		$new_pass = trim($new_pass);
		if ($pass_date != null) {
			$pass_date = $this->db->qstr($pass_date);
		} else {
			$pass_date = "NOW()";
		}
		
		$sql = "UPDATE auth_users SET ".
			"`password` = ". 
				($this->hash ? $this->sql_hash($new_pass) : $this->sql_encrypt($username, $new_pass)) .
				", ".
			"pass_date = ". $pass_date ." ".
			"WHERE id = ". $this->db->qstr($user_id) ." ".
			"AND username = ". $this->db->qstr($username);
		$results = $this->db->Execute($sql);
		return $this->db->results_check($results);
	}
}


// -----------------------------------------------------------------------------
//	Login
// -----------------------------------------------------------------------------
class Login extends Auth {
	// Validate credentials for user login
	
	protected $username;
	protected $password;
	
	function __construct($username=null, $password=null, $hash=true, $alt_db=false) {
		parent::__construct($hash, $alt_db);
		$this->username = trim($username);
		$this->password = trim($password);
	}
	
	public function check_login() {
		// Validate that password is correct for given username
		
		$sql = "SELECT users.id, users.username, users.account, users.login_type, ".
			"UNIX_TIMESTAMP(users.pass_date) AS pass_date, ".
			"log.pass_expiration, log.url, log.pass_url ".
			"FROM auth_users AS users ".
			"LEFT JOIN auth_login_types AS log ".
			"ON log.id = users.login_type ".
			"WHERE users.username = ". $this->db->qstr($this->username) ." ".
			"AND users.active = 1 ".
			"AND users.`password` = ". 
				($this->hash ? $this->sql_hash($this->password) : $this->sql_encrypt($this->username, $this->password));
		$results = $this->db->Execute($sql);
		if (!$this->db->results_check($results)) {
			$this->error('A database error occured');
			return false;
		}
		if (!$results->EOF and strtolower($results->fields['username']) === strtolower($this->username)) {
			return $results;
		} else {
			// Error handling
			$this->error('Incorrect User ID / Password');
			trigger_error('Bad username/password submitted for '. $this->username, E_USER_NOTICE);
			return false;
		}
	}

	public function full_login() {
		// Validate username and password
		// Set session variables
		// Return header location
		// ** Does not clear all session data
		
		// Check login
		$results = $this->check_login();
		if ($results == false) {
			return false;
		}
		
		// Reset session ID
		session_regenerate_id(true);

		// Log user in
		$_SESSION['user_id'] = $results->fields['id'];
		$_SESSION['user_name'] = $results->fields['username'];
		$_SESSION['user_login_type'] = $results->fields['login_type'];
		$_SESSION['user_pass_expired'] = false;
		$_SESSION['user_account'] = $results->fields['account'];
		
		// Setup group permissions
		if (!$this->session_setup($_SESSION['user_id'])) {
			// At least the 'common' permission should be assigned
			$this->error('Unable to log in to account');
			return false;
		}
		
		// Password expiration
		if ($results->fields['pass_expiration'] > 0) {
			if ($results->fields['pass_date'] < time() - $results->fields['pass_expiration']) {
				$_SESSION['user_pass_expired'] = true;
			}
		}
		
		// Site options
		if ($results->fields['pass_url'] != "") {
			$_SESSION['password_url'] = $results->fields['pass_url'];
		}
		
		// Get default URL
		if ($results->fields['url'] != null) {
			$url = $results->fields['url'];
			$_SESSION['home_url'] = $url;
		} else {
			$url = false;
		}
		
		// URL modifiers
		if ($url != false) {
			// Redirect to URL
			if (isset($_SESSION['redirect_url']) and $_SESSION['redirect_url']) {
				$url = $_SESSION['redirect_url'];
				$_SESSION['redirect_url'] = "";
			}
		}
		
		// Update 'last_login' date
		$sql = "UPDATE auth_users SET last_login = ". $this->db->DBTimeStamp(time()) . 
			"WHERE id = ". $this->db->qstr($_SESSION['user_id']);
		$this->db->Execute($sql);
		return $url;
	}
}

?>
