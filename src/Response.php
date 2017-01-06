<?php
namespace Redocn\Analytics;

/**
 * 对API输出的结果进行处理
 * Class Response
 * @package Redocn\Analytics
 */
class Response {

    protected $_data;

    public function __construct(array $data) {
        $this->_data = $data;
    }

    /**
     * 取得一个列表
     * @param \Result\ARow $itemRow
     * @return array
     */
    public function get(\Result\ARow $itemRow = NULL) {
        $items = $this->_data['data']['items'];
        if ($itemRow !== NULL) {
            foreach ($items as $_key => $_item) {
                $item_row       = clone $itemRow; //原型模式
                $items[ $_key ] = $item_row->setItem($_item);
            }
        }
        return $items;
    }

    /**
     * 获取总记录数
     * @return array
     */
    public function total() {
        return $this->_data['data']['total'];
    }

    /**
     * 获取当前页的记录数量
     * @return int
     */
    public function count() {
        return $this->_data['data']['count'];
    }

    /**
     * @param \Result\ARow $_item
     * @return array|\Result\Arow}|NULL 一行数据
     */
    public function first(\Result\ARow $_item = NULL) {
        if (sizeof($this->_data['data']['count']) > 0) {
            if ($_item !== NULL) {
                $new_item_row = clone $_item;
                return $new_item_row->setItem(array_shift($this->_data['data']['items']));
            } else {
                return array_shift($this->_data['data']['items']);
            }
        } else {
            return NULL;
        }
    }
}