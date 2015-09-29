<?php
namespace Kemer\Streaming\PseudoStream;

use Kemer\Stream\StreamInterface;
use Kemer\Stream\StreamWrapper;
use Kemer\Stream\Buffer;

class ChunkedStream extends StreamWrapper
{
    /**
     * @var Buffer
     */
    private $buffer;

    /**
     * @param StreamInterface $stream
     * @param Buffer|null $buffer
     */
    public function __construct(StreamInterface $stream, Buffer\BufferInterface $buffer = null)
    {
        parent::__construct($stream);
        $this->buffer = $buffer ?: new Buffer\Buffer();
    }

    public function read($length)
    {
        while ($this->buffer->getSize() < $length) {
            $chunk = $this->stream->read($length);
            $this->buffer->write($chunk);
        }
        $content = $this->buffer->read($length);
        return dechex(strlen($content))."\r\n".$content."\r\n";
    }
}
