<?php
namespace GuzzleHttp\Tests\Ring\Client;

use GuzzleHttp\Ring\Client\CurlHandler;
use PHPUnit\Framework\TestCase;

class CurlHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('curl_reset')) {
            $this->markTestSkipped('curl_reset() is not available');
        }
    }

    protected function getHandler($factory = null, $options = [])
    {
        return new CurlHandler($options);
    }

    public function testCreatesCurlErrors()
    {
        $handler = new CurlHandler();
        $response = $handler([
            'http_method' => 'GET',
            'uri' => '/',
            'headers' => ['host' => ['localhost:123']],
            'client' => ['timeout' => 0.001, 'connect_timeout' => 0.001],
        ]);
        $this->assertNull($response['status']);
        $this->assertNull($response['reason']);
        $this->assertEquals([], $response['headers']);
        $this->assertInstanceOf(
            'GuzzleHttp\Ring\Exception\RingException',
            $response['error']
        );

        $this->assertEquals(
            1,
            preg_match('/^cURL error \d+: .*$/', $response['error']->getMessage())
        );
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testReleasesAdditionalEasyHandles()
    {
        Server::flush();
        $response = [
            'status'  => 200,
            'headers' => ['Content-Length' => [4]],
            'body'    => 'test',
        ];

        Server::enqueue([$response, $response, $response, $response]);
        $a = new CurlHandler(['max_handles' => 2]);

        $fn = function () use (&$calls, $a, &$fn) {
            if (++$calls < 4) {
                $a([
                    'http_method' => 'GET',
                    'headers'     => ['host' => [Server::$host]],
                    'client'      => ['progress' => $fn],
                ]);
            }
        };

        $request = [
            'http_method' => 'GET',
            'headers'     => ['host' => [Server::$host]],
            'client'      => [
                'progress' => $fn,
            ],
        ];

        $a($request);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testReusesHandles()
    {
        Server::flush();
        $response = ['status' => 200];
        Server::enqueue([$response, $response]);
        $a = new CurlHandler();
        $request = [
            'http_method' => 'GET',
            'headers'     => ['host' => [Server::$host]],
        ];
        $a($request);
        $a($request);
    }
}
