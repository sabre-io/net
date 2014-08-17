<?php

namespace Sabre\Net;

use Sabre\Net\Socket;
use Sabre\Net\Exception\CouldNotBindSocket;
use Sabre\Net\Exception\StreamSelectFailed;
use Sabre\Event;


class Server implements Event\EventEmitterInterface {

    use Event\EventEmitterTrait;

    protected $server;

    protected $clients = [];

    protected $readStreams = [];

    protected $writeStreams = [];

    protected $socketTimeout = 200000;


    public function __construct($localSocket) {

        $this->server = stream_socket_server($localSocket, $errno, $errorMessage);
        if(!$this->server) {
            throw new CouldNotBindSocket('Could not bind to socket: ' . $errorMessage);
        }

    }

    public function getClients() {

        return $this->clients;

    }

    protected function getClientStreams() {

        $clientStreams = [];
        foreach($this->getClients() as $client) {
            $clientStreams[] = $client->getStream();
        }
        return $clientStreams;

    }

    protected function connect($stream) {

        $socket = new Socket($stream);

        $this->addClient($socket);

        $this->emit('connect', [$socket]);

        unset($this->readStreams[array_search($this->server, $this->readStreams)]);

    }

    protected function addClient(Socket $socket) {

        $id = $socket->getId();

        if(isset($this->clients[$id])) {
            throw new \LogicException('Client already exists.');
        }

        $this->clients[$id] = $socket;

    }

    protected function removeClient(Socket $socket) {

        $id = $socket->getId();

        if(!isset($this->clients[$id])) {
            throw new \LogicException('Client does not exist.');
        }

        unset($this->clients[$id]);

    }

    protected function getSocketForStream($stream) {

        $id = (int) $stream;
        return $this->clients[$id];

    }

    protected function processStreams() {

        foreach($this->readStreams as $stream) {

            $socket = $this->getSocketForStream($stream);

            if(!$socket->read())
            {
                $this->removeClient($socket);
                $socket->disconnect();

                continue;
            }

        }

    }

    public function broadcast($data) {

        foreach($this->clients as $socket) {
            $socket->send($data);
        }

    }

    protected function streamSelect() {

        $this->readStreams = $this->getClientStreams();
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
