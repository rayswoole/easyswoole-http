<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2018/5/24
 * Time: 下午3:40
 */

namespace EasySwoole\Http;


use EasySwoole\Http\Message\ServerRequest;
use EasySwoole\Http\Message\Stream;
use EasySwoole\Http\Message\UploadFile;
use EasySwoole\Http\Message\Uri;
use Psr\Http\Message\StreamInterface;

class Request extends ServerRequest
{
    private $request;
    private $body;

    function __construct(\Swoole\Http\Request $request = null)
    {
        if($request){
            $this->request = $request;
            $this->initHeaders();
            $protocol = str_replace('HTTP/', '', $request->server['server_protocol']) ;
            $uri = $this->initUri();
            $files = $this->initFiles();
            $method = $request->server['request_method'];
            //body传值为null，通过getBody直接返回
            parent::__construct($method, $uri, null, null, $protocol, $request->server);
            $this->withCookieParams($this->initCookie())->withQueryParams($this->initGet())->withParsedBody($this->initPost())->withUploadedFiles($files);
        }
    }

    function getRequestParam(...$key)
    {
        $data = $this->getParsedBody() + $this->getQueryParams();
        if(empty($key)){
            return $data;
        }else{
            $res = [];
            foreach ($key as $item){
                $res[$item] = isset($data[$item])? $data[$item] : null;
            }
            if(count($key) == 1){
                return array_shift($res);
            }else{
                return $res;
            }
        }
    }

    function getSwooleRequest()
    {
        return $this->request;
    }

    public function getBody()
    {
        if ($this->body == null) {
            $this->body = new Stream($this->request->fd ? $this->request->rawContent() : '');
        }
        return $this->body;
    }

    public function withBody(StreamInterface $body)
    {
        $this->body = $body;
        return $this;
    }

    private function initUri()
    {
        $uri = new Uri();
        if (isset($this->request->header) && isset($this->request->header['scheme']) && in_array($this->request->header['scheme'], ['http','https'])) {
            $uri->withScheme($this->request->header['scheme']);
        } else {
            $uri->withScheme("http");
        }
        $uri->withPath($this->request->server['path_info']);
        $uri->withQuery($this->request->server['query_string'] ?? '');
        //host与port以header为准，防止经过proxy
        if(isset($this->request->header['host'])){
            $host = $this->request->header['host'];
            $host = explode(":",$host);
            $realHost = $host[0];
            $port = isset($host[1]) ? $host[1] : null;
        }else{
            $realHost = '127.0.0.1';
            $port = $this->request->server['server_port'];
        }
        $uri->withHost($realHost);
        $uri->withPort($port);
        return $uri;
    }

    private function initHeaders()
    {
        $headers = isset($this->request->header) ? $this->request->header :[];
        foreach ($headers as $header => $val){
            $this->withAddedHeader($header,$val);
        }
    }

    private function initFiles()
    {
        if(isset($this->request->files)){
            $normalized = array();
            foreach($this->request->files as $key => $value){
                //如果是二维数组文件
                if(is_array($value) && empty($value['tmp_name'])){
                    $normalized[$key] = [];
                    foreach($value as $file){
                        if (empty($file['tmp_name'])){
                            continue;
                        }
                        $file = $this->initFile($file);
                        if($file){
                            $normalized[$key][] = $file;
                        }
                    }
                    continue;
                }else{
                    $file = $this->initFile($value);
                    if($file){
                        $normalized[$key] = $file;
                    }
                }
            }
            return $normalized;
        }else{
            return array();
        }
    }

    private function initFile(array $file)
    {
        if(empty($file['tmp_name'])){
            return null;
        }
        return new UploadFile(
            $file['tmp_name'],
            (int) $file['size'],
            (int) $file['error'],
            $file['name'],
            $file['type']
        );
    }

    private function initCookie()
    {
        return isset($this->request->cookie) ? $this->request->cookie : [];
    }

    private function initPost()
    {
        return isset($this->request->post) ? $this->request->post : [];
    }

    private function initGet()
    {
        return isset($this->request->get) ? $this->request->get : [];
    }

    final public function __toString():string
    {
        return Utility::toString($this);
    }

    public function __destruct()
    {
        $this->getBody()->close();
        $this->request = null;
    }

}
