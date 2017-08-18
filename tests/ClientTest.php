<?php

use Legionth\React\Http\Client;
use React\SocketClient\ConnectorInterface;
use RingCentral\Psr7\Request;
use React\Promise\Promise;
use Psr\Http\Message\ResponseInterface;

class ClientTest extends TestCase
{
    private $client;
    private $connector;
    private $connection;
    private $loop;
    private $uri;
    private $promise;

    public function setUp()
    {
        $this->uri = 'tcp://reactphp.org';
        $this->loop = new React\EventLoop\StreamSelectLoop();

        $this->connector = $this->getMockBuilder('React\SocketClient\ConnectorInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $this->connection = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->setMethods(
                array(
                    'write',
                    'end',
                    'close',
                    'pause',
                    'resume',
                    'isReadable',
                    'isWritable'
                )
            )
            ->getMock();

        $connection = &$this->connection;
        $this->promise = new Promise(function ($resolve, $reject) use (&$connection) {
            $resolve($connection);
        });
    }

    public function testClientRequestWillReturnPromise()
    {
        $this->connector->expects($this->once())
            ->method('connect')
            ->with($this->equalTo($this->uri))
            ->willReturn($this->promise);

        $client = new Client($this->loop, $this->connector);

        $responsePromise = $client->request($this->uri, new Request('GET', 'http://reactphp.org'));

        $this->assertInstanceOf('React\Promise\Promise', $responsePromise);
    }

    public function testClientRequestWillResultInResponse()
    {
        $this->connector->expects($this->once())
            ->method('connect')
            ->with($this->equalTo($this->uri))
            ->willReturn($this->promise);

        $client = new Client($this->loop, $this->connector);

        $data = '';
        $responsePromise = $client->request($this->uri, new Request('GET', 'http://reactphp.org'));
        $responsePromise->then(function (ResponseInterface $response) use (&$data) {
            $data = \RingCentral\Psr7\str($response);
        });

        $this->connection->emit('data', array("HTTP/1.1 200 OK\r\n\r\n"));
        $this->assertEquals("HTTP/1.1 200 OK\r\n\r\n", $data);
    }

    public function testTransferEncodingResponseWillEmitEndEvent()
    {
        $this->connector->expects($this->once())
            ->method('connect')
            ->with($this->equalTo($this->uri))
            ->willReturn($this->promise);

        $client = new Client($this->loop, $this->connector);

        $data = '';
        $expectCalledOnce = $this->expectCallableOnce();

        $responsePromise = $client->request($this->uri, new Request('GET', 'http://reactphp.org'));
        $responsePromise->then(function (ResponseInterface $response) use (&$data, $expectCalledOnce) {
            $response->getBody()->on('data', function ($chunk) use (&$data){
                $data = $chunk;
            });
            $response->getBody()->on('end', $expectCalledOnce);
        });

        $this->connection->emit('data', array("HTTP/1.1 200 OK\r\nTransfer-Encoding: chunked\r\n\r\n5\r\nhello\r\n0\r\n\r\n"));
        $this->assertEquals('hello', $data);
    }

    public function testContentLengthResponseWillEmitEndEvent()
    {
        $this->connector->expects($this->once())
            ->method('connect')
            ->with($this->equalTo($this->uri))
            ->willReturn($this->promise);

        $client = new Client($this->loop, $this->connector);

        $data = '';
        $expectCalledOnce = $this->expectCallableOnce();

        $responsePromise = $client->request($this->uri, new Request('GET', 'http://reactphp.org'));
        $responsePromise->then(function (ResponseInterface $response) use (&$data, $expectCalledOnce) {
            $response->getBody()->on('data', function ($chunk) use (&$data){
                $data = $chunk;
            });
            $response->getBody()->on('end', $expectCalledOnce);
        });

        $this->connection->emit('data', array("HTTP/1.1 200 OK\r\nContent-Length: 5\r\n\r\nhello"));
        $this->assertEquals('hello', $data);
    }

    public function testInvalidContentLengthWillResultInInvalidException()
    {
        $this->connector->expects($this->once())
            ->method('connect')
            ->with($this->equalTo($this->uri))
            ->willReturn($this->promise);

        $client = new Client($this->loop, $this->connector);

        $result = null;

        $responsePromise = $client->request($this->uri, new Request('GET', 'http://reactphp.org'));
        $responsePromise->then(
            $this->expectCallableNever(),
            function ($exception) use (&$result) {
                $result = $exception;
            }
        );

        $this->connection->emit('data', array("HTTP/1.1 200 OK\r\nContent-Length: bla\r\n\r\nhello"));
        $this->assertInstanceOf('InvalidArgumentException', $result);
    }

    public function testInvalidTransferEncodingWillResultInInvalidException()
    {
        $this->connector->expects($this->once())
            ->method('connect')
            ->with($this->equalTo($this->uri))
            ->willReturn($this->promise);

        $client = new Client($this->loop, $this->connector);

        $result = null;

        $responsePromise = $client->request($this->uri, new Request('GET', 'http://reactphp.org'));
        $responsePromise->then(
            $this->expectCallableNever(),
            function ($exception) use (&$result) {
                $result = $exception;
            }
        );

        $this->connection->emit('data', array("HTTP/1.1 200 OK\r\nTransfer-Encoding: bla\r\n\r\n5\r\nhello\r\n0\r\n\r\n"));
        $this->assertInstanceOf('InvalidArgumentException', $result);
    }

    public function testFailingConnectionReturnsException()
    {
        $connector = $this->getMockBuilder('React\SocketClient\ConnectorInterface')
            ->disableOriginalConstructor()
            ->getMock();

        $promise = new Promise(function ($resolve, $reject) {
            $reject(new Exception());
        });

        $connector->expects($this->once())
            ->method('connect')
            ->with($this->equalTo($this->uri))
            ->willReturn($promise);

        $client = new Client($this->loop, $connector);

        $responsePromise = $client->request($this->uri, new Request('GET', 'http://reactphp.org'));
        $responsePromise->then(
            $this->expectCallableNever(),
            function ($exception) use (&$result) {
                $result = $exception;
            }
        );

        $this->connection->emit('data', array("HTTP/1.1 200 OK\r\n\r\n"));
        $this->assertInstanceOf('Exception', $result);
    }

    public function testInvalidResponseResultsInException()
    {
        $this->connector->expects($this->once())
            ->method('connect')
            ->with($this->equalTo($this->uri))
            ->willReturn($this->promise);

        $client = new Client($this->loop, $this->connector);

        $result = null;

        $responsePromise = $client->request($this->uri, new Request('GET', 'http://reactphp.org'));
        $responsePromise->then(
            $this->expectCallableNever(),
            function ($exception) use (&$result) {
                $result = $exception;
            }
        );

        $this->connection->emit('data', array("blaHTTP/1.1 200 OK\r\n\r\n"));
        $this->assertInstanceOf('InvalidArgumentException', $result);
    }
}
