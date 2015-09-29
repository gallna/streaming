<?php
namespace Kemer\Streaming\PseudoStream;

use Kemer\Stream\StreamInterface;
use Zend\Http\PhpEnvironment\Response;

/**
 * PHP stream implementation.
 *
 * @var $stream
 */
class ApacheStream implements StreamInterface
{
    private $socket;
    private $size;
    private $seekable;
    private $readable;
    private $writable;
    private $uri;
    private $customMetadata = [];
    private $metadata = [];

    /**
     * This constructor accepts an associative array of options.
     *
     * - size: (int) If a read stream would otherwise have an indeterminate
     *   size, but the size is known due to foreknownledge, then you can
     *   provide that size, in bytes.
     * - metadata: (array) Any additional metadata to return when the metadata
     *   of the stream is accessed.
     *
     * @param resource $socket  Socket resource to wrap.
     * @param array $metadata: (array) Any additional metadata to return when the metadata
     *
     * @throws \InvalidArgumentException if the stream is not a stream resource
     */
    public function __construct()
    {
    }

    /**
     * Closes the stream when the destructed
     */
    public function __destruct()
    {
        $this->close();
    }

    public function __toString()
    {

    }

    public function getContents()
    {

    }

    public function close()
    {

    }

    public function detach()
    {

    }

    public function getSize()
    {
        return null;
    }

    public function isReadable()
    {
        return false;
    }

    public function isWritable()
    {
        return true;
    }

    public function isSeekable()
    {
        return false;
    }

    public function eof()
    {
        return true;
    }

    public function tell()
    {

    }

    public function rewind()
    {
        $this->seek(0);
    }

    public function seek($offset, $whence = SEEK_SET)
    {

    }

    public function read($length)
    {

    }

    public function write($string)
    {
        if ($string instanceof Response) {
            $string->send();
            file_put_contents(__DIR__."/responses.txt", $string->toString());
            return;
        }
        echo $string;
        ob_flush(); flush();
    }

    public function getMetadata($key = null)
    {

    }
}
