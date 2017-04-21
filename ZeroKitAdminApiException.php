<?php
namespace ZeroKit;

/**
 * ZeroKit administrative API exception
 *
 * @author 		hami89 (Gergely Hamos, hami89@gmail.com)
 * @copyright	Copyright © Tresorit AG. 2017
*/
class ZeroKitAdminApiException extends \Exception
{
    // Api error code
    private $errorCode;

    // Api error message
    private $errorMessage;

    /**
     * Initializes a new instance of the ZeroKitAdminApiException class.
     *
     * @param $errorCode    string      Api error code.
     * @param $errorMessage string      Api error message.
     */
    public function __construct($errorCode, $errorMessage)
    {
        parent::__construct($errorMessage);

        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Gets the API error code
     *
     * @return string   Returns the API error code.
     */
    public function getErrorCode() {
        return $this->errorCode;
    }

    /**
     * Gets the API error message
     *
     * @return string   Returns the API error message.
     */
    public function getErrorErrorMessage() {
        return $this->errorMessage;
    }
}