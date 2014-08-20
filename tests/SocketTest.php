<?php

namespace Sabre\Net;

class SocketTest extends ServerTest {

    function setUp() {

        parent::setUp();

    }

    function testSocket() {

        $this->assertEquals(0, count($this->connectedClients));

        $socket = stream_socket_client($this->localSocket, $errno, $errstr);
        $this->server->tick();

        $this->assertEquals(1, count($this->connectedClients));

        foreach($this->connectedClients as $client) {
            $this->assertInstanceOf('Sabre\Net\Socket', $client['Socket']);
            $this->assertTrue(is_numeric($client['Socket']->getId()));
            $this->assertTrue(!empty($client['Socket']->getName()));
        }

        fclose($socket);
        $this->server->tick();

        $this->assertEquals(0, count($this->connectedClients));

    }

    function testRead() {

        $this->assertEquals(0, count($this->connectedClients));

        $socket = stream_socket_client($this->localSocket, $errno, $errstr);
        $this->server->tick();

        $this->assertEquals(1, count($this->connectedClients));

        fwrite($socket, 'Test' . PHP_EOL);
        $this->server->tick();

        foreach($this->connectedClients as $client) {
            $this->assertEquals('Test' . PHP_EOL, $client['data']);
        }

    }

}
