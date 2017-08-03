<?php

use RingCentral\Psr7\Response;
use RingCentral\Psr7\Request;
use Legionth\React\Http\Client;

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$client = new Client($loop);

$uri = 'tcp://httpbin.org:80';
$request = new Request('GET', 'http://httpbin.org');

$promise = $client->request($uri, $request);
$promise->then(
    function (Response $response) {
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

$loop->run();
