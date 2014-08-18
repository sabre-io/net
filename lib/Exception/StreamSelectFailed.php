<?php

namespace Sabre\Net\Exeption;

/**
 * StreamSelectFailed
 *
 * Is thrown if the stream_select call failed.
 * Probably because the server incorrectly assumed that certain clients are still connected.
 *
 * @copyright Copyright (C) 2014 fruux GmbH (https://fruux.com/).
 * @author Dominik Tobschall (http://tobschall.de/)
 * @license http://sabre.io/license/ Modified BSD License
 */

class StreamSelectFailed extends \Sabre\Net\Exception {

}
