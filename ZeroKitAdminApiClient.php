<?php
namespace ZeroKit;

require_once "ZeroKitAdminApiException.php";

/**
 * ZeroKit administrative API client for PHP 5.x - 7.x
 * This client is capable of doing the low-level call canonicalization
 * and signature creation and also capable of making the calls themselves.
 *
 * @author 		hami89 (Gergely Hamos, hami89@gmail.com)
 * @copyright	Copyright © Tresorit AG. 2017
 */
class ZeroKitAdminApiClient {
    // Administrative access key (64 char hex digits, represents 32 bytes)
    private $adminKey;

    // Service url of the tenant, without trailing slash
    private $serviceUrl;

    // ID of the tenant (10 alphanumeric digits, strictly starting with a letter)
    private $tenantId;

    // ID of the administrative user (format: admin@{tenantId}.api.tresorit.io)
    private $adminUserId;

    // Valid HTTP methods for the client, only used for validation.
    private $methods = array("GET", "HEAD", "POST", "PUT", "DELETE", "OPTIONS");

    /**
     * Constructs a new ZeroKit administrative API client
     *
     * @param    string $serviceUrl The service URL copied from the management portal
     * @param    string $adminKey One of the hexadecimal, 64 char long admin keys from the management portal
     * @param    string $tenantId [OPTIONAL] If your tenant is hosted on-demand or on a special url, then and only then you should support the tenant ID,
     *
     * @throws \InvalidArgumentException Throws exception when any of the given values or their combination is invalid.
     */
    function __construct($serviceUrl, $adminKey, $tenantId = null) {
        // Parse and check URL
        $parsedurl = parse_url($serviceUrl);

        if ($parsedurl === false)
            throw new \InvalidArgumentException("Given parameter serviceUrl is invalid!");

        $this->serviceUrl = rtrim($serviceUrl, "/");

        // Check admin key
        if ($adminKey === null || !is_string($adminKey) || strlen($adminKey)!=64 || !ctype_xdigit($adminKey))
            throw new \InvalidArgumentException("Given parameter adminKey is invalid!");

        $this->adminKey = $adminKey;

        if ($tenantId !== null && (!is_string($tenantId) || preg_match("/\A[a-z][a-z0-9]{7,9}\z/",$tenantId) != 1))
            throw new \InvalidArgumentException("Given parameter tenantId is invalid!");

        // Check if tenantId is supplied to the client
        if ($tenantId !== null){
            $this->tenantId = $tenantId;
			$this->adminUserId = "admin@".$this->tenantId.".tresorit.io";
	   }
        // Try to obtain tenant ID otherwise
        else{
            // Try match for production url format
            // This format is used for all tenants hosted by Tresorit
            // Example: https://{tenantId}.api.tresorit.io)
            $matches = array();
            if (preg_match("/\Ahttps?:\/\/(?<tenantid>[a-z][a-z0-9]{7,9})\.[^\/,^\?,^#]*\/?\z/", $serviceUrl , $matches) == 1) {
                $this->tenantId = $matches[1];
                $this->adminUserId = "admin@".$this->tenantId.".tresorit.io";
                return;
            }

            // Try match hosted url format for tenant id
            // This format is used for testing, not used for production tenants
            // Example: https://host-{hostId}.api.tresorit.io/tenant-{tenantId})
            $matches = array();
            if (preg_match("/\Ahttps?:\/\/[^\/,^\?,^#]*\/tenant-(?<tenantid>[a-z][a-z0-9]{7,9})\/?\z/", $serviceUrl, $matches) == 1) {
                $this->tenantId = $matches[1];
                $this->adminUserId = "admin@".$this->tenantId.".tresorit.io";
                return;
            }

            // No admin key supplied nor captured
            throw new \InvalidArgumentException("No tenantId is supplied nor can be captured from the given service URL!");
        }
    }

