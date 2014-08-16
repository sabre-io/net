<?php

namespace Dominik\TcpServer;

use Dominik\TcpServer\Socket;
use Sabre\Event;


class Socket implements Event\EventEmitterInterface {

    use Event\EventEmitterTrait;

    protected $socket;


    public function __construct($socket) {

        $this->socket = $socket;

    }

    public function getName() {

        return stream_socket_get_name($this->socket, true);

    }

    public function getSocket() {

        return $this->socket;

    }

    public function send($data) {

        fwrite($this->socket, $data);

    }

}
