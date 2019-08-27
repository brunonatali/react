<?php

namespace React\Socket;

use React\EventLoop\LoopInterface;
use React\Promise;
use InvalidArgumentException;
use RuntimeException;

/**
 * Unix domain socket connector
 *
 * Unix domain sockets use atomic operations, so we can as well emulate
 * async behavior.
 */
final class UnixConnector implements ConnectorInterface
{
    private $loop;
	public $res;
	private $socketSystem = false;
	private $socketProtocol = 1;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function connect($path, $unixType = "stream", $forceSocket = false)
    {
		$this->socketSystem = (($unixType == "dgram") or $forceSocket);
		if($this->socketSystem){
			if($unixType == "stream" && $forceSocket){
				$path = substr($path, 7);
				$resource = @\socket_create(AF_UNIX, SOCK_STREAM, 0);
				socket_connect($resource, $path);
			} else if($unixType == "dgram"){
				$path = substr($path, 7);
				$resource = @\socket_create(AF_UNIX, SOCK_DGRAM, 0);
				socket_bind($resource, $path);
			}
		} else {
			if (\strpos($path, '://') === false) {
				$path = 'unix://' . $path;
			} elseif (\substr($path, 0, 7) !== 'unix://') {
				return Promise\reject(new \InvalidArgumentException('Given URI "' . $path . '" is invalid'));
			}

			$resource = @\stream_socket_client($path, $errno, $errstr, 1.0);
		}
		
		if (!$resource) {
			return Promise\reject(new \RuntimeException('Unable to connect to unix domain socket "' . $path . '": ' . $errstr, $errno));
		}
		$this->res = $resource;
        $connection = new Connection($resource, $this->loop, $this->socketSystem);
        $connection->unix = true;

        return Promise\resolve($connection);
    }	

	public function get_resource()
	{
		return $this->res;
	}
}
