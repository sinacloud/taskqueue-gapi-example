<?php
/**
 * SAE TaskQueue服务 [公网接口]
 *
 * @package sae
 * @version $Id$
 * @author Lazypeople
 */

/**
 * SAE TaskQueuePublic<br />
 * 任务队列服务
 *
 * <code>
 * <?php
 * $queue = new SaeTaskQueuePublic('test');
 * $queue->set('appName', 'accessKey', 'secretKey', 'appVersion');
 *
 * //添加单个任务
 * $queue->addTask("/page1.php");
 * $queue->addTask("/page2.php", "key=value", true);
 *
 * //批量添加任务
 * $array = array();
 * $array[] = array('url'=>"/page3.php", "postdata"=>"act=test");
 * $array[] = array('url'=>"/page4.php", "postdata"=>"act=test", "prior"=>true);
 * $queue->addTask($array);
 *
 * //将任务推入队列
 * $ret = $queue->push();
 *
 * //任务添加失败时输出错误码和错误信息
 * if ($ret === false)
 *        var_dump($queue->errno(), $queue->errmsg());
 * ?>
 * </code>
 *
 * 错误码参考：
 *  - errno: 0        成功
 *  - errno: 1        认证失败
 *  - errno: 3        参数错误
 *  - errno: 10        队列不存在
 *  - errno: 11        队列已满或剩余长度不足
 *  - errno: 500    服务内部错误
 *  - errno: 999    未知错误
 *  - errno: 403    权限不足或超出配额
 *
 * @package sae
 * @author lazypeople
 *
 */
class SaeTaskQueuePublic
{
    private $_appname = "";
    private $_accesskey = "";
    private $_secretkey = "";
    private $_appversion = false;
    private $_errno = 0;
    private $_errmsg = "OK";
    private $_post = array();
    private $_queue_name = '';
    private $_act = '';
    private $_backend_url = 'http://g.sinacloud.com';

    /**
     * @ignore
     */
    const post_limitsize = 8388608;

    /**
     * 构造对象
     *
     * @param string $queue_name 队列名称
     */
    function __construct($queue_name)
    {
        $this->_queue_name = $queue_name;
        $this->_post['name'] = $queue_name;
        $this->_post['queue'] = array();
    }

