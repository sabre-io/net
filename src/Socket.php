<?php

namespace Dominik\TcpServer;

use Dominik\TcpServer\Socket;
use Sabre\Event;


class Socket implements Event\EventEmitterInterface {

    use Event\EventEmitterTrait;

    protected $id;

    protected $name;

    protected $stream;


    public function __construct($stream) {

        $this->id = (int) $stream;
        $this->name = stream_socket_get_name($stream, true);
        $this->stream = $stream;


    }

    public function getId() {

        return $this->id;

    }

    public function getName() {

        return $this->name;

    }

    public function getStream() {

        return $this->stream;

    }

    public function read($length = 128) {

        $data = fread($this->stream, $length);

        if($data) {
            $this->emit('data', [$this, $data]);
        }

        return $data;

    }

    public function send($data) {

        fwrite($this->stream, $data);

    }

    public function disconnect() {

        $this->emit('disconnect', [$this]);
        fclose($this->stream);

    }

}
