<?php
/**
 * ConnectApiTest
 * PHP version 7.3
 *
 * @category Class
 * @package  Stan
 * @author Brightweb
 * @link https://stan-business.fr
 */

/**
 * Stan API
 *
 * Stan Client API
 *
 * The version of the OpenAPI document: 1.0.0
 * Generated by: https://openapi-generator.tech
 * OpenAPI Generator version: 5.4.0
 */

/**
 * NOTE: This class is auto generated by OpenAPI Generator (https://openapi-generator.tech).
 * https://openapi-generator.tech
 * Please update the test case below to test the endpoint.
 */

namespace Stan\Test\Api;

use Stan\Api\ConnectApi;
use Stan\Model\ConnectAccessToken;
use Stan\Model\ConnectAccessTokenRequestBody;

use Stan\Configuration;
use Stan\ApiException;
use Stan\ObjectSerializer;
use Stan\Api\StanClient;
use Stan\Test\Api\TestCase;

/**
 * ConnectApiTest Class Doc Comment
 *
 * @category Class
 * @package  Stan
 * @author Brightweb
 * @link https://stan-business.fr
 */
class ConnectApiTest extends TestCase
{

    /**
     * Test case for create
     *
     * Create an access token to request user's infos.
     *
     */
    public function testCreateConnectAccessToken()
    {
        $this->client
            ->method('sendRequest')
            ->willReturn('{"access_token": "abc"}');

        $connectApi = new ConnectApi($this->client);
        $accessToken = $connectApi->createConnectAccessToken(
            new ConnectAccessTokenRequestBody()
        );
        $this->assertInstanceOf(ConnectAccessToken::class, $accessToken);
        $this->assertSame("abc", $accessToken->getAccessToken());
    }
}