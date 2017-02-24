<?php
// 声明命名空间
namespace NeWorld;

// 引入文件
require_once __DIR__.'/NeWorld.Common.Class.php';

// 使用其他的类
use \Exception;

// 授权检测类
if (!class_exists('License'))
{
    class License
    {
        private $router;    // 路由地址
        private $license;   // 授权许可
        private $extended;  // 扩展类
        private $database;  // 数据库类
        private $encoder;   // 编码类
        private $table;     // 是否存在表
        public $code;       // 检测授权返回的代码

        public function __construct($license, $force = false)
        {
            // 授权许可编号
            $this->license = (string) $license;

            // 判断授权许可是否为空
            if (empty($this->license)) throw new Exception('当前未正确获取到授权许可编号，请尝试刷新当前页面重试');

            // 实例化一个数据库类
            $this->database = new Database;

            // 实例化一个扩展类
            $this->extended = new Extended;

            // 判断是否强制检测
            switch ($force)
            {
                // 如果 $force 是 true 的话强制检测
                case true:
                    // 将 table 属性赋值为 true 告诉后面的联网验证要使用 update
                    $this->table = true;

                    // 执行联网检测
                    $this->verifyOnline();
                    break;
                // 默认不联网检测
                default:
                    // 检测天数
                    $checkDay = 86400 * 3;

                    // Session 中存在有授权返回的代码、并且 Time 与当前时间相隔不超过 3 天才返回授权代码
                    if (!empty($_SESSION['NeWorld'][$this->license]['Code']) && time() - $_SESSION['NeWorld'][$this->license]['Time'] < $checkDay)
                    {
                        // 如果已检测过授权就直接返回授权所获取的代码
                        $this->code = $_SESSION['NeWorld'][$this->license]['Code'];

                        // 返回信息
                        return $_SESSION['NeWorld'][$this->license]['info'];
                    }
                    // 如果不存在 Session 中的代码或者时间超过 3 天，那么就重新检测授权
                    else
                    {
                        // 检测本地授权
                        $getData = $this->database->runSQL([
                            'action' => [
                                'neworld' => [
                                    'sql' => 'SELECT * FROM NeWorldProduct WHERE license = ?',
                                    'pre' => [$this->license],
                                ],
                            ],
                            'trans' => false,
                        ]);

                        // 如果找到的结果是空的
                        if (empty($getData['neworld']['result']))
                        {
                            // 将 table 属性赋值为 false 告诉后面的联网验证要使用 insert
                            $this->table = false;

                            // 执行联网检测
                            $this->verifyOnline();
                        }
                        // 否则验证本地授权
                        else
                        {
                            // 将 table 属性赋值为 true 告诉后面的联网验证要使用 update
                            $this->table = true;

                            // 执行本地授权
                            $checkStatus = $this->extended->verifyHash($getData['neworld']['result']);

                            // 如果 Hash 不匹配就联网检测
                            if (!$checkStatus) $this->verifyOnline();
                        }
                    }
            }
        }

        // 联网检测
        public function verifyOnline()
        {
            // 实例化一个编码类
            $this->encoder = new Encoder('0a34ce21ce04e3a49d95c20e5baf7987');

            // 设置授权路由
            $this->router = (string) $this->extended->getRouter();

            // 处理错误信息
            try
            {
                // 连接授权路由，检查授权
                $checkStatus = $this->extended->getWebPage([
                    'url' => $this->encoder->coding($this->router, false),
                    'post' => [
                        'domain' => $this->extended->getSystemURL(),
                        'license' => $this->license,
                        'path' => ROOTDIR,
                    ],
                    'type' => 'json',
                ]);

                // 判断状态
                switch ($checkStatus['status'])
                {
                    case 'success':
                        // 检查当前 WHMCS 是否已经安装过此产品
                        $checkRepeat = $this->database->runSQL([
                            'action' => [
                                'neworld' => [
                                    'sql' => 'SELECT license FROM NeWorld WHERE id = ?',
                                    'pre' => [$checkStatus['id']],
                                ],
                            ],
                            'trans' => false,
                        ]);

                        // 判断是否有相应的内容
                        if (!empty($checkRepeat['neworld']['result'])) throw new Exception('当前网站已包含与授权许可 [ '.$checkRepeat['neworld']['result']['license'].' ] 相同的服务，无需重复添加');

                        // 更新数据库中 NeWorld Manager 的信息
                        $this->database->runSQL([
                            'action' => [
                                'version' => [
                                    'sql' => "UPDATE NeWorld SET value = ? WHERE setting = 'version'",
                                    'pre' => [$checkStatus['neworld']['version']],
                                ],
                                'notice' => [
                                    'sql' => "UPDATE NeWorld SET value = ? WHERE setting = 'notice'",
                                    'pre' => [json_encode($checkStatus['neworld']['notice'])],
                                ],
                            ],
                        ]);

                        // 把授权许可放进数组
                        $checkStatus['license'] = $this->license;

                        // 把年份和月份放进数组
                        $checkStatus['date'] = time();

                        // 判断应该使用插入还是更新
                        if ($this->table)
                        {
                            // 更新数据库
                            $update = $this->database->runSQL([
                                'action' => [
                                    'cache' => [
                                        'sql' => "UPDATE NeWorldCache SET name = ? , version = ? , download = ? , md5 = ? , date = ? WHERE id = ?",
                                        'pre' => [$checkStatus['name'], $checkStatus['version'], $checkStatus['download'], $checkStatus['md5'], time(), $checkStatus['id']],
                                    ],
                                    'product' => [
                                        'sql' => "UPDATE NeWorldProduct SET id = ? , name = ? , hash = ? , date = ? WHERE license = ?",
                                        'pre' => [$checkStatus['id'], $checkStatus['softname'], $this->extended->getHash($checkStatus), time(), $this->license],
                                    ],
                                ],
                            ]);

                            // 判断是否成功
                            if ($update['cache']['rows'] != 1 || $update['product']['rows'] != 1) throw new Exception('授权信息无法更新至数据库，请刷新当前页面重试');
                        }
                        else
                        {
                            // 写入数据库
                            $insert = $this->database->runSQL([
                                'action' => [
                                    'cache' => [
                                        'sql' => "INSERT INTO NeWorldCache (id, name, version, download, md5, date) VALUES (?,?,?,?,?,?)",
                                        'pre' => [$checkStatus['id'], $checkStatus['name'], $checkStatus['version'], $checkStatus['download'], $checkStatus['md5'], time()],
                                    ],
                                    'product' => [
                                        'sql' => "INSERT INTO NeWorldProduct (id, name, version, license, hash, date) VALUES (?,?,'-',?,?,?)",
                                        'pre' => [$checkStatus['id'], $checkStatus['softname'], $this->license, $this->extended->getHash($checkStatus), time()],
                                    ],
                                ],
                            ]);

                            // 判断是否成功
                            if ($insert['cache']['rows'] != 1 || $insert['product']['rows'] != 1) throw new Exception('授权信息无法写入至数据库，请刷新当前页面重试');
                        }

                        // 如果已激活就把返回的 code 字段赋值给 code 属性
                        $this->code = $checkStatus['code'];

                        // 放到 SESSION 防止多次检测授权
                        $_SESSION['NeWorld'][$this->license]['code'] = $this->code;
                        $_SESSION['NeWorld'][$this->license]['time'] = time();
                        $_SESSION['NeWorld'][$this->license]['info'] = [
                            'status' => 'success',
                            'id' => $checkStatus['id'],
                            'name' => $checkStatus['name'],
                            'version' => $checkStatus['version'],
                            'license' => $checkStatus['license'],
                            'softname' => base64_decode($checkStatus['softname']),
                            'download' => base64_decode($checkStatus['download']),
                            'md5' => $checkStatus['md5'],
                            'date' => date('Y-m-d'),
                        ];

                        // 记录日志
                        $this->extended->recordLog('NeWorld_Manager', '已成功生成授权许可 [ '.$this->license.' ] 的本地缓存信息');

                        // 返回序列化后的信息
                        return $_SESSION['NeWorld'][$this->license]['info'];
                        break;
                    case 'error':
                        // 如果无效就报出 info 字段的信息
                        throw new Exception($checkStatus['info']);
                        break;
                    default:
                        throw new Exception('模块授权检测失败，请检查当前网站所处服务器网络是否正常，并重新刷新当前页面重试');
                }
            }
            catch (Exception $e)
            {
                throw new Exception($e->getMessage());
            }
        }

        // 获取授权信息
        public function getInfo()
        {
            // 判断是否为空
            if (empty($_SESSION['NeWorld'][$this->license]['info'])) throw new Exception('无法获取授权信息，请尝试刷新当前页面重试');

            // 返回信息
            return $_SESSION['NeWorld'][$this->license]['info'];
        }
    }
}