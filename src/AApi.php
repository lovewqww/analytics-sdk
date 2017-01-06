<?php

/**
 * 这个包不是给analytics用的，而是给客户端（例如：红动网/图片114用的）
 */

namespace Redocn\Analytics;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

abstract class AApi {

    private static   $_urlContentCaches = array(); //URL请求的缓存
    protected $_lastUrl; //记录上次访问的URL

    /**
     * @var \Redocn\Analytics\Result\AItem
     */
    protected $_item;

    /**
     * 请求API
     * @param string $uri API的URI部分，例如：book_items，book_row或chapter_items等
     * @param array $params
     * @return Response 返回JSON格式的数据
     * @throws \Redocn\Analytics\Exception
     */
    public function requestApi($uri, $params = array()) {

        $config         = \Redocn\Analytics\Config::signle();
        $api_charset    = 'UTF-8'; //分析系统使用的是UTF-8,这里不要改动,如果zAdmin将来换成了utf-8,直接去Config里修改编码为utf-8即可

        if (strtoupper($config->get("charset")) !== $api_charset) { //如果当前系统不是UTF-8,则转码为UTF-8,因为分析系统是UTF-8的
            $params = array_map_recursive(function($_item) use ($config, $api_charset) {
                return mb_convert_encoding($_item, $api_charset, $config->get("charset"));
            }, $params);
        }

        $params['api_key']	= $config->get('api_key');
        $url				= rtrim($config->get('api_host'), '/').'/'.ltrim($uri, '/').'.htm?'.http_build_query($params);

        $opts				= array(CURLOPT_TIMEOUT => 300); //超时时间为5分钟
        $content            = $this->getContentByUrl($url, '', '', $opts);
        $data				= $this->jsonEncode($content, TRUE);
        if (strtoupper($config->get("charset")) !== $api_charset) { //如果当前系统不是UTF-8,则转码为UTF-8,因为分析系统是UTF-8的
            $data = array_map_recursive(function ($_item) use ($config, $api_charset) {
                if (preg_match("/^[\x{4e00}-\x{9fa5}A-Za-z0-9_]+$/u",$_item)) { //如果返回出来的还是UTF-8的字符,则转换
                    return mb_convert_encoding($_item, $config->get("charset"), $api_charset);
                } else {
                    return $_item;
                }
            }, $data);
        }
        if ((int)$data['status'] !== 200) { //如果发生错误，则报错
            throw new \Redocn\Analytics\Exception($data['message']."，URL: {$url}", $data['code']);
        } else {
            return new Response($data);
        }
    }

    /**
     * @return array
     */
    protected function jsonEncode($data) {
        return json_decode($data, TRUE);
    }

    /**
     * 获取指定URL的内容，并对内容进行转码
     * @param string $url 网址
     * @param string $from_charset 原编码
     * @param string $to_charset 需要转换成什么编码
     * @param array $opts curl的options
     * @param int $interval 每次请求的间隔"秒"数
     * @return string 网页内容
     * @throws \Redocn\Analytics\Exception
     */
    public function getContentByUrl($url, $from_charset = '', $to_charset = '', $opts = array(), $interval = 0) {
        $key = md5($url);
        if (!isset(self::$_urlContentCaches[$key])) { //如果这个URL没有被获取，则跳出
            if (is_numeric($interval) && $interval > 0) { //两次请求的间隔时间
                sleep($interval);
            }
            try {
                $content = request_follow($url, $opts);
            } catch (\Exception $e) {
                throw new \Redocn\Analytics\Exception($e->getMessage(), $e->getCode());
            }
            /* 如果$from_charset不等于$to_charset，并且它们都不为空，则转换编码 */
            if ($from_charset != '' && $to_charset != '' && strtolower($from_charset) != strtolower($to_charset)) {
                $content = $this->strEncoding($content, $from_charset, $to_charset);
            }
            if (empty($content)) throw new \Redocn\Analytics\Exception('HTTP请求成功，但没有获取到内容，可能目标网页的内容本身就是空的');
            $this->_lastUrl = $url;
            self::$_urlContentCaches[$key] = $content;

        }
        return self::$_urlContentCaches[$key];
    }

    /**
     * 对字符串进行转码
     * @param string $str 需要转码的字符串
     * @param string $from 从XXX编码
     * @param string $to 转换到XXX编码
     * @return string
     * @throws Exception
     */

    public function strEncoding($str, $from, $to) {
        if ($from == $to) { //如果两个编码是相同的，则不需要转码
            return $str;
        } else {
            if (function_exists('mb_convert_encoding')) {
                return mb_convert_encoding($str, $to, $from);
            } else if (function_exists('iconv')) {
                return iconv($from, $to, $str);
            } else { //如果两个转码函数都不能用，则提示没有可用于转码的PHP函数的错误
                throw new \Redocn\Analytics\Exception('encoding error: No PHP available transcoding function');
            }
        }
    }

