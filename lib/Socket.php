<?php

namespace Sabre\Net;

use Sabre\Net\Socket;
use Sabre\Event;

/**
 * The Socket class represents a single client socket.
 *
 * @copyright Copyright (C) 2009-2014 fruux GmbH (https://fruux.com/).
 * @author Dominik Tobschall (http://tobschall.de/)
 * @license http://sabre.io/license Modified BSD License
 */
class Socket implements Event\EventEmitterInterface {

    use Event\EventEmitterTrait;

    /**
     * Ressouce id.
     *
     * @var int
     */
    protected $id;

    /**
     * Ressource name.
     *
     * @var string
     */
    protected $name;

    /**
     * The actual stream
     *
     * @var ressource
     */
    protected $stream;

    /**
     * Creates a new Socket object.
     *
     * @param ressource $stream
     * @return void
     */
    public function __construct($stream) {

        $this->id = (int) $stream;
        $this->name = stream_socket_get_name($stream, true);
        $this->stream = $stream;

    }

    /**
     * Returns the ressource id.
     *
     * @return int
     */
    public function getId() {

        return $this->id;

    }

    /**
     * Returns the ressource name.
     *
     * @return string
     */
    public function getName() {

        return $this->name;

    }

    /**
     * Returns the stream.
     *
     * @return ressource
     */
    public function getStream() {

        return $this->stream;

    }

    /**
     * Reads data from the stream.
     *
     * @return string
     */
    public function read() {

        $data = fgets($this->stream);

        if($data) {
            $this->emit('data', [$this, $data]);
        }

        return $data;

    }

    /**
     * Sends data to the stream.
     *
     * @param  string $data
     * @return void
     */
    public function send($data) {

        fwrite($this->stream, $data);

    }

    /**
     * Disconnect the client.
     *
     * @return void
     */
    public function disconnect() {

        $this->emit('disconnect', [$this]);
        fclose($this->stream);

    }

}
