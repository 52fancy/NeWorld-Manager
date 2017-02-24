<?php
// 声明命名空间
namespace NeWorld;

// 引入文件
require_once __DIR__.'/NeWorld.Common.Class.php';

// 使用其他的类
use \Exception;

// 模块类
if (!class_exists('Addons'))
{
    class Addons
    {
        // 模块属性
        private $name;
        private $database;
        private $extended;
        public $license;

        // 构造方法
        public function __construct($name = '')
        {
            // 把模块（目录）名赋值给属性
            $this->name = (string) $name;

            // 判断是否为空
            if (empty($this->name)) throw new Exception('传递的产品名称为空，请检查模块设置');

            // 实例化数据库类
            $this->database = new Database;

            // 查询数据库中是否有当前模块的信息
            $getData = $this->database->runSQL([
                'action' => [
                    'check' => [
                        'sql' => 'SELECT id FROM NeWorldCache WHERE name = ?',
                        'pre' => [$this->name],
                    ],
                ],
                'trans' => false,
            ]);

            // 判断结果是否为空
            if (empty($getData['check']['result'])) throw new Exception('当前产品未在 NeWorld Manager 中激活，请检查设置');

            // 查看产品的授权信息
            $getData = $this->database->runSQL([
                'action' => [
                    'product' => [
                        'sql' => 'SELECT * FROM NeWorldProduct WHERE id = ?',
                        'pre' => [$getData['check']['result']['id']],
                    ],
                ],
                'trans' => false,
            ]);

            // 判断是否为空
            if (empty($getData['product']['result'])) throw new Exception('无法取出当前产品在 NeWorld Manager 的数据，请刷新当前页面重试');

            // 实例化扩展类
            $this->extended = new Extended;

            // 判断哈希值是否匹配
            $checkStatus = $this->extended->verifyHash($getData['product']['result']);

            // 如果 Hash 不匹配就报错
            if (!$checkStatus) throw new Exception('当前产品在本地缓存的授权信息与当前环境特征不匹配，请尝试通过 NeWorld Manager 检测此产品');

            // 把相应的值赋值给属性
            $this->license = (string) $getData['product']['result']['license'];
        }
    }
}