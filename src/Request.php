<?php

namespace Legionth\React\Http;

use RingCentral\Psr7\Request as Psr7Request;
use React\Stream\ReadableStreamInterface;
use Legionth\React\Http\HttpBodyStream;

/**
 * Implementation of the PSR-7 RequestInterface
 * This class is an extension of RingCentral\Psr7\Request.
 * The only difference is that this class will accept implemenations
 * of the ReactPHPs ReadableStreamInterface for $body.
 */
class Request extends Psr7Request
{
    public function __construct(
        $method,
        $uri,
        array $headers = array(),
        $body = null,
        $protocolVersion = '1.1'
    ) {
        if ($body instanceof ReadableStreamInterface) {
            $body = new HttpBodyStream($body, null);
        }

        parent::__construct(
            $method,
            $uri,
            $headers,
            $body,
            $protocolVersion
        );
    }
}
