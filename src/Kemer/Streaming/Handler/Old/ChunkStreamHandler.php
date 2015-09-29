<?php
namespace Kemer\Stream\Handler;

use Psr\Http\Message\StreamInterface;
use Zend\Http\Request;
use Zend\Http\Response;
use Zend\Http\PhpEnvironment\Response as PhpResponse;

class ChunkStreamHandler
{
    private $contentType = "application/octet-stream";
    private $stream;
    private $buffer = 10024;
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
        $request = new Request();

        $request->setUri($this->stream->getMetadata("uri"));
        $request->setUri('/movie.mp4');
        $request->getHeaders()->addHeaders([
            'Host' => '10.0.10.107:8090',
        ]);
        $request->setMethod('GET');

        $this->stream->write($request->toString());
        $response = $this->getResponse($request);


        $head = $this->stream->read($this->buffer);
        list(, $chunk) = explode("\r\n\r\n", $head);

        $conn->write($response);
        $hex = dechex(strlen($chunk));
        $conn->write($hex."\r\n".$chunk."\r\n");
        var_dump($head);
        $i = 0;
        while ($i < 10) { $chunk = $this->stream->read($this->buffer);
        //while (false !== ($chunk = $this->stream->read($this->buffer))) {
            $hex = dechex(strlen($chunk));
            $conn->write($hex."\r\n".$chunk."\r\n");
            $i++;
        }
        $conn->write("0\r\n\r\n");


        //return $response;
    }

    public function getContentType()
    {
        return $this->contentType;
    }

    public function getResponse()
    {
        $response = new PhpResponse();
        $response->setStatusCode(Response::STATUS_CODE_200);
        $response->getHeaders()->addHeaders([
            // 'Content-Type' => $this->getContentType(),
            // 'Transfer-Encoding' => 'chunked',
            // 'Accept-Ranges' => 'bytes',
            //"Cache-Control" => "no-cache",

            // 'Accept-Ranges' => 'bytes',


        ]);
        return $response;
    }
}
