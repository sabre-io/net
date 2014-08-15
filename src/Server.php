<?php

namespace Dominik\TcpServer;

use Sabre\Event;


class Server implements Event\EventEmitterInterface {

    use Event\EventEmitterTrait;

    protected $server;

    protected $clientSockets = array();

    protected $writeSockets = array();

    protected $exceptSockets = array();

    protected $socketTimeout = 200000;


    public function __construct($socket) {

        $this->server = stream_socket_server($socket, $errno, $errorMessage);
        if(!$this->server) {
            throw new Exception('Could not bind to socket: ' . $errorMessage);
        }

        $this->registerEvents();

    }

    protected function registerEvents() {
        $this->on('connect', [$this, 'connect']);
        $this->on('read', [$this, 'read']);
        $this->on('pong', [$this, 'pong']);
    }

    protected function connect() {

        $client = stream_socket_accept($this->server);
        if($client) {

            $this->clientSockets[] = $client;
            echo 'Connected: ' . stream_socket_get_name($client, true) . PHP_EOL;
            echo 'We have ' . count($this->clientSockets) . ' connected client(s).' . PHP_EOL;

        }

        unset($this->readSockets[array_search($this->server, $this->readSockets)]);

    }

    protected function read() {

        foreach($this->readSockets as $socket) {

            $data = fread($socket, 128);
            if(!$data)
            {
                unset($this->clientSockets[array_search($socket, $this->clientSockets)]);
                fclose($socket);
                echo 'Client disconnected. ' . count($this->clientSockets) . ' connected client(s) left.' . PHP_EOL;
                continue;
            }

            $this->emit('pong', [$socket, $data]);
        }

    }

    protected function pong($socket, $data) {
        fwrite($socket, 'PONG: ' . $data);
    }

    public function run() {

        while(true)
        {
            $this->readSockets = $this->clientSockets;
            $this->readSockets[] = $this->server;

            if(!stream_select($this->readSockets, $this->writeSockets, $this->exceptSockets, $this->socketTimeout))
            {
                throw new Exception('stream_select failed.');
            }

            if(in_array($this->server, $this->readSockets)) {
                $this->emit('connect');
            }

            $this->emit('read');

        }

    }

}