    /**
     * Completely performs a signed HTTP call including the
     * parameter checks, signing and network communication.
     *
     * @param	string	$method					Http methode to use (GET, HEAD, POST, PUT, DELETE, OPTIONS)
     * @param	string	$endpointPathWithQuery	Endpoint path with the query (example: /api/v4/admin/user/init-user-registration)
     * @param	string	$payload				[OPTIONAL] Raw payload of the call. (Binary data is also allowed in PHP strings).
     * @param	string	$contentType			[OPTIONAL] Content type of the payload.
     *
     * @throws \InvalidArgumentException    Throws exception when any of the given values or their combination is invalid.
     * @throws \Exception                   Throws exception if the call fails do to technical / network issues.
     * @throws ZeroKitAdminApiException     Throws exception when the response is an API error.
     *
     * @return	string	Returns the raw response body. If you need the status code, you can use $http_response_header PHP variable.
     */
    function doHttpCall($method, $endpointPathWithQuery, $payload = null, $contentType = "application/json"){
        // Check method
        if ($method === null || !is_string($method) || !in_array($method, $this->methods))
            throw new \InvalidArgumentException("Given parameter method is invalid!");

        // Assemble url
        $url = $this->serviceUrl."/".ltrim($endpointPathWithQuery, "/");
        $parsedurl = parse_url($url);

        if ($parsedurl === false || $parsedurl["path"] === null)
            throw new \InvalidArgumentException("Given parameter endpointPathWithQuery is invalid!");

        // Check content type
        if ($payload !== null && $contentType === null)
            throw new \InvalidArgumentException("Given parameter contentType is invalid!");

        // Compute content-type hash
        $contentHash = "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855";
        $contentLength = 0;
        if ($payload !== null){
            $contentHash = hash('sha256', $payload);
            $contentLength = strlen($payload);
        }

        // Assemble headers
        $headers = array(
            "UserId" => $this->adminUserId,
            "TresoritDate" => gmdate("Y-m-d\TH:i:s\Z"),
            "Content-Type" => $contentType,
            "Content-SHA256" => $contentHash,
            "HMACHeaders" => "UserId,TresoritDate,Content-Type,Content-SHA256,HMACHeaders");

        // Canonicalize request
        $stringToSign = $this->canonicalizeCall($method, $url, $headers);

        // Sign request
        $signature = $this->signString($stringToSign);

        // Assemble signature header
        $headers["Authorization"] = "AdminKey $signature";

        // Add content length
        $headers["Content-length"] = $contentLength;

        // Prepare http context options
        $httpOptions = array(
            'header'  => array_map(function($key, $value) { return "$key:$value"; }, array_keys($headers), $headers),
            'method'  => $method,
            'ignore_errors' => true
        );

        if ($payload !== null)
            $httpOptions['content'] = $payload;

		// Preapre sream context
        $options = array(
            'http' => $httpOptions,
            "ssl" => array(
                "verify_peer" => true,
                "verify_peer_name" => true,
            )
        );

        // Do the call
        $context  = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        // Check success (network)
		if ($result === null || $result === false)
            throw new \Exception("Failed to do the call");

		// Check response status code
        $statusCode = ZeroKitAdminApiClient::getLastStatusCode($http_response_header);
        if ($statusCode === NULL || $statusCode < 200 || $statusCode > 299){
            // Try parse error
            $json = json_decode($result, true);

            // Convert error
            if ($json != NULL && is_array($json) && array_key_exists("ErrorCode", $json) && array_key_exists("ErrorMessage", $json))
                throw new  ZeroKitAdminApiException($json["ErrorCode"], $json["ErrorMessage"]);

            // Throw error on failure
            throw new \Exception("Http call failed but no valid api error has been received.");
        }

		return $result;
	}