    /**
     * 添加任务
     *
     * @param string|array $tasks 任务要访问的URL或以数组方式传递的多条任务。添加多条任务时的数组格式：
     * <code>
     * <?php
     * $tasks = array( array("url" => "/test.php", //只支持相对URL，且"/"开头
     *                       "postdata" => "data", //要POST的数据。可选
     *                       "prior" => false,  //是否优先执行，默认为false，如果设为true，则将此任务插入到队列最前面。可选
     *                       "options" => array('key1' => 'value1', ....),  //附加参数，可选。
     * ), ................);
     * ?>
     * </code>
     * @param string $postdata 要POST的数据。可选，且仅当$tasks为URL时有效
     * @param bool prior 是否优先执行，默认为false，如果设为true，则将此任务插入到队列最前面。可选，且仅当$tasks为URL时有效
     * @param array options 附加参数，可选，且仅当$tasks为URL时有效。目前支持的参数：
     *  - delay, 延时执行，单位秒，最大延时600秒。
     * @return bool
     * @author Elmer Zhang
     */
    function addTask($tasks, $postdata = NULL, $prior = false, $options = array())
    {
        if (is_string($tasks)) {
            if (!$this->checkTaskUrl($tasks)) {
                $this->_errno = 1;
                $this->_errmsg = "Unavailable tasks";
                return false;
            }

            //添加单条任务
            $item = array();
            $item['url'] = $tasks;
            if ($postdata != NULL) $item['postdata'] = base64_encode($postdata);
            if ($prior) $item['prior'] = true;
            $this->setOptions($item, $options);
            $this->_post['queue'][] = $item;

        } elseif (is_array($tasks)) {
            if (empty($tasks)) {
                $this->_errno = 1;
                $this->_errmsg = "Unavailable tasks";
                return false;
            }

            //添加多条任务
            foreach ($tasks as $k => $v) {
                if (is_array($v) && isset($v['url']) && $this->checkTaskUrl($v['url'])) {
                    if (isset($v['postdata'])) {
                        $v['postdata'] = base64_encode($v['postdata']);
                    }
                    if (isset($v['options'])) {
                        $this->setOptions($v, $v['options']);
                        unset($v['options']);
                    }
                    $this->_post['queue'][] = $v;
                } elseif (isset($tasks['url']) && $this->checkTaskUrl($tasks['url'])) {
                    if (isset($tasks['postdata'])) {
                        $tasks['postdata'] = base64_encode($tasks['postdata']);
                    }
                    if (isset($tasks['options'])) {
                        $this->setOptions($tasks, $tasks['options']);
                        unset($tasks['options']);
                    }
                    $this->_post['queue'][] = $tasks;
                    break;
                } else {
                    $this->_post['queue'] = array();
                    $this->_errno = 1;
                    $this->_errmsg = "Unavailable tasks";
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * 取得错误码
     *
     * @return int
     * @author Elmer Zhang
     */
    public function errno()
    {
        return $this->_errno;
    }

    /**
     * 取得错误信息
     *
     * @return string
     * @author Elmer Zhang
     */
    public function errmsg()
    {
        return $this->_errmsg;
    }

    /**
     * 设置应用的授权信息
     * @param $appname , 应用名
     * @param $accesskey , 应用的accessKey
     * @param $secretkey , 应用的secretKey
     * @param bool $appversion , 应有的版本
     * @return bool
     */
    public function setAuth($appname, $accesskey, $secretkey, $appversion = false)
    {
        $this->_appname = trim($appname);
        $accesskey = trim($accesskey);
        $secretkey = trim($secretkey);
        $this->_accesskey = $accesskey;
        $this->_secretkey = $secretkey;
        $this->_appversion = trim($appversion);
        return true;
    }

    /**
     * 将任务列表推入队列
     *
     * @return bool
     * @author Elmer Zhang
     */
    public function push()
    {
        $post = json_encode($this->_post);
        if (strlen($post) > self::post_limitsize) {
            $this->_errno = 1;
            $this->_errmsg = "The post data is too large.";
            return false;
        }
        if (count($this->_post['queue']) > 0) {
            $this->_post['queue'] = array();
            return $this->postData(array("taskqueue" => $post));
        } else {
            $this->_errno = 1;
            $this->_errmsg = "The queue is empty.";
            return false;
        }
    }

    /**
     * 查询队列剩余长度（可再添加的任务数）
     *
     * @return int
     * @author Elmer Zhang
     */
    function leftLength()
    {
        $this->_act = 'leftlen';
        return $this->send();
    }

    /**
     * 查询队列当前长度（剩余未执行的任务数）
     *
     * @return int
     * @author Elmer Zhang
     */
    function curLength()
    {
        $this->_act = 'curlen';

        return $this->send();
    }

    /**
     * @author Elmer Zhang
     */
    private function send()
    {
        $post = urlencode(json_encode($this->_post));
        if ($post) {
            return $this->postData(array("params" => $post, "act" => $this->_act));
        } else {
            return false;
        }
    }

    private function post($uri, $post_data = array())
    {
        if (!$uri) {
            return false;
        }
        return $this->_curl($uri, $post_data, 'POST');
    }

    private function _cal_sign_and_set_header($uri, $method = 'GET')
    {
        $a = array();
        $a[] = $method;
        $a[] = $uri;
        // $timeline unix timestamp
        $timeline = time();
        $b = array('x-sae-accesskey' => $this->_accesskey, 'x-sae-timestamp' => $timeline);
        ksort($b);
        foreach ($b as $key => $value) {
            $a[] = sprintf("%s:%s", $key, $value);
        }
        $str = implode("\n", $a);
        $s = hash_hmac('sha256', $str, $this->_secretkey, true);
        $b64_s = base64_encode($s);
        $headers = array();
        $headers[] = sprintf('x-sae-accesskey:%s', $this->_accesskey);
        $headers[] = sprintf('x-sae-timestamp:%s', $timeline);
        $headers[] = sprintf('Authorization: SAEV1_HMAC_SHA256 %s', $b64_s);
        return $headers;
    }

    private function _curl($uri, $post_data = array(), $method = 'GET')
    {
        $ch = curl_init();
        $url = sprintf('%s%s', $this->_backend_url, $uri);
        curl_setopt($ch, CURLOPT_URL, $url);
        $headers = $this->_cal_sign_and_set_header($uri, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if ($post_data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        }
        $ret = curl_exec($ch);
        $error = curl_errno($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        if (empty($info['http_code'])) {
            $this->_errno = 500;
            $this->_errmsg = $error;
        } else if ($info['http_code'] != 200) {
            $this->_errno = 500;
            $code = $info['http_code'];
            $this->_errmsg = "Taskqueue service internal error, http code isn't 200, http code: $code";
        } else {
            if ($info['size_download'] == 0) {
                $header = substr($ret, 0, $info['header_size']);
                $task_header = $this->extractCustomHeader("TaskQueueError", $header);
                if ($task_header == false) { // not found MailError header
                    $this->_errno = 501;
                    $this->_errmsg = "unknown error";
                } else {
                    $err = explode(",", $task_header, 2);
                    $this->_errno = trim($err[0]);
                    $this->_errmsg = trim($err[1]);
                }
            } else {
                $body = substr($ret, -$info['size_download']);
                $body = json_decode(trim($body), true);
                $this->_errno = $body['errno'];
                $this->_errmsg = $body['errmsg'];
                if ($body['errno'] == 0) {
                    if (isset($body['data'])) {
                        return $body['data'];
                    } else {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function postData($post)
    {
        return $this->post('/taskqueue/index.php', $post);
    }

    private function extractCustomHeader($key, $header)
    {
        $pattern = '/' . $key . ':(.*?)' . "\n/";
        if (preg_match($pattern, $header, $result)) {
            return $result[1];
        } else {
            return false;
        }
    }

    private function setOptions(&$item, $options)
    {
        if (is_array($options) && !empty($options)) {
            foreach ($options as $k => $v) {
                switch ($k) {
                    case 'delay':
                        $item['delay'] = intval($v);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    private function checkTaskUrl(&$url)
    {
        if (substr($url, 0, 1) === '/') {
            if ($this->_appversion) {
                $url = sprintf('http://%s.%s.applinzi.com%s', $this->_appversion, $this->_appname, $url);
            } else {
                $url = sprintf('http://%s.applinzi.com%s', $this->_appname, $url);
            }
        }
        $url = filter_var($url, FILTER_VALIDATE_URL);
        return $url;
    }
}
