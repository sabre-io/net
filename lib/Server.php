<?php

namespace Sabre\Net;

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
     * Creates a new server.
     *
     * @param string $localSocket
     */
    function __construct($localSocket) {

        $this->serverResource = stream_socket_server($localSocket, $errno, $errorMessage);
        if(!$this->serverResource) {
            throw new Exception\CouldNotBindSocket('Could not bind to socket: ' . $errorMessage);
        }

    }

    /**
     * Returns an array of connected clients.
     *
     * @return array
     */
    function getClients() {

        return $this->clients;

    }

    /**
     * Sends data to all currently connected clients.
     *
     * @param string $data
     * @return void
     */
    function broadcast($data) {

        foreach($this->clients as $socket) {
            $socket->send($data);
        }

    }

    /**
     * Starts the server.
     *
     * @return void
     */
    function start() {

        while(true) {
            // Waiting 10 seconds for something to happen.
            $this->tick(10);
        }

    }

    /**
     * Executes a single server tick.
     *
     * A tick is when the server waits for data coming from sockets and new
     * connections, and does all its processing.
     *
     * If there is nothing going on, the server can wait for x seconds for
     * something to happen, using the $timeOut argument. Set that argument to 0
     * to not wait.
     *
     * @return void
     */
    function tick($timeOut = 0) {

        $readStreams = $this->getClientStreams();
        $readStreams[] = $this->serverResource;

        $writeStreams = null;
        $exceptStreams = null;

        if(!stream_select($readStreams, $writeStreams, $exceptStreams, $timeOut)) {
            return;
        }

        $this->processPendingReadStreams($readStreams);

    }

    /**
     * Stream socket server.
     *
     * @var resource
     */
    protected $serverResource;

    /**
     * Array of connected Sabre/Net/Socket's.
     *
     * @var Socket[]
     */
    protected $clients = [];

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
     * @param resource $stream
     * @return void
     */
    protected function connect($stream) {

        $socket = new Socket($stream);

        $this->addClient($socket);

        $this->emit('connect', [$socket]);

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

        $id = (int)$stream;
        return $this->clients[$id];

    }

    /**
     * This method processes any readable streams that have pending data.
     *
     * @param resource[] $streams
     * @return void
     */
    protected function processPendingReadStreams($streams) {

        foreach($streams as $stream) {

            if ($stream === $this->serverResource) {
                // If the server stream is in this list, a new client
                // connected.
                $this->connect(stream_socket_accept($this->serverResource));
                continue;
            }

            $socket = $this->getSocketForStream($stream);

            if(!$socket->read())
            {
                $this->removeClient($socket);
                $socket->disconnect();

            }

        }

    }

}
