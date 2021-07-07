<?php
/**
 * Created by PhpStorm.
 * User: Jimmy
 * Date: 21/1/2019
 * Time: 5:37 PM
 */

namespace tests\Auth;

//require '../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use App\Http\Controllers\Auth\RegisterController;

//use GuzzleHttp\RequestOptions;

class AuthTest extends TestCase
{
    protected $testClient;
    protected $activeClientID;
    protected $deletedClientID;
    protected $activeClientIDEncrypt;
    protected $deletedClientIDEncrypt;
    protected $token;
    protected $tokenEncryption;

    /** @test
     *@doesNotPerformAssertions
     */
    public function setUp()
    {
        parent::setUp(); //this is required

        $this->testClient=new Client(['base_uri' => 'http://localhost:8989']);
        $this->activeClientID=0;
        $this->deletedClientID=0;
        $this->activeClientIDEncrypt=0;
        $this->deletedClientIDEncrypt=0;
        $this->token = "";
    }

    /** @test */
    public function Test_Register()
    {
        // create our http client (Guzzle)
        $options['timeout'] = 300;

        //Test Validation WithOut Token
        $response = $this->testClient->request('POST', '/microservice', ['version'=>1.2,'json' => [
        ],'http_errors' => false
        ],$options);

        $this->assertEquals(401, $response->getStatusCode(),"Error Test Response Code when submit request without Token");
        $data = json_decode($response->getBody(true), true);
        $this->assertArrayHasKey('error', $data,"Error Test because there is no [Error Message] when submit request without Token");

        //Test Validation Tanpa ada Body ( Encrypt )
        $response = $this->testClient->request('POST', '/microservice', ['version'=>1.2,'json' => [
        ], 'query' => [
            'token' => $this->tokenEncryption],'http_errors' => false
        ],$options);

        $this->assertEquals(400, $response->getStatusCode(),"Error Test Response Code when insert MicroService without Any Data in Request Body General [Encrypt]");
        $generalController = new GeneralController();
        $data = json_decode($generalController->getDecryptString($this->tokenEncryption,$response->getBody(true)), true);
        $this->assertArrayHasKey('error', $data,"Error Test because there is no [Error Message] when submit request without body [Encrypt]");
    }


}
