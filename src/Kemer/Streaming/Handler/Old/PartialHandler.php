<?php
namespace Kemer\Stream\Handler;

use Psr\Http\Message\StreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;

class PartialHandler
{
    private $contentType = "video/mp4";
    private $stream;
    private $buffer = 1024000;

    public function __construct(StreamInterface $stream = null)
    {
        $this->stream = $stream;

        if ($contentType = $stream->getMetadata("content-type")) {
            $this->contentType = $contentType;
        }
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function getSize()
    {
        return $this->stream->getSize();
    }

    public function handle(Request $request, StreamInterface $conn)
    {
        if (!$request->getHeader("Range")) {
            $response = $this->getRangeError(0, $this->getSize());
            e($response->toString(), 'purple');
            return $conn->write($response->toString());
            throw new \Exception("This handler accept only requests with Range header");
        }
        list(, $range) = explode('=', $request->getHeader("range")->getFieldValue(), 2);
        list($start, $end) = explode('-', $range);
        $end = is_numeric($end) ? $end : $start + $this->buffer;
        if ($start > $end) {
            $response = $this->getRangeError($start, $end);
            return $conn->write($response);
        }
        $response = $this->getResponse($start, $end);
        e($response, 'blue');
        $conn->write($response);
        return $conn->write($this->getContent($start, $end));
    }

    private function getResponse($start, $end)
    {
        if ($end > $this->getSize()) {
            $end = $this->getSize();
        };

        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_206);
        $response->getHeaders()->addHeaders([
            'Content-Type' => $this->getContentType(),
            'Accept-Ranges' => 'bytes',
            //'Cache-Control' => 'max-age=2592000, public',
            //"Expires" => gmdate('D, d M Y H:i:s', time()+2592000) . ' GMT',
            //"Last-Modified" => gmdate('D, d M Y H:i:s', strtotime("today")) . ' GMT',
            'Content-Length' => $length = $end - $start,
            'Content-Range' => sprintf("bytes %d-%d/%d", $start, $end - 1, $this->getSize()),
        ]);
        //$response->setContent($this->getContent($start, $length));
        return $response;
    }

    /**
     * perform the streaming of calculated range
     */
    private function getContent($start, $end)
    {
        $this->stream->seek($start);
        return $this->stream->read($end - $start);
    }

    private function getRangeError($start, $end)
    {
        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_416);
            $response->getHeaders()->addHeaders([
                'Content-Length' => $length = $this->getSize(),
                'Content-Range' => sprintf("bytes %d-%d/%d", $start, $end - 1, $this->getSize()),
            ]);
        return $response;
    }
}
