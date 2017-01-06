<?php
/**
 * 这个包不是给analytics用的，而是给客户端（例如：红动网/图片114用的）
 */

namespace Redocn\Analytics;

class LogApi extends AApi {

    /**
     * @param array $params
     * @return Response
     * @throws array 多行数据,2维数组
     */
    public function search(array $params) {
        return $this->requestApi('api/log/search', $params);
    }

    /**
     * 使用日志的唯一ID,获取日志的信息
     * @param $log_unique
     * @return array 一行数据
     */
    public function row($log_unique) {
        return $this->requestApi('api/log/row', array('log_unique' => $log_unique))->first();
    }
}