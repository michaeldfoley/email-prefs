<?php

namespace IMCConnector;
use IMCConnector\ImcConnectorException;
use IMCConnector\ImcRestConnector;
use IMCConnector\ImcXmlConnector;

/**
 * This is a basic class for connecting to the Imc API. It is able
 * to communicate with both the legacy XML API and the newer REST API.
 * For any given request, it will attempt to use the REST API if it is
 * available (that is, the requested resource is defined in the REST API
 * and a REST session is available). If not, it will fall back on the XML
 * API.
 * 
 * If you would prefer to force a request to be handled by one specific
 * version of the API, you should use the ImcRestConnector or
 * ImcXmlConnector class to handle your request directly.
 * 
 * @author Mark French, Argyle Social
 */
class ImcConnector {
	protected static $instance = null;
	protected $restConnector   = null;
	protected $xmlConnector    = null;

	protected $baseUrl      = null;
	protected $username     = null;
	protected $password     = null;
	protected $clientId     = null;
	protected $clientSecret = null;
	protected $refreshToken = null;
	protected $accessToken  = null;

	///////////////////////////////////////////////////////////////////////////
	// MAGIC /////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////

	/**
	 * Construct a connector object. If you will be authenticating with only a
	 * single set of credentials, it is recommended that you use the singleton
	 * getInstance() method instead. Use this constructor if you require
	 * multiple connector objects for more than one set of credentials.
	 * 
	 * @param string $baseUrl The base API URL for all requests.
	 * @return ImcConnector
	 */
	public function __construct($baseUrl='http://api2.ibmmarketingcloud.com') {
		$this->restConnector = ImcRestConnector::getInstance();
		$this->xmlConnector  = ImcXmlConnector::getInstance();
		$this->setBaseUrl($baseUrl);
	}

	/**
	 * Passthrough to shunt requests to the appropriate connector object.
	 * If a matching method is available through the REST API, it will be
	 * utilitzed. If not, the XML API will be attempted instead.
	 * 
	 * @param string $method
	 * @param array  $args
	 * @return mixed
	 */
	public function __call($method, $args) {
		if (isset($this->restConnector)) {
			if ($this->restConnector->methodAvailable($method)) {
				return call_user_func_array(array($this->restConnector, $method), $args);
			}
		}
		if (isset($this->xmlConnector)) {
			return call_user_func_array(array($this->xmlConnector, $method), $args);
		}
		throw new ImcConnectorException("No authenticated connector available for call to {$method}. You must authenticate before calling API resource endpoints.");
	}

	//////////////////////////////////////////////////////////////////////////
	// STATIC ///////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////

	/**
	 * Get a singleton instance of the connector. If you will be
	 * authenticating with only a single set of credentials, fetching a
	 * singleton may be simpler for your code to manage than creating your
	 * own instance object which you are required to manage by hand. If,
	 * however, you need multiple connectors in order to connect with
	 * different sets of credentials, you should call the constructor to
	 * obtain individual ImcConnector objects.
	 * 
	 * Note that this method is implemented with "static" not "self", so
	 * if you extend the connector to add your own functionality, you can
	 * continue to use the singleton provided by this method by calling
	 * YourChildClassName::getInstance(), but you will need to provide a
	 * "protected static $instance=null;" property in your child class
	 * for this method to reference.
	 * 
	 * @return ImcConnector
	 */
	public static function getInstance($baseUrl='http://api2.ibmmarketingcloud.com') {
		if (static::$instance == null) {
			static::$instance = new static($baseUrl);
		}
		return static::$instance;
	}

	///////////////////////////////////////////////////////////////////////////
	// PUBLIC ////////////////////////////////////////////////////////////////
	/////////////////////////////////////////////////////////////////////////

	/**
	 * Perform Imc authentication. If three arguments are supplied, will
	 * be treated as a REST authentication. If only two arugments are supplied,
	 * will instead be treated as XML authentication.
	 */
	public function authenticate() {
		$method = (func_num_args()==3) ? 'authenticateRest' : 'authenticateXml';
		return call_user_func_array(array($this, $method), func_get_args());
	}

	/**
	 * Performs Imc authentication using the supplied REST credentials,
	 * or with the cached credentials if none are supplied. Any new credentials
	 * will be cached for the next request.
	 * 
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $refreshToken
	 *
	 * @throws ImcConnectorException
	 */
	public function authenticateRest($clientId=null, $clientSecret=null, $refreshToken=null) {
		$this->clientId     = empty($clientId)     ? $this->clientId     : $clientId;
		$this->clientSecret = empty($clientSecret) ? $this->clientSecret : $clientSecret;
		$this->refreshToken = empty($refreshToken) ? $this->refreshToken : $refreshToken;

		$this->restConnector = ImcRestConnector::getInstance();
		return $this->restConnector->authenticate(
			$this->clientId,
			$this->clientSecret,
			$this->refreshToken);
	}

	/**
	 * Performs Imc authentication using the supplied REST credentials,
	 * or with the cached credentials if none are supplied. Any new credentials
	 * will be cached for the next request.
	 *
	 * @param string $clientId
	 * @param string $clientSecret
	 * @param string $refreshToken
	 *
	 * @throws ImcConnectorException
	 */
	public function authenticateXml($username=null, $password=null) {
		$this->username = empty($username) ? $this->username : $username;
		$this->password = empty($password) ? $this->password : $password;

		$this->xmlConnector = ImcXmlConnector::getInstance();
		return $this->xmlConnector->authenticate(
			$this->username,
			$this->password);
	}

	/**
	 * Set the base URL for API access.
	 * 
	 * @param string $baseUrl
	 */
	public function setBaseUrl($baseUrl) {
		// The URL needs a protocol, and SSL is preferred
		if (substr($baseUrl, 0, 4) != 'http') {
			$protocol = (stripos($baseUrl, 'api.pilot')===false) ? 'https' : 'http';
			$baseUrl = "{$protocol}://{$baseUrl}";
		}
		$this->baseUrl = $baseUrl;
		$this->restConnector->setBaseUrl($baseUrl);
		$this->xmlConnector->setBaseUrl($baseUrl);
	}

	//////////////////////////////////////////////////////////////////////////
	// PROTECTED ////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////////////

}
