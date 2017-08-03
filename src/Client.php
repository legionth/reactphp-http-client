<?php

namespace Legionth\React\Http;

use React\Promise\Promise;
use React\EventLoop\LoopInterface;
use React\SocketClient\Connector;
use React\Socket\Connection;
use Psr\Http\Message\RequestInterface;
use React\SocketClient\ConnectorInterface;

class Client
{
    private $connector;

    public function __construct(LoopInterface $loop, ConnectorInterface $connector = null)
    {
        if ($connector === null) {
            $connector = new Connector($loop);
        }

        $this->connector = $connector;
    }

    /**
     * @param string $uri - the client will connect to this uri, e.g: tcp://google.com
     * @param RequestInterface $request - request that will be sent to the server
     * @return \React\Promise\Promise
     */
    public function request($uri, RequestInterface $request)
    {
        $connector = $this->connector;
        $that = $this;

        return new Promise(function ($resolve, $reject) use ($connector, $that, $uri, $request) {
            $connector->connect($uri)->then(function ($connection) use ($that, $resolve, $reject, $request) {
                $headerBuffer = '';
                $listener = function ($data) use (&$headerBuffer, $that, $resolve, $reject, $connection, &$listener) {
                    $headerBuffer .= $data;
                    if (strpos($headerBuffer, "\r\n\r\n") !== false) {
                        $connection->removeListener('data', $listener);
                        // header is completed
                        $fullHeader = (string)substr($headerBuffer, 0, strpos($headerBuffer, "\r\n\r\n") + 4);

                        try {
                            $response = \RingCentral\Psr7\parse_response($fullHeader);
                        } catch (\Exception $ex) {
                            return $reject($ex);
                        }

                        $stream = $connection;
                        $contentLength = 0;
                        // validate for 'Content-Length' or 'Transfer-Encoding'
                        if ($response->hasHeader('Content-Length')) {
                            $string = $response->getHeaderLine('Content-Length');

                            $contentLength = (int)$string;
                            if ((string)$contentLength !== (string)$string) {
                                // Content-Length value is not an integer or not a single integer
                                return $reject(new \InvalidArgumentException('The value of `Content-Length` is not valid'));
                            }

                            $stream = new LengthLimitedStream($stream, $contentLength);
                        } else if ($response->hasHeader('Transfer-Encoding')) {
                            if (strtolower($response->getHeaderLine('Transfer-Encoding')) !== 'chunked') {
                                return $reject(new \InvalidArgumentException('Only chunked-encoding is allowed for Transfer-Encoding'));
                            }

                            $stream = new ChunkedDecoder($stream);

                            $request = $response->withoutHeader('Transfer-Encoding');

                            $contentLength = null;
                        }

                        $bodyStream = new HttpBodyStream($stream, $contentLength);
                        $response = $response->withBody($bodyStream);
                        $resolve($response);

                        // remove header from $data, only body is left
                        $data = (string)substr($data, strlen($fullHeader));
                        if ($data !== '') {
                            $connection->emit('data', array($data));
                        }
                    };
                };

                $connection->on('data', $listener);
                $connection->write(\RingCentral\Psr7\str($request));
            },
            function (\Exception $ex) use ($reject) {
                $reject($ex);
            });
        });
    }
}
