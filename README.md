# ZeroKit-PHP-admin-api-client
[![Build Status](https://travis-ci.org/tresorit/ZeroKit-PHP-admin-api-client.svg?branch=master)](https://travis-ci.org/tresorit/ZeroKit-PHP-admin-api-client)

Small client lib to call ZeroKit's administrative API from PHP.
This lib provides a special HTTP client which automatically signs the administrative requests for your ZeroKit tenant's admin API.

More information about ZeroKit encryption platform: [https://tresorit.com/zerokit](https://tresorit.com/zerokit)

ZeroKit management portal: [https://manage.tresorit.io](https://manage.tresorit.io)
 
## Example
```php
<?php

require_once 'ZeroKitAdminApiClient.php';

use Zerokit\ZeroKitAdminApiClient;

// Provider your zeroKit tenant's settings
$client = new ZeroKitAdminApiClient($ZKIT_SERVICE_URL, $ZKIT_ADMIN_KEY);

// Assemble call and do the request
$response = $client->doJsonCall(
            "POST",
            "/api/v4/admin/user/init-user-registration");

// Use returned data
echo "Generated user id: " . $response->UserId . 
     "Registration session: " . $response->RegSessionId .
     "Registration session verifier: " . $response->RegSessionVerifier;

?>

```
