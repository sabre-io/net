<?php

namespace Dominik\TcpServer;

use Dominik\TcpServer\Socket;
use Dominik\TcpServer\Exception\CouldNotBindSocket;
use Dominik\TcpServer\Exception\StreamSelectFailed;
use Sabre\Event;


class Server implements Event\EventEmitterInterface {

    use Event\EventEmitterTrait;

    protected $server;

    protected $clients = [];

    protected $readStreams = [];

    protected $writeStreams = [];

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
        $this->on('data', [$this, 'data']);
        $this->on('pong', [$this, 'pong']);
    }

    protected function connect($client) {

        if($client) {

            $this->clients[] = $client;
            echo 'Connected: ' . stream_socket_get_name($client, true) . PHP_EOL;
            echo 'We have ' . count($this->clients) . ' connected client(s).' . PHP_EOL;

        }

        unset($this->readStreams[array_search($this->server, $this->readStreams)]);

    }

    protected function data() {

        foreach($this->readStreams as $socket) {

            $data = fread($socket, 128);
            if(!$data)
            {
                unset($this->clients[array_search($socket, $this->clients)]);
                fclose($socket);
                echo 'Client disconnected. ' . count($this->clients) . ' connected client(s) left.' . PHP_EOL;
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

        foreach($this->clients as $socket) {
            $this->send($socket, $data);
        }

    }

    public function send($socket, $data) {

        fwrite($socket, $data);

    }

    public function start() {

        while(true)
        {
            $this->readStreams = $this->clients;
            $this->readStreams[] = $this->server;

            $exceptStreams = NULL;
            if(!stream_select($this->readStreams, $this->writeStreams, $exceptStreams, $this->socketTimeout))
            {
                throw new StreamSelectFailed();
            }

            if(in_array($this->server, $this->readStreams)) {
                $this->emit('connect', [stream_socket_accept($this->server)]);
            }

            $this->emit('data');
        }

    }

}
