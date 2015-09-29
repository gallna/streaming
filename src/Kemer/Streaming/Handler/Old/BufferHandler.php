<?php
namespace Kemer\Stream\Handler;

use Psr\Http\Message\StreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;

class BufferHandler
{
    private $contentType = "video/mp4";
    private $stream;
    private $cache = '';
    private $initialized = false;
    private $buffer = 102400;

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

    public function handle(Request $request, StreamInterface $conn)
    {
        $length = 1000000000;
        // $accept = $request->getHeader("accept");

        // $iAccept = [$this->getContentType(), "*"];
        // $notAccept = ["text/html", "application/xhtml+xml", "application/xml"];
        // $allowed = true;
        // foreach ($accept->getPrioritized() as $type) {
        //     if (in_array($type->getTypeString(), $notAccept)) {
        //         var_dump(["disallowed" => $type->getTypeString()]);
        //         $allowed = false;
        //     }
        // }
        // if ($allowed) {
        //     foreach ($accept->getPrioritized() as $type) {
        //         if (in_array($type->getTypeString(), $iAccept)) {
        //             var_dump(["allowed" => $type->getTypeString()]);
        //             $allowed = true;
        //         }
        //     }
        // }
        // if (!$allowed) {
        //     $response = new Response();
        //     $response->setStatusCode(Response::STATUS_CODE_406);
        //     $response->getHeaders()->addHeaders([
        //         'Content-Type' => $this->getContentType(),
        //         // 'Accept-Ranges' => 'bytes',
        //         // 'Content-Length' => $length,
        //         // 'Content-Range' => sprintf("bytes %d-%d/%d", 0, $length - 1, $length),
        //     ]);
        //     $conn->write($response->toString());
        //     echo $response;
        //     return;
        // }




        if (!$request->getHeader("Range")) {
            $response = $this->getRangeError(0, $length);
            e($response->toString(), 'purple');
            return $conn->write($response->toString());
        }
        list(, $range) = explode('=', $request->getHeader("range")->getFieldValue(), 2);
        list($start, $end) = explode('-', $range);

        $end = is_numeric($end) ? $end : $start + $length;
        if ($start > $end) {
            $response = $this->getRangeError($start, $end);
            return $conn->write($response);
        }
        $response = $this->getResponse($start, $end, $length);
        e($response, 'blue');
        $conn->write($response);
        if ($end == $length) {
            $part = $this->buffer;
            $theStart = $start;
            $theEnd = $this->buffer;
            while (false !== ($data = $this->getContent($theStart, $theEnd))) {
                $conn->write($data);
                $theStart = $theEnd + 1;
                $theEnd += $theStart + $this->buffer;
            }
        }

        return $conn->write($this->getContent($start, $end));
    }

    private function getResponse($start, $end, $length)
    {

        if ($end > $length) {
            $end = $length;
        };

        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_206);
        $response->getHeaders()->addHeaders([
            'Content-Type' => $this->getContentType(),
            'Accept-Ranges' => 'bytes',
            //'Cache-Control' => 'max-age=2592000, public',
            //"Expires" => gmdate('D, d M Y H:i:s', time()+2592000) . ' GMT',
            //"Last-Modified" => gmdate('D, d M Y H:i:s', strtotime("today")) . ' GMT',
            'Content-Length' => $end - $start,
            'Content-Range' => sprintf("bytes %d-%d/%d", $start, $end - 1, $length),
        ]);
        //$response->setContent($this->getContent($start, $end));
        return $response;
    }

    /**
     * perform the streaming of calculated range
     */
    private function getContent($start, $end)
    {
        if (!$this->initialized) {
            e("initializing", 'red');
            $this->initialize();
            $this->initialized = true;
        }
        $length = $end - $start;
        if ($bufferLength = strlen($this->cache)) {
            $cache = $this->cache;
            $this->cache = '';
            $cache .= $this->stream->read($length - $bufferLength);
            var_dump(strlen($cache));
            return $cache;
        }
        $content = $this->stream->read($length);
        return $content;
    }

    /**
     * perform the streaming of calculated range
     */
    private function initialize()
    {
        $request = new Request();
        $request->setUri("http://10.0.10.107:8080/file.mp4");
        $request->setMethod('GET');
        $this->stream->write($request->toString());
        var_dump($request->toString());
        $head = $this->stream->read($this->buffer);
        list($headers, $content) = explode("\r\n\r\n", $head);
        var_dump($headers);
        $this->cache = $content;
    }

    private function getRangeError($start, $end)
    {
        $response = new Response();
        $response->setStatusCode(Response::STATUS_CODE_416);
            $response->getHeaders()->addHeaders([
                'Content-Length' => $end,
                'Content-Range' => sprintf("bytes %d-%d/%d", $start, $end - 1, $end),
            ]);
        return $response;
    }
}
