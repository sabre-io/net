<?php

namespace Sabre\Net;

class SocketTest extends \PHPUnit_Framework_TestCase {

    function testNewSocket() {

        $stream = fopen('php://memory', 'r+');
        $socket = new Socket($stream);

        $this->assertEquals($stream, $socket->getStream());

    }

}
