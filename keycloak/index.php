<?php
define('IN_SCRIPT',1);
define('HESK_PATH','../');

/* Get all the required files and functions */
require(HESK_PATH . 'hesk_settings.inc.php');
require(HESK_PATH . 'inc/common.inc.php');
require(HESK_PATH . 'keycloak/data.php');

hesk_session_start();

class KeycloakAuth {
    // Authorization data and keys
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $authorization_endpoint;
    private $token_endpoint;
    private $userinfo_endpoint;

    public function __construct($client_id, $client_secret, $redirect_uri, $authorization_endpoint, $token_endpoint, $userinfo_endpoint) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->redirect_uri = $redirect_uri;
        $this->authorization_endpoint = $authorization_endpoint;
        $this->token_endpoint = $token_endpoint;
        $this->userinfo_endpoint = $userinfo_endpoint;
    }

    public function redirectToLogin($kc_idp_hint) {
		$_SESSION['state_auth'] = bin2hex(random_bytes(16));
	
        // Redirect to Keycloak with parameters
        $params = [
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'response_type' => 'code',
            'scope' => 'openid',
            'kc_idp_hint' => $kc_idp_hint,
            'state' => $_SESSION['state_auth']
        ];
		
        header('Location: ' . $this->authorization_endpoint . '?' . http_build_query($params));
        exit();
    }

    public function getToken($code) {
        // Get token from provider via Keycloak
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->token_endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'code' => $code,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'grant_type' => 'authorization_code',
        ]));

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return isset($response['error']) ? null : $response;
    }

    public function getUserInfo($access_token) {
        // Get user information
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
        curl_setopt($ch, CURLOPT_URL, $this->userinfo_endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return isset($response['error']) ? null : $response;
    }
}

class UserSessionManager {   
	public function isLoggedInAuth() {
        return isset($_SESSION['logged_auth']) && $_SESSION['logged_auth'] === true;
    }

    public function loginAuth($user_info, $access_token, $expires_in, $refresh_token, $id_token) {
        // Save user information in the session
        $_SESSION['logged_auth'] = true;
        $_SESSION['email_auth'] = $user_info['email'];
        $_SESSION['access_token_auth'] = $access_token;
        $_SESSION['access_token_expires_in_auth'] = time() + $expires_in;
        $_SESSION['refresh_token_auth'] = $refresh_token;
        $_SESSION['id_token_auth'] = $id_token;
    }
}

// Instances
$keycloak = new KeycloakAuth($client_id, $client_secret, $redirect_uri, $authorization_endpoint, $token_endpoint, $userinfo_endpoint);
$userSession = new UserSessionManager();

// Check if user logged
if ($userSession->isLoggedInAuth()) {
	header("Location: ../{$hesk_settings['admin_dir']}/index.php");
    exit();
}

// Check if code exist
if (!isset($_GET['code'])) {
	$keycloak->redirectToLogin($_GET['kc_idp_hint']);
    exit();
} else {
	// Verify that the state parameter matches the one stored in the session (CSRF protection)
    if (!isset($_GET['state']) || $_GET['state'] !== $_SESSION['state_auth']) {
        error_log('Invalid state.');
        exit('Invalid state');
    }
	
	// If we return from Keycloak with code, we retrieve the tokens
	$token_response = $keycloak->getToken($_GET['code']);
	if ($token_response) 
	{
		// The token was successfully received, now we retrieve the user's data
		$access_token = $token_response['access_token'];
		$user_info = $keycloak->getUserInfo($access_token);

		if ($user_info) {
			// User login, saving data in the session
			$userSession->loginAuth($user_info, $access_token, $token_response['expires_in'], $token_response['refresh_token'], $token_response['id_token']);
			
			// Redirect to the home page after logging in
			header("Location: ../{$hesk_settings['admin_dir']}/index.php");
			exit();
		} else {
			error_log('Failed to retrieve user information.'); 
		}
	} else {
		error_log('Failed to retrieve access token.'); 
	}
}