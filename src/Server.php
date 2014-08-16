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

    }

    public function getClients() {

        $return = [];
        foreach($this->clients as $client) {
            $return[] = $client->getSocket();
        }
        return $return;

    }

    protected function connect($socket) {

        $socket = new Socket($socket);

        $this->clients[] = $socket;

        $this->emit('connect', [$socket]);

        unset($this->readStreams[array_search($this->server, $this->readStreams)]);

    }

    protected function processStreams() {

        foreach($this->readStreams as $socket) {

            $data = fread($socket, 128);
            if(!$data)
            {
                $this->emit('disconnect', [new Socket($socket)]);

                unset($this->clients[array_search($socket, $this->clients)]);
                fclose($socket);
                continue;
            }

            $this->emit('data', [new Socket($socket), $data]);
        }

    }

    public function broadcast($data) {

        foreach($this->clients as $socket) {
            $socket->send($data);
        }

    }

    protected function streamSelect() {

        $this->readStreams = $this->getClients();
        $this->readStreams[] = $this->server;

        $exceptStreams = NULL;
        if(!stream_select($this->readStreams, $this->writeStreams, $exceptStreams, $this->socketTimeout))
        {
            throw new StreamSelectFailed();
        }

        if(in_array($this->server, $this->readStreams)) {
            $this->connect(stream_socket_accept($this->server));
        }

        $this->processStreams();

    }

    public function start() {

        while(true)
        {
            $this->streamSelect();
        }

    }

}
