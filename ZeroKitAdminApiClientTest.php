<?php
namespace ZeroKit;

include "ZeroKitAdminApiClient.php";

use PHPUnit\Framework\TestCase;

class Test extends TestCase
{
    private $serviceUrl;
    private $adminKey;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->serviceUrl = getenv("ZKIT_SERVICE_URL");
        $this->adminKey = getenv("ZKIT_ADMIN_KEY");

        if ($this->serviceUrl === false || $this->adminKey === false)
            throw new \Exception("Test init failed. ZKIT_SERVICE_URL and/or ZKIT_ADMIN_KEY environment variables ar not set!");
    }

    public function testCanBeCreatedWithValidCredentials(){
        $this->assertInstanceOf(
            ZeroKitAdminApiClient::class,
            new ZeroKitAdminApiClient($this->serviceUrl, $this->adminKey)
        );
    }

    public function testCanBeCreatedWithValidCredentialsAndTenantId(){
        $this->assertInstanceOf(
            ZeroKitAdminApiClient::class,
            new ZeroKitAdminApiClient($this->serviceUrl, $this->adminKey, "testtenant")
        );
    }

    public function testCanNotBeCreatedWithoutParams(){
        $this->expectException("ArgumentCountError");

        new ZeroKitAdminApiClient();
    }

    public function testCanNotBeCreatedWithoutAdminkey(){
        $this->expectException("ArgumentCountError");

        new ZeroKitAdminApiClient($this->serviceUrl);
    }

    public function testCanNotBeCreatedWithNullUrl()
    {
        $this->expectException("InvalidArgumentException");

        new ZeroKitAdminApiClient(null, $this->adminKey);
    }

    public function testCanNotBeCreatedWithBadUrl(){
        $this->expectException("InvalidArgumentException");

        new ZeroKitAdminApiClient("badurl://bad.bad", $this->adminKey);
    }

    public function testCanNotBeCreatedWithNullAdminKey()
    {
        $this->expectException("InvalidArgumentException");

        new ZeroKitAdminApiClient($this->serviceUrl, null);
    }

    public function testCanNotBeCreatedWithShortAdminKey()
    {
        $this->expectException("InvalidArgumentException");

        new ZeroKitAdminApiClient($this->serviceUrl, substr($this->adminKey, 2));
    }

    public function testCanNotBeCreatedWithNonHexAdminKey()
    {
        $this->expectException("InvalidArgumentException");

        new ZeroKitAdminApiClient($this->serviceUrl, "no".substr($this->adminKey, 2));
    }

    public function testCanNotBeCreatedWithBadTenantId()
    {
        $this->expectException("InvalidArgumentException");

        new ZeroKitAdminApiClient($this->serviceUrl, $this->adminKey, "00testtest");
    }

    public function testCanNotBeCreatedWithShortTenantId()
    {
        $this->expectException("InvalidArgumentException");

        new ZeroKitAdminApiClient($this->serviceUrl, $this->adminKey, "nope");
    }

    public function testCanCallJsonApiWithoutPayloadReturnsObject(){
        $client = new ZeroKitAdminApiClient(
            $this->serviceUrl,
            $this->adminKey);

        $response = $client->doJsonCall(
            "POST",
            "/api/v4/admin/user/init-user-registration");

        $this->assertNotNull($response);
        $this->assertInstanceOf("stdClass", $response);
        $this->assertObjectHasAttribute("UserId", $response);
        $this->assertObjectHasAttribute("RegSessionId", $response);
        $this->assertObjectHasAttribute("RegSessionVerifier", $response);
        $this->assertNotNull($response->UserId);
        $this->assertNotNull($response->RegSessionId);
        $this->assertNotNull($response->RegSessionVerifier);
    }

    public function testCanCallJsonApiWithoutPayloadReturnsAssocArray(){
        $client = new ZeroKitAdminApiClient(
            $this->serviceUrl,
            $this->adminKey);

        $response = $client->doJsonCall(
            "POST",
            "/api/v4/admin/user/init-user-registration",
            null,
            true);

        $this->assertNotNull($response);
        $this->assertInternalType("array", $response);
        $this->arrayHasKey("UserId", $response);
        $this->arrayHasKey("RegSessionId", $response);
        $this->arrayHasKey("RegSessionVerifier", $response);
        $this->assertNotNull($response["UserId"]);
        $this->assertNotNull($response["RegSessionId"]);
        $this->assertNotNull($response["RegSessionVerifier"]);
    }

    public function testCanCallHttpApiWithPayload(){
        $client = new ZeroKitAdminApiClient(
            $this->serviceUrl,
            $this->adminKey);

        $response = $client->doHttpCall(
            "PUT",
            "/api/v4/admin/tenant/upload-custom-content?fileName=css/login.css",
            "body { background-color: red; }");

        $this->assertNotNull($response);

        $response = json_decode($response);

        $this->assertNotNull($response);

        $this->assertObjectHasAttribute("Name", $response);
        $this->assertObjectHasAttribute("Path", $response);
        $this->assertObjectHasAttribute("Url", $response);
        $this->assertObjectHasAttribute("Size", $response);
        $this->assertObjectHasAttribute("ContentType", $response);
        $this->assertObjectHasAttribute("Etag", $response);
        $this->assertNotNull($response->Name);
        $this->assertNotNull($response->Path);
        $this->assertNotNull($response->Url);
        $this->assertNotNull($response->Size);
        $this->assertNotNull($response->ContentType);
        $this->assertNotNull($response->Etag);
        $this->assertEquals("login.css", $response->Name);
        $this->assertEquals("css/login.css", $response->Path);
        $this->assertEquals("text/css", $response->ContentType);
    }

    public function testCanCallJsonApiWithPayloadApiError()
    {
        try{
            $client = new ZeroKitAdminApiClient(
                $this->serviceUrl,
                $this->adminKey);

            $response = $client->doJsonCall(
                "POST",
                "/api/v4/admin/user/init-user-registration");

            $response = $client->doJsonCall(
                "POST",
                "/api/v4/admin/user/set-user-state",
                array("UserId" => $response->UserId, "Enable" => true));

            $this->fail();
        }
        catch (ZeroKitAdminApiException $exception) {
            $this->assertEquals("UserNotExists", $exception->getErrorCode());
            $this->assertNotNull($exception->getErrorErrorMessage());
        }
    }
}
