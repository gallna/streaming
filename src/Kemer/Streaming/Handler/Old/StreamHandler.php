<?php
namespace Kemer\Stream\Handler;

use Psr\Http\Message\StreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;

class StreamHandler
{
    private $contentType = "application/octet-stream";
    private $stream;
    private $buffer = 102400;
    private $start  = -1;
    private $end    = -1;
    private $size   = 0;
    private $conn;

    public function __construct(StreamInterface $stream)
    {
        $this->stream = $stream;
        if ($contentType = $stream->getMetadata("content-type")) {
            $this->contentType = $contentType;
        }
    }

    public function handle(Request $request, StreamInterface $conn)
    {
        $accept = $request->getHeader("accept");
        var_dump($accept->getPrioritized());

        $iAccept = [$this->getContentType(), "*"];
        $notAccept = ["text/html", "application/xhtml+xml", "application/xml"];
        $allowed = true;
        foreach ($accept->getPrioritized() as $type) {
            if (in_array($type->getTypeString(), $notAccept)) {
                var_dump(["disallowed" => $type->getTypeString()]);
                $allowed = false;
            }
        }
        if ($allowed) {
            foreach ($accept->getPrioritized() as $type) {
                if (in_array($type->getTypeString(), $iAccept)) {
                    var_dump(["allowed" => $type->getTypeString()]);
                    $allowed = true;
                }
            }
        }
        if (!$allowed) {
            $response = new Response();
            $response->setStatusCode(Response::STATUS_CODE_406);
            $response->getHeaders()->addHeaders([
                'Content-Type' => $this->getContentType()
            ]);
            $conn->write($response->toString());
            echo $response;
            return;
        }
        $request = new Request();
        $request->setUri($this->stream->getMetadata("uri"));
        $request->setMethod('GET');
        $this->stream->write($request->toString());
        $response = $this->getResponse($request);
        $conn->write($response->toString());
        $head = $this->stream->read($this->buffer);
        list(, $content) = explode("\r\n\r\n", $head);
        $conn->write($content);
        while (false !== ($data = $this->stream->read($this->buffer))) {
            $conn->write($data);
        }

        return $response;
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function getResponse(Request $request)
    {
        $response = new Response();
        $response->getHeaders()->addHeaders([
            'Content-Type' => $this->getContentType(),
            "Cache-Control" => "no-cache",
        ]);
        $response->setStatusCode(Response::STATUS_CODE_200);

        return $response;
    }
}
