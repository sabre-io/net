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
            $this->streamSelect();
        }

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

    /**
     * This method goes over all our open streams, and calls the relevant
     * methods to process any changes.
     *
     * @return void
     */
    protected function streamSelect() {

        $readStreams = $this->getClientStreams();
        $readStreams[] = $this->serverResource;

        $writeStreams = null;
        $exceptStreams = null;

        // We wait 10 seconds for something to happen, and then we return.
        $socketTimeout = 10;

        if(!stream_select($readStreams, $writeStreams, $exceptStreams, $socketTimeout)) {
            return;
        }

        $this->processPendingReadStreams($readStreams);

    }


}
