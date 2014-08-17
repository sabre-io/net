#!/usr/bin/env php
<?php

/*
 * Find the Composer autoloader.
 * Credit: https://github.com/evert/sabre-vobject/blob/master/bin/vobjectvalidate.php
 */

$paths = array(
    __DIR__ . '/../vendor/autoload.php',  // In case the project is cloned directly
    __DIR__ . '/../../../autoload.php',   // In case the project is a composer dependency.
);

foreach ($paths as $path) {
    if (file_exists($path)) {
        include($path);
        break;
    }
}

/*
 * Import namespaces
 */
use Sabre\Net\Server;

$address = 'tcp://0.0.0.0:6667';

/*
 * Run Server
 */
$server = new Server($address);

echo "Starting server at $address\n";

$server->on('connect', function($socket) use ($server) {
    echo 'Connected: ' . $socket->getName() . PHP_EOL;
    echo 'We have ' . count($server->getClients()) . ' connected client(s).' . PHP_EOL;

    $socket->on('disconnect', function($socket) use ($server, $socket) {
        echo 'Disconnected: ' . $socket->getName() . PHP_EOL;
        echo 'We have ' . count($server->getClients()) . ' client(s) left.' . PHP_EOL;
    });

    $socket->on('data', function($socket, $data) use ($server, $socket) {
        if(count($server->getClients()) > 1) {
            $socket->send('Thank you, ' . $socket->getName() . '. Message received and broadcasted.' . PHP_EOL);
            $server->broadcast($socket->getName() . ' sent: ' . $data . PHP_EOL);
        } else {
            $socket->send('Message received, but not broadcasted. You are alone! :-(' . PHP_EOL);
        }
    });

});

$server->start();