    /**
     * 取得当前请求的URL
     * @return string
     */
    public function getRequestUrl() {
        return $this->_lastUrl;
    }

    /**
     * 释放内存（用来释放getContentByUrl方法里缓存的HTTP请求后的内容）
     * @param string $url 如果$url为all则清除所有的缓存，否则清除指定URL的缓存
     * @return void
     */
    public static function sClearHttpContentCache($url = 'all') {
        if ($url === 'all') {
            self::$_urlContentCaches = array();
        } else {
            $key = md5($url);
            if (isset(self::$_urlContentCaches[$key])) { //如果key存在，则删除掉指定的缓存
                unset(self::$_urlContentCaches[$key]);
            }
        }
    }
}

if (!function_exists("array_map_recursive")) {
    function array_map_recursive($filter, $data) {
        $result = array();
        foreach ($data as $key => $val)
        {
            $result[$key] = is_array($val)
                ? array_map_recursive($filter, $val)
                : call_user_func($filter, $val);
        }

        return $result;
    }
}
/**
 * 请求URL，并自行处理302或301
 */
function request_follow($url, $opts = array()) {
    if (trim($url) == '') {
        throw new Exception('url can not be empty');
    }
    $user_agents = array(
        'ie6' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)',
        'ie7' => 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.2)',
        'ie8' => 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.0)',
        'firefox1' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1) Gecko/20070803 Firefox/1.5.0.12',
        'firefox2' => '(Windows; U; Windows NT 5.1) Gecko/20070309 Firefox/2.0.0.3',
        'firefox3' => 'Mozilla/5.0 (Windows; U; Windows NT 5.2) Gecko/2008070208 Firefox/3.0.1'
        #'chrome' => 'Mozilla/5.0 (Windows; U; Windows NT 5.2) AppleWebKit/525.13 (KHTML, like Gecko) Chrome/0.2.149.27 Safari/525.13',
        #'google' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
        #'soso' => 'Sosospider+(+http://help.soso.com/webspider.htm)',
        #'bing' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)',
        #'sogou' => 'Sogou web spider/4.0(+http://www.sogou.com/docs/help/webmasters.htm#07)'
    );

    if (!isset($opts[CURLOPT_USERAGENT])) {
        $opts[ CURLOPT_USERAGENT ] = $user_agents[ array_rand($user_agents) ]; //取得随机的User-agent
    }
    $opts[CURLOPT_HTTPHEADER] = array('Accept-Encoding: gzip,deflate');
    $opts[CURLOPT_ENCODING]	  = 'gzip,deflate';
    if (!isset($opts[CURLOPT_TIMEOUT])) $opts[CURLOPT_TIMEOUT] = 15; //设置超时时间为5秒，如果这里不设置，很容易造成php-cgi进程被长时间占用，造成阻塞
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if (is_array($opts) && sizeof($opts) > 0) {
        foreach ($opts as $key => $val) {
            curl_setopt($ch, $key, $val);
        }
    }
    try {
        $data = curl_redir_exec($ch, $url);
        curl_close($ch);
        $_data = cs_gzdecode($data);
        if ($_data !== NULL) $data = $_data;
        return $data;
    } catch (Exception $e) {
        throw new Exception($e->getMessage(), $e->getCode());
    }
}

function curl_redir_exec($ch, $url) {
    static $curl_loops = 0;
    static $curl_max_loops = 3;
    if ($curl_loops++ >= $curl_max_loops) {
        $curl_loops = 0;
        return FALSE;
    }
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $data = curl_exec($ch);
    if (strpos($data, "\r\n\r\n") !== FALSE) {
        list($header, $data) = explode("\r\n\r\n", $data, 2);
    } else {
        //这里的getCode都是100以下的数字
        throw new Exception("Failed to open the page: ".$url." failure, Error message: ".curl_error($ch), curl_errno($ch));
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code >= 400)
        throw new Exception('Failed to open the page: ' . $http_code . '，URL：' . $url . ' ' . __FUNCTION__, $http_code);
    if ($http_code == 301 || $http_code == 302) {
        $matches = array();
        preg_match('/Location:(.*?)\n/', $header, $matches);
        $url = @parse_url(trim(array_pop($matches)));
        if (!$url) {
            //couldn't process the url to redirect to
            $curl_loops = 0;
            return $data;
        }
        $last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
        if (!isset($url['scheme']))
            $url['scheme'] = $last_url['scheme'];
        if (!isset($url['host']))
            $url['host'] = $last_url['host'];
        if (!isset($url['path']))
            $url['path'] = ''; //$url['path'] = $last_url['path'];

        $new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . (isset($url['query']) ? '?' . $url['query'] : '');
        curl_setopt($ch, CURLOPT_URL, $new_url);
        return curl_redir_exec($ch, $new_url);
    } else {
        $curl_loops = 0;
        return $data;
    }
}

