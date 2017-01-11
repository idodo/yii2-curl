<?php
namespace lspbupt\curl;
use Yii;
use \yii\helpers\ArrayHelper;
use \yii\base\Component;
use \yii\base\InvalidParamException;
use Closure;
/*encapsulate normal Http Request*/
class BaseCurlHttp extends Component
{
    const METHOD_GET = 0;
    const METHOD_POST = 1;
    const METHOD_POSTJSON = 2;

    public $timeout = 10;
    public $connectTimeout = 5;
    public $protocol = "http";
    public $port = 80;
    public $host;
    public $method = self::METHOD_GET;
    public $headers = array(
        'User-Agent' => 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.22 (KHTML, like Gecko) Ubuntu Chromium/25.0.1',
        'Accept-Charset' => 'GBK,utf-8' ,
    );
    public $action;
    public $params;
    private $debug = false;

    private static $methodDesc = [
        self::METHOD_GET => "GET",
        self::METHOD_POST => "POST",
        self::METHOD_POSTJSON => "POST",
    ];

    private $_curl;

    public function init()
    {
        parent::init();
        if(empty($this->host)) {
            throw new InvalidParamException("Please config host.");
        }
    }

    public function getUrl()
    {
        $url = $this->protocol."://".$this->host;
        if($this->port != 80) {
            $url .= ":".$this->port;
        }
        return $url.$this->getAction();
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function setParams($params = [])
    {
        $this->params = $params;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    public function setGet()
    {
        if(!empty($this->headers['Content-Type'])) {
            unset($this->headers["Content-Type"]);
        }
        return $this->setMethod(self::METHOD_GET);
    }

    public function getMethod()
    {
        if(isset(self::$methodDesc[$this->method])) {
            return self::$methodDesc[$this->method];
        }
        return "GET";
    }

    public function setPost()
    {
        if(!empty($this->headers['Content-Type'])) {
            unset($this->headers["Content-Type"]);
        }
        return $this->setMethod(self::METHOD_POST);
    }

    public function setPostJson()
    {
        $this->setHeader('Content-Type', 'application/json;charset=utf-8');
        return $this->setMethod(self::METHOD_POSTJSON);
    }


    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
        return $this;
    }

    public function setHeader($key, $value)
    {
        $this->headers[$key] = $value;
        return $this;
    }

    private function getHeads()
    {
        $heads = [];
        foreach($this->headers as $key => $val) {
            $heads[] = $key.":".$val;
        }
        return $heads;
    }

    public function getCurl()
    {
        if($this->_curl) {
            return $this->_curl;
        }
        $this->_curl = curl_init();
        return $this->_curl;
    }

    public function setDebug($debug = true)
    {
        $this->debug = $debug;
        return $this;
    }

    public function isDebug()
    {
        return $this->debug;
    } 


    //请求之前的操作
    protected function beforeCurl($params)
    {
        return true;
    }

    //请求之后的操作
    protected function afterCurl($data)
    {
        return $data;
    }

    public function httpExec($action = "/", $params = [])
    {
        $this->setAction($action);
        $this->setParams($params);
        if($this->isDebug()) {
            echo "\n开始请求之前:\nurl:".$this->getUrl()."\n参数列表:".json_encode($this->getParams())."\n方法:".$this->getMethod()."\n";
        }
        $ret = $this->beforeCurl($params);
        if(!$ret) {
            return ""; 
        }
        $ch = $this->getCurl();
        $url = $this->getUrl();
        if ($this->method == self::METHOD_POST) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($this->getParams()));
        } elseif ($this->method == self::METHOD_POSTJSON) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($this->getParams()));
        } else {
            if(!empty($params)) {
                $temp = explode("?", $url);
                if(count($temp) > 1) {
                    $url = $temp[0]."?".$temp[1].'&'.http_build_query($this->getParams());
                } else {
                    $url = $url."?".http_build_query($this->getParams());
                }
            }
        }
        if($this->isDebug()) {
            echo "\n开始请求:\nurl:${url}\n参数列表:".json_encode($this->getParams())."\n方法:".$this->getMethod()."\n";
        }
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER , $this->getHeads());
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $data = curl_exec($ch);
        if($this->isDebug()) {
            echo "\n请求结果:".$data."\n";
        }
        $data = $this->afterCurl($data);
        curl_close($ch);
        $this->_curl = null;
        return $data;
    }

    public static function requestByUrl($url, $params = [], $method=self::METHOD_GET)
    {
        $data = parse_url($url);
        $config = [];
        $config['protocol'] = ArrayHelper::getValue($data, "scheme", "http");
        $config['host'] = ArrayHelper::getValue($data, "host", "");
        $config['port'] = ArrayHelper::getValue($data, "port", 80);
        $config['method'] = $method;
        $action = ArrayHelper::getValue($data, "path", "");
        $queryStr = ArrayHelper::getValue($data, "query", "");
        $fragment = ArrayHelper::getValue($data, "fragment", "");
        if($queryStr) {
            $action .= "?".$queryStr;
        }
        if($fragment) {
            $action .= "?".$fragment;
        }
        $config['class'] = get_called_class();
        $obj = Yii::createObject($config);
        return $obj->httpExec($action, $params);
    }

}