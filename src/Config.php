<?php
namespace Redocn\Analytics;

/**
 * 这就是一个配置类,用来填写一些固定的配置信息的
 * Class Config
 * @package Redocn\Analytics
 */

class Config {

    use \Redocn\Base\Factory;

    protected $config   = [
        'api_key'   => 'analytics',
        'api_host'  => 'http://www.analytics.com/',

        'charset'   => 'GBK' //如果要以GBK的方式交互
    ];

    /**
     * @param string $key
     * @return mixed
     */
    public function get($key) {
        return $this->config[$key];
    }
}