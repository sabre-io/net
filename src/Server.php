<?php

namespace Dominik\TcpServer;

use Dominik\TcpServer\Exception\CouldNotBindSocket;
use Dominik\TcpServer\Exception\StreamSelectFailed;
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
            throw new CouldNotBindSocket('Could not bind to socket: ' . $errorMessage);
        }

        $this->registerEvents();

    }

    protected function registerEvents() {
        $this->on('connect', [$this, 'connect']);
        $this->on('read', [$this, 'read']);
        $this->on('pong', [$this, 'pong']);
    }

    protected function connect($client) {

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
        $this->send($socket, 'PONG: ' . $data);
        $this->broadcast($data);
    }

    public function broadcast($data) {
        foreach($this->clientSockets as $socket) {
            $this->send($socket, $data);
        }
    }

    public function send($socket, $data) {
        fwrite($socket, $data);
    }

    public function start() {

        while(true)
        {
            $this->readSockets = $this->clientSockets;
            $this->readSockets[] = $this->server;

            if(!stream_select($this->readSockets, $this->writeSockets, $this->exceptSockets, $this->socketTimeout))
            {
                throw new StreamSelectFailed();
            }

            if(in_array($this->server, $this->readSockets)) {
                $this->emit('connect', [stream_socket_accept($this->server)]);
            }

            $this->emit('read');

        }

    }

}
