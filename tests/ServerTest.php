<?php

namespace Sabre\Net;

class ServerTest extends \PHPUnit_Framework_TestCase {

    protected $localSocket;

    protected $server;

    protected $connectedClients = [];

    function setUp() {

        $this->localSocket = 'tcp://0.0.0.0:'.rand(10000, 65535);
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