    /**
     * Completely performs a signed HTTP call with JSON conversion,
     * including the, parameter checks, signing and network communication.
     *
     * @param	string	$method					Http methode to use (GET, HEAD, POST, PUT, DELETE, OPTIONS)
     * @param	string	$endpointPathWithQuery	Endpoint path with the query (example: /api/v4/admin/user/init-user-registration)
     * @param	object	$payload				[OPTIONAL] Object or associative array hierarcy (Automatically jsonyfied).
     * @param	bool	$assoc					[OPTIONAl] When TRUE, returned objects will be converted into associative arrays.
     *
     * @throws \InvalidArgumentException    Throws exception when any of the given values or their combination is invalid.
     * @throws \Exception                   Throws exception if the call fails do to technical / network issues.
     * @throws ZeroKitAdminApiException     Throws exception when the response is an API error.
     *
     * @return	string	Returns the parsed JSON response body. If you need the status code, you can use $http_response_header PHP variable.
     */
    function doJsonCall($method, $endpointPathWithQuery, $payload = null, $assoc = false){
        if ($payload !== null)
            $payload = json_encode($payload);

        if ($payload === false)
            throw new \InvalidArgumentException("Given parameter payload is invalid!");

        $result = $this->doHttpCall($method, $endpointPathWithQuery, $payload, "application/json");

        if ($result === null || strlen($result) == 0)
            return null;

        return json_decode($result, $assoc);
    }

    /**
     * Comutes the canonicalized format of the request which can be
     * used for signing.
     *
     * @param	string	$method		Http methode to use (GET, HEAD, POST, PUT, DELETE, OPTIONS)
     * @param	string	$url		URL of the called endpoint
     * @param	array	$headers	Associative array of the header which should be included into the signature.
     *
     * @throws \InvalidArgumentException    Throws exception when any of the given values ot their combination is invalid.
     *
     * @return	string	Returns the computed canonical string-to-sign value.
     */
    function canonicalizeCall($method, $url, $headers){
        // Check method
        if ($method === null || !is_string($method) || !in_array($method, $this->methods))
            throw new \InvalidArgumentException("Given parameter method is invalid!");

        // Parse and check URL and query
        $parsedurl = parse_url($url);

        if ($parsedurl === false || $parsedurl["path"] === null)
            throw new \InvalidArgumentException("Given parameter url is invalid!");

        $path = ltrim($parsedurl["path"], "/");

        if (array_key_exists("query", $parsedurl) && $parsedurl["query"] !== null && strlen($parsedurl["query"]) > 0)
            $path .= "?".$parsedurl["query"];

        // Check and transform headers
        if ($headers === null || !is_array($headers) || !ZeroKitAdminApiClient::hasStringKeys($headers))
            throw new \InvalidArgumentException("Given parameter headers is invalid!");

        $headers = array_map(function($key, $value) { return "$key:$value"; }, array_keys($headers), $headers);

        $stringToSign = "$method" 	. "\n" .
            "$path" 	. "\n" .
            implode("\n", $headers);

        return $stringToSign;
    }

    /**
     * Signs the given canonical string value with the key and user
     * of thsi client class.
     *
     * @param	string	$stringToSign	The canonical string that should be signed
     *
     * @throws \InvalidArgumentException   Throws exception when the given parameter is invalid.
     *
     * @return	string	Returns the signature as a base64 encoded string.
     */
    function signString($stringToSign){
        if ($stringToSign === null || !is_string($stringToSign))
            throw new \InvalidArgumentException("Given parameter is invalid for signing!");

		$ret = base64_encode(hash_hmac('sha256', $stringToSign, hex2bin($this->adminKey), true));

		return $ret;
	}

    /**
     * Internal helper function to check whether the given array is an
     * associative array.
     *
     * @param	array	$array	Array to check.
     *
     * @return	bool	Returns whether teh given array is associative
     */
    private static function hasStringKeys(array $array) {
        return count(array_filter(array_keys($array), 'is_string')) == count($array);
    }

    /**
     * Returns the http status code of the last http(s) call
     *
     * @param $responseHeaders  array  The http response headers.
     * @return mixed|null Returns the http status code of the last http(s) call or null on failure
     */
    private static function getLastStatusCode($responseHeaders){
        // Check $http_response_header
        if ($responseHeaders === NULL || !count($responseHeaders) >= 1)
            return null;

        // Try get status code
        $matches = array();
        preg_match('#HTTP/\d+\.\d+ (\d+)#', $responseHeaders[0], $matches);

        if (count($matches) === 2)
            return $matches[1];

        return null;
    }
}
?>