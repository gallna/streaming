<?php
namespace Kemer\Stream\Handler;

use Psr\Http\Message\StreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Http\PhpEnvironment\Response as PhpResponse;
use Kemer\Stream\Stream;

class MpegtsHandler
{
    private $contentType = "video/mp4";
    private $stream;
    private $buffer = 4096;
    private $command;
    private $pipes;
    private $process;

    public function __construct()
    {

    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function getSize()
    {
        return $this->stream->getSize();
    }

    public function handle($url, $conn)
    {
        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_200);
        $response->getHeaders()->addHeaders([
            'Content-Type' => $this->getContentType(),
            'Accept-Ranges' => 'bytes',
            'Transfer-Encoding' => 'chunked',
        ]);
        e($response, 'blue');
        $conn->write($response->toString());

        $command = $this->createCommand($url);
        $pipeStream = new Stream\ReadPipeStream($command);
        $chunkedStream = new Stream\ChunkedStream($pipeStream);
        while ($chunk = $chunkedStream->read($this->buffer)) {
            $conn->write($chunk);
        }
        $conn->write("0\r\n\r\n");
        $this->close();
    }

    private function createCommand($url)
    {
        return $this->command = sprintf('ffmpeg -i %s -c copy -f mpegts pipe:', $url);
    }
}
