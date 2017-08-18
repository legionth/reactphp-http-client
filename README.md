# legionth/http-client-react

HTTP client written in PHP on top of ReactPHP.

**Table of Contents**
* [Usage](#usage)
  * [Request body](#request-body)
* [Install](#install)
* [License](#license)

## Usage

The client is responsible to send HTTP requests and receive the HTTP response
from the server.

This library uses [PSR-7](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md)
objects to make it easier to handle the HTTP-Messsages.

```php
$uri = 'tcp://httpbin.org:80';
$request = new Request('GET', 'http://httpbin.org');

$promise = $client->request($uri, $request);
```

It could take some time until the response is transferred from the server
to the client.
For this reason the `request`-method will return a
[ReactPHP promise](https://github.com/reactphp/promise).

The promise will result in a
[PSR-7 response object](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message.md#33-psrhttpmessageresponseinterface)

```php
$promise = $client->request($uri, $request);
$promise->then(
    function (\Psr\Http\Message\ResponseInterface $response) {
        echo 'Successfully received a response from the server:' . PHP_EOL;
        echo RingCentral\Psr7\str($response);
    },
    function (\Exception $exception) {
        echo $exception->getMessage() . PHP_EOL;
    }
);
```

The body of the response will always be an asynchronous
[ReactPHP Stream](https://github.com/reactphp/stream).

```php
$promise = $client->request($uri, $request);
$promise->then(
    function (ResponseInterface $response) {
        echo 'Successfully received a response from the server:' . PHP_EOL;
        echo RingCentral\Psr7\str($response);

        $body = $response->getBody();
        $body->on('data', function ($data) {
            echo "Body-Data: " . $data . PHP_EOL;
        });

        $body->on('end', function () {
            exit(0);
        });
    },
    function (\Exception $exception) {
        echo $exception->getMessage() . PHP_EOL;
    }
);
```

The `end`-Event will be emmitted when the complete body of the HTTP response
has been transferred to the client.
In the example above it will exit the current script.

### Request body

You can add also add a [ReactPHP Stream](https://github.com/reactphp/stream) as
the request body to stream data with it.
The body will always be transferred chunked encoded if you use this method,
any header like `Content-Length` or other `Transfer-Encoding` headers will
be replaced.
```php
$stream = new ReadableStream();

$timer = $loop->addPeriodicTimer(0.5, function () use ($stream) {
    $stream->emit('data', array(microtime(true) . PHP_EOL));
});

$loop->addTimer(5, function() use ($loop, $timer, $stream) {
    $loop->cancelTimer($timer);
    $stream->emit('end');
});

$request = new Request(
    'POST',
    'http://127.0.0.1:10000',
    array(
        'Host' => '127.0.0.1',
        'Content-Type' => 'text/plain'
    ),
    $stream
);
$promise = $client->request($uri, $request);
```

This example will transfer every 0.5 seconds a chunked encoded data to the
server.
The transmission of the body will end after 5 seconds.

Checkout the `examples` folder to try it yourself.

## Install

[New to Composer?](https://getcomposer.org/doc/00-intro.md)

This will install the latest supported version:

```bash
$ composer require legionth/http-client-react:^0.1
```

## License

MIT
