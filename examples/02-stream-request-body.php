<?php

use RingCentral\Psr7\Response;
use Legionth\React\Http\Client;
use Legionth\React\Http\Request;
use React\Stream\ReadableStream;

require __DIR__ . '/../vendor/autoload.php';

// This examples is made for local HTTP server on port 10000
// You can use the 'https://github.com/reactphp/http' server
// Execute '09-stream-request.php' to try this example

$loop = React\EventLoop\Factory::create();

$client = new Client($loop);

$uri = 'tcp://127.0.0.1:10000';
$stream = new $stream = new \React\Stream\ReadableResourceStream(STDIN, $loop);

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
$promise->then(
    function (Response $response) {
        echo RingCentral\Psr7\str($response);
        $response->getBody()->on('data', function ($data) {
            echo $data;
        });

        $response->getBody()->on('end', function () {
            return;
        });
    },
    function (\Exception $exception) {
        echo $exception->getMessage() . PHP_EOL;
    }
);


$loop->run();