/**
 * 对GZIP压缩的文本进行解压
 * @return mixed 如果不是GZIP文件，则返回null，否则返回解压后的字符
 */
if (!function_exists('cs_gzdecode')) {

    function cs_gzdecode($data, &$filename = '', &$error = '', $maxlength = null) {
        $len = strlen($data);
        if ($len < 18 || strcmp(substr($data, 0, 2), "\x1f\x8b")) {
            $error = "Not in GZIP format.";
            return null;  // Not GZIP format (See RFC 1952)
        }
        $method = ord(substr($data, 2, 1));  // Compression method
        $flags = ord(substr($data, 3, 1));  // Flags
        if ($flags & 31 != $flags) {
            $error = "Reserved bits not allowed.";
            return null;
        }
        // NOTE: $mtime may be negative (PHP integer limitations)
        $mtime = unpack("V", substr($data, 4, 4));
        $mtime = $mtime[1];
        $xfl = substr($data, 8, 1);
        $os = substr($data, 8, 1);
        $headerlen = 10;
        $extralen = 0;
        $extra = "";
        if ($flags & 4) {
            // 2-byte length prefixed EXTRA data in header
            if ($len - $headerlen - 2 < 8) {
                return false;  // invalid
            }
            $extralen = unpack("v", substr($data, 8, 2));
            $extralen = $extralen[1];
            if ($len - $headerlen - 2 - $extralen < 8) {
                return false;  // invalid
            }
            $extra = substr($data, 10, $extralen);
            $headerlen += 2 + $extralen;
        }
        $filenamelen = 0;
        $filename = "";
        if ($flags & 8) {
            // C-style string
            if ($len - $headerlen - 1 < 8) {
                return false; // invalid
            }
            $filenamelen = strpos(substr($data, $headerlen), chr(0));
            if ($filenamelen === false || $len - $headerlen - $filenamelen - 1 < 8) {
                return false; // invalid
            }
            $filename = substr($data, $headerlen, $filenamelen);
            $headerlen += $filenamelen + 1;
        }
        $commentlen = 0;
        $comment = "";
        if ($flags & 16) {
            // C-style string COMMENT data in header
            if ($len - $headerlen - 1 < 8) {
                return false; // invalid
            }
            $commentlen = strpos(substr($data, $headerlen), chr(0));
            if ($commentlen === false || $len - $headerlen - $commentlen - 1 < 8) {
                return false; // Invalid header format
            }
            $comment = substr($data, $headerlen, $commentlen);
            $headerlen += $commentlen + 1;
        }
        $headercrc = "";
        if ($flags & 2) {
            // 2-bytes (lowest order) of CRC32 on header present
            if ($len - $headerlen - 2 < 8) {
                return false; // invalid
            }
            $calccrc = crc32(substr($data, 0, $headerlen)) & 0xffff;
            $headercrc = unpack("v", substr($data, $headerlen, 2));
            $headercrc = $headercrc[1];
            if ($headercrc != $calccrc) {
                $error = "Header checksum failed.";
                return false; // Bad header CRC
            }
            $headerlen += 2;
        }
        // GZIP FOOTER
        $datacrc = unpack("V", substr($data, -8, 4));
        $datacrc = sprintf('%u', $datacrc[1] & 0xFFFFFFFF);
        $isize = unpack("V", substr($data, -4));
        $isize = $isize[1];
        // decompression:
        $bodylen = $len - $headerlen - 8;
        if ($bodylen < 1) {
            // IMPLEMENTATION BUG!
            return null;
        }
        $body = substr($data, $headerlen, $bodylen);
        $data = "";
        if ($bodylen > 0) {
            switch ($method) {
                case 8:
                    // Currently the only supported compression method:
                    $data = gzinflate($body, $maxlength);
                    break;
                default:
                    $error = "Unknown compression method.";
                    return false;
            }
        }  // zero-byte body content is allowed
        // Verifiy CRC32
        $crc = sprintf("%u", crc32($data));
        $crcOK = $crc == $datacrc;
        $lenOK = $isize == strlen($data);
        if (!$lenOK || !$crcOK) {
            $error = ( $lenOK ? '' : 'Length check FAILED. ') . ( $crcOK ? '' : 'Checksum FAILED.');
            return false;
        }
        return $data;
    }

}