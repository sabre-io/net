<?php

namespace Sabre\Net;

class ServerTest extends \PHPUnit_Framework_TestCase {

    protected $localSocket;

    protected $server;

    protected $connectedClients;

    function setUp() {

        $this->localSocket = 'tcp://0.0.0.0:'.rand(10000, 65535);
        $this->connectedClients = [];
        $this->server = new Server($this->localSocket);

        $this->server->on('connect', function($socket) {

            $this->connectListener($socket);

            $socket->on('disconnect', function($socket) {
                $this->disconnectListener($socket);
            });

            $socket->on('data', function($socket, $data) {
                $this->dataListener($socket, $data);
            });

        });

    }

    /**
     * @expectedException Sabre\Net\Exception\CouldNotBindSocket
     */
    function testCantBindSocket() {

        $this->server = new Server($this->localSocket);

    }

    function testClientConnectDisconnect() {

        $this->assertEquals(0, count($this->connectedClients));

        $socket1 = stream_socket_client($this->localSocket, $errno, $errstr);
        $this->server->tick();

        $this->assertEquals(1, count($this->connectedClients));

        $socket2 = stream_socket_client($this->localSocket, $errno, $errstr);
        $this->server->tick();

        $this->assertEquals(2, count($this->connectedClients));

        fclose($socket1);
        $this->server->tick();

        $this->assertEquals(1, count($this->connectedClients));

        fclose($socket2);
        $this->server->tick();

        $this->assertEquals(0, count($this->connectedClients));

    }

    function testBroadcast() {

        $this->assertEquals(0, count($this->connectedClients));

        $socket1 = stream_socket_client($this->localSocket, $errno, $errstr);
        $this->server->tick();

        $socket2 = stream_socket_client($this->localSocket, $errno, $errstr);
        $this->server->tick();

        $this->assertEquals(2, count($this->connectedClients));

        $this->server->broadcast('Test');
        $this->server->tick();

        $this->assertEquals('Test', fread($socket1, 1024));
        $this->assertEquals('Test', fread($socket2, 1024));

    }

    /**
     * @expectedException LogicException
     * @medium
     */
    function testClientAddException() {

        $this->assertEquals(0, count($this->connectedClients));

        $socket = stream_socket_client($this->localSocket, $errno, $errstr);

        $add = function($socket) {

            // Ugly! Would be better to know the resource id of the socket before it gets added,
            // but there's no obvious way to get that before the client is already connected.
            // (The local ressource id of the client's socket is different from the ressource id of
            // the socket on the server)
            $id=1;
            while($id < 65535) {
                $this->clients[$id] = new Socket($socket);
                $id++;
            }

        };
        $addClients = $add->bindTo($this->server, 'Sabre\Net\Server');
        $addClients($socket);

        $this->server->tick();

    }

    /**
     * @expectedException LogicException
     * @medium
     */
    function testClientRemoveException() {

        $this->assertEquals(0, count($this->connectedClients));

        $socket = stream_socket_client($this->localSocket, $errno, $errstr);
        $this->server->tick();

        $this->assertEquals(1, count($this->connectedClients));

        $remove = function($key, $socket) {

            unset($this->clients[$key]);
            $this->removeClient($socket);

        };
        $removeClients = $remove->bindTo($this->server, 'Sabre\Net\Server');

        foreach($this->connectedClients as $key => $client) {
            $removeClients($key, $client['Socket']);
        }

    }

    function connectListener($socket) {

        $id = $socket->getId();

        if(isset($this->connectedClients[$id])) {
            throw new \LogicException('Client already exists.');
        }

        $this->connectedClients[$id]['Socket'] = $socket;

    }

    function disconnectListener($socket) {

        $id = $socket->getId();

        if(!isset($this->connectedClients[$id])) {
            throw new \LogicException('Client does not exist.');
        }

        unset($this->connectedClients[$id]);

    }

    function dataListener($socket, $data) {

        $id = $socket->getId();

        if(!isset($this->connectedClients[$id])) {
            throw new \LogicException('Client does not exist.');
        }

        $this->connectedClients[$id]['data'] = $data;

    }

}
