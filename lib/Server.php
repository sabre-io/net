<?php

namespace Sabre\Net;

use Sabre\Net\Socket;
use Sabre\Net\Exception\CouldNotBindSocket;
use Sabre\Net\Exception\StreamSelectFailed;
use Sabre\Event;

/**
 * Main TCP server class.
 *
 * @copyright Copyright (C) 2014 fruux GmbH (https://fruux.com/).
 * @author Dominik Tobschall (http://tobschall.de/)
 * @license http://sabre.io/license/ Modified BSD License
 */
class Server implements Event\EventEmitterInterface {

    use Event\EventEmitterTrait;

    /**
     * Stream socket server.
     *
     * @var resource
     */
    protected $server;

    /**
     * Array of connected Sabre/Net/Socket's.
     *
     * @var Socket[]
     */
    protected $clients = [];

    /**
     * Stream socket read streams.
     *
     * @var resource[]
     */
    protected $readStreams = [];

    /**
     * Stream socket write streams.
     *
     * @var resource[]
     */
    protected $writeStreams = [];

    /**
     * Stream socket timeout.
     *
     * @var int
     */
    protected $socketTimeout = 200000;

    /**
     * Creates a new server.
     *
     * @param string $localSocket
     * @return void
     */
    public function __construct($localSocket) {

        $this->server = stream_socket_server($localSocket, $errno, $errorMessage);
        if(!$this->server) {
            throw new CouldNotBindSocket('Could not bind to socket: ' . $errorMessage);
        }

    }

    /**
     * Returns an array of connected clients.
     *
     * @return array
     */
    public function getClients() {

        return $this->clients;

    }

    /**
     * Returns an array of streams of the connected clients.
     *
     * @return array
     */
    protected function getClientStreams() {

        $clientStreams = [];
        foreach($this->getClients() as $client) {
            $clientStreams[] = $client->getStream();
        }
        return $clientStreams;

    }

    /**
     * This method handles incoming connections of new clients.
     *
     * @param  resource $stream
     * @return void
     */
    protected function connect($stream) {

        $socket = new Socket($stream);

        $this->addClient($socket);

        $this->emit('connect', [$socket]);

        unset($this->readStreams[array_search($this->server, $this->readStreams)]);

    }

    /**
     * This method adds new clients to the clients array.
     *
     * @param Socket $socket
     * @return void
     */
    protected function addClient(Socket $socket) {

        $id = $socket->getId();

        if(isset($this->clients[$id])) {
            throw new \LogicException('Client already exists.');
        }

        $this->clients[$id] = $socket;

    }

    /**
     * This method removes clients from the clients array.
     *
     * @param Socket $socket
     * @return void
     */
    protected function removeClient(Socket $socket) {

        $id = $socket->getId();

        if(!isset($this->clients[$id])) {
            throw new \LogicException('Client does not exist.');
        }

        unset($this->clients[$id]);

    }

    /**
     * Returns the respective socket from the clients array for a given stream.
     *
     * @param resource $stream
     * @return Socket
     */
    protected function getSocketForStream($stream) {

        $id = (int) $stream;
        return $this->clients[$id];

    }

    /**
     * This method takes care of processing updates in the readStreams array.
     *
     * @return void
     */
    protected function processStreams() {

        foreach($this->readStreams as $stream) {

            $socket = $this->getSocketForStream($stream);

            if(!$socket->read())
            {
                $this->removeClient($socket);
                $socket->disconnect();

            }

        }

    }

    /**
     * Sends data to all currently connected clients.
     *
     * @param  string $data
     * @return void
     */
    public function broadcast($data) {

        foreach($this->clients as $socket) {
            $socket->send($data);
        }

    }

    /**
     * stream_select wrapper.
     *
     * @return void
     */
    protected function streamSelect() {

        $this->readStreams = $this->getClientStreams();
        $this->readStreams[] = $this->server;

        $exceptStreams = null;
        if(!stream_select($this->readStreams, $this->writeStreams, $exceptStreams, $this->socketTimeout))
        {
            throw new StreamSelectFailed();
        }

        if(in_array($this->server, $this->readStreams)) {
            $this->connect(stream_socket_accept($this->server));
        }

        $this->processStreams();

    }

    /**
     * Starts the server.
     *
     * @return void
     */
    public function start() {

        while(true)
        {
            $this->streamSelect();
        }

    }

}
