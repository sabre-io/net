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
use Dominik\TcpServer\Server;

/*
 * Run Server
 */
$server = new \Dominik\TcpServer\Server('tcp://0.0.0.0:6667');

$server->on('connect', function($socket) use ($server) {
    echo 'Connected: ' . stream_socket_get_name($socket, true) . PHP_EOL;
    echo 'We have ' . count($server->getClients()) . ' connected client(s).' . PHP_EOL;
});

$server->on('disconnect', function($socket) use ($server) {
    echo 'Disconnected: ' . stream_socket_get_name($socket, true) . PHP_EOL;
    echo 'We have ' . count($server->getClients()) . ' client(s) left.' . PHP_EOL;
});

$server->on('data', function($socket, $data) use ($server) {
    if(count($server->getClients()) > 1) {
        $server->send($socket, 'Thank you, ' . stream_socket_get_name($socket, true) . '. Message received and broadcasted.' . PHP_EOL);
        $server->broadcast(stream_socket_get_name($socket, true) . ' sent: ' . $data);
    } else {
        $server->send($socket, 'Message received, but not broadcasted. You are alone! :-(' . PHP_EOL);
    }
});
$server->start();
