<?php
// 声明是 UTF8
header("Content-type: text/html; charset=utf-8");

// 配置信息
$config = [
    'download' => 'https://neworld.org/downloads/',  // 文件下载目录具体地址
    'database' => (string) '',                       // 数据库名称
    'username' => (string) '',                       // 数据库用户
    'password' => (string) '',                       // 数据库密码
    'hostname' => (string) '',                       // 数据库主机
    'port' => (int) 3306,                            // 数据库端口
    'code' => [
        '1' => [                                     // 附加代码，索引匹配 Package ID
            '附加代码第一条',
            '附加代码第二条',
        ],
        '2' => [
            '附加代码第一条',
            '附加代码第二条',
        ],
    ],
    'neworld' => [
        'version' => '1.0',                          // 最新的 NeWorld Manager 版本
        'notice' => [                                // 推送到 NeWorld Manager 管理页面右侧的通知
            ['通知名称', '通知描述', '通知地址'],
            ['通知名称', '通知描述', '通知地址'],
            ['通知名称', '通知描述', '通知地址'],
        ],
    ],
];

try
{
    // POST 过来的目录
    $path = empty($_POST['path']) ? '' : (string) trim($_POST['path']);

    // POST 过来的域名（是 WHMCS 的网址）
    $domain = empty($_POST['domain']) ? '' : (string) explode('/', trim($_POST['domain']))['2'];

    // POST 过来的授权许可
    $license = empty($_POST['license']) ? '' : (string) trim($_POST['license']);

    // 判断 POST 值是否为空
    $ip = empty($_SERVER['HTTP_CLIENT_IP']) ? (string) $_SERVER['REMOTE_ADDR'] : (string) $_SERVER['HTTP_CLIENT_IP'];

    // 判断值是否为空
    if (empty($path) || empty($domain) || empty($license) || empty($ip)) throw new Exception('缺少传递值，请刷新当前页面重试');

    // 判断域名前面四位是否 www. 如果是则去掉
    if (substr($domain, 0, 4) == 'www.') $domain = substr($domain, 4);

    // 实例化数据库类
    $db = new Database($config['database'], $config['username'], $config['password'], $config['hostname'], 3306);

    // 检查域名或 IP 地址是否存在于数据库中的黑名单
    $getData = $db->runSQL([
        'action' => [
            'check' => [
                'sql' => 'SELECT notes FROM mod_licensingbans WHERE value = ? OR value = ? OR value = ?',
                'pre' => [$ip, $domain, 'www.'.$domain],
            ],
        ],
        'trans' => false,
    ]);

    // 判断是否存在黑名单记录
    if (!empty($getData['check']['result'])) throw new Exception('无法为此 IP 或域名完成授权，因为'.$getData['check']['result']['notes']);

    // 查询这个授权许可在数据库中的激活状态
    $getData = $db->runSQL([
        'action' => [
            'check' => [
                'sql' => "SELECT id,serviceid,validdomain,validip,validdirectory,status FROM mod_licensing WHERE licensekey = ?",
                'pre' => [$license],
            ],
        ],
        'trans' => false,
    ]);

    // 判断结果是否为空
    if (empty($getData['check']['result'])) throw new Exception('系统中未检索到相应的授权许可，请检查授权许可是否填写错误');

    // 赋值给变量
    $licenseid = $getData['check']['result']['id'];

    // 判断状态
    switch ($getData['check']['result']['status'])
    {
        case 'Reissued':
            // 写入当前验证的资料
            $update = $db->runSQL([
                'action' => [
                    'license' => [
                        'sql' => "UPDATE mod_licensing SET validdomain = ? , validip = ? , validdirectory = ? , status = 'Active' , lastaccess = ? WHERE licensekey = ?",
                        'pre' => [$domain.',www.'.$domain, $ip, $path, date('Y-m-d H:i:s'), $license],
                    ],
                ],
            ]);

            // 判断是否成功
            if ($update['license']['rows'] != 1) throw new Exception('当前环境的授权信息更新失败');
            break;
        case 'Active':
            // 检查 IP 地址是否匹配
            if ($getData['check']['result']['validip'] != $ip
                || $getData['check']['result']['validdirectory'] != $path
                || !in_array($domain, explode(',', $getData['check']['result']['validdomain']))
            ) {
                // 写入错误信息到日志
                $db->runSQL([
                    'action' => [
                        'license' => [
                            'sql' => "INSERT INTO setting(licenseid,domain,ip,path,message,datetime) VALUES (?,?,?,?,?,?)",
                            'pre' => [$licenseid, $domain, $ip, $path, 'The license information does not match', date('Y-m-d H:i:s')],
                        ],
                    ],
                ]);

                // 报错
                throw new Exception('当前环境与授权中心所注册的信息不匹配');
            }

            // 写入当前验证的时间
            $update = $db->runSQL([
                'action' => [
                    'license' => [
                        'sql' => "UPDATE mod_licensing SET lastaccess = ? WHERE licensekey = ?",
                        'pre' => [date('Y-m-d H:i:s'), $license],
                    ],
                ],
            ]);

            // 判断是否成功
            if ($update['license']['rows'] != 1) throw new Exception('授权连接时间更新失败');
            break;
        case 'Suspended':
            throw new Exception('授权许可被暂停，请前往客户中心检查');
            break;
        case 'Expired':
            throw new Exception('授权许可已逾期，请前往客户中心检查');
            break;
        default:
            throw new Exception('无法获取授权状态，请刷新重试');
    }

    // 搜索 serviceid 在数据库中属于哪个 package
    $getData = $db->runSQL([
        'action' => [
            'search' => [
                'sql' => 'SELECT packageid FROM tblhosting WHERE id = ?',
                'pre' => [$getData['check']['result']['serviceid']],
            ],
        ],
        'trans' => false,
    ]);

    // 检测结果是否为空
    if (empty($getData['search']['result']['packageid'])) throw new Exception('无法获取当前授权许可的套餐 ID 编号，请刷新页面重试');

    // 赋值给变量
    $package = (int) $getData['search']['result']['packageid'];

    // 获取套餐(模块或产品)的名字等信息
    $getData = $db->runSQL([
        'action' => [
            'search' => [
                'sql' => 'SELECT id,name,configoption11,configoption12 FROM tblproducts WHERE id = ?',
                'pre' => [$package],
            ],
        ],
        'trans' => false,
    ]);

    // 判断结果是否为空
    if (empty($getData['search']['result'])) throw new Exception('无法获取当前授权许可的套餐信息，请刷新页面重试');

    // 赋值给变量
    $softName = (string) $getData['search']['result']['name'];
    $md5 = (string) $getData['search']['result']['configoption11'];
    $name = (string) $getData['search']['result']['configoption12'];

    // 获取套餐 ID 在数据库中最大的下载 ID（也就是最后上传文件的 ID）
    $getData = $db->runSQL([
        'action' => [
            'search' => [
                'sql' => 'SELECT download_id FROM tblproduct_downloads WHERE product_id = ? ORDER BY download_id DESC LIMIT 1',
                'pre' => [$getData['search']['result']['id']],
            ],
        ],
        'trans' => false,
    ]);

    // 判断获取到的数据是否为空
    if (empty($getData['search']['result'])) throw new Exception('无法获取当前授权许可所在套餐的文件 ID 编号，请刷新重试');

    // 查询这个文件的文件名
    $getData = $db->runSQL([
        'action' => [
            'search' => [
                'sql' => 'SELECT location FROM tbldownloads WHERE id = ?',
                'pre' => [$getData['search']['result']['download_id']],
            ],
        ],
        'trans' => false,
    ]);

    // 判断文件名是否为空
    if (empty($getData['search']['result']['location'])) throw new Exception('无法获取当前授权许可相应模块的文件名称，请刷新重试');

    // 拼接文件下载地址
    $download = (string) $config['download'].$getData['search']['result']['location'];

    // 获取文件版本
    $version = (string) substr($getData['search']['result']['location'], -10, -7);

    // 返回结果
    $result = [
        'id' => $package,
        'status' => 'success',
        'name' => $name,
        'version' => $version,
        'softname' => base64_encode($softName),
        'download' => base64_encode($download),
        'md5' => $md5,
        'neworld' => $config['neworld'],
    ];

    // 判断是否存在额外代码，如果有的话加入索引
    if (!empty($config['code'][$package])) $result['code'] = $config['code'][$package];
}
catch (Exception $e)
{
    // 返回结果
    $result = [
        'status' => 'error',
        'info' => $e->getMessage(),
    ];
}
finally
{
    die(json_encode($result));
}

// 数据库类
class Database
{
    private $pdo; // 一个私有化属性

    // 构造方法
    public function __construct($database = '', $username = '', $password = '', $hostname = 'localhost', $port = 3306)
    {
        // 强制转换类型
        $database = (string) $database;
        $username = (string) $username;
        $password = (string) $password;
        $hostname = (string) $hostname;
        $port = (int) $port;

        // 如果数据库用户名是空的
        if (empty($username))
        {
            // 使用 WHMCS 自带的 Capsule 解析 pdo (由于无法使用预处理)
//                $this->pdo = Capsule::connection()->getPdo();
            // 从 WHMCS 的全局变量中获取数据库信息;
            empty($GLOBALS['db_port']) ? $port = 3306 : $port = $GLOBALS['db_port'];
            $this->pdo = new PDO("mysql:dbname={$GLOBALS['db_name']};host={$GLOBALS['db_host']};port={$port}", $GLOBALS['db_username'], $GLOBALS['db_password']);
        }
        else
        {
            // 实例化一个 pdo 对象
            $this->pdo = new PDO("mysql:dbname={$database};host={$hostname};port={$port}", $username, $password);
        }
    }

    // 运行 SQL 语句
    public function runSQL(array $action)
    {
        // 判断默认 $arr['trans'] 为空或者为 true 的情况下开启事务
        if (empty($action['trans']))
        {
            $trans = true;
        }
        else
        {
            if ($action['trans'])
            {
                $trans = true;
            }
            else
            {
                $trans = false;
            }
        }

        try
        {
            // 开启事务，相当于新建了一个还原点，如果出现错误的话要还原到这里之后的变化
            if ($trans) $this->pdo->beginTransaction();

            // 赋值为一个空的关联数组
            $result = [];
            foreach ($action['action'] as $key => $value)
            {
                // 判断如果不存在 SQL 则报错
                if (empty($value['sql'])) throw new Exception('语法错误，必须传入一个数组，且包含 [ sql ] 键值');

                $sql = $this->pdo->prepare($value['sql']);
                isset($value['pre']) ? $sql->execute($value['pre']) : $sql->execute();
                isset($value['all']) ? $sqlfetch = $sql->fetchAll(PDO::FETCH_ASSOC) : $sqlfetch = $sql->fetch(PDO::FETCH_ASSOC);

                $result[$key]['rows'] = $sql->rowCount();

                if (!empty($sqlfetch)) $result[$key]['result'] = $sqlfetch;
            }

            // 提交事务
            if ($trans) $this->pdo->commit();

            // 返回结果
            return $result;
        }
        catch (Exception $e)
        {
            // 如果执行失败了，那就还原到刚刚开启事务的地方
            if ($trans) $this->pdo->rollBack();

            // 抛出异常
            throw new Exception($e->getMessage());
        }
    }

    // 导入 sql
    public function putSQL(array $action)
    {
        // 如果指定数据库文件为空则报错
        if (empty($action['sql'])) throw new Exception('未定义需要导入的数据库文件');

        // 如果未定义目录，默认为 class.php 当前的目录
        empty($action['dir']) ? $dir = NeWorld.'/library/data/' : $dir = $action['dir'].'/';

        // 拼合 SQL 的文件名
        $file = $dir.$action['sql'].'.sql';

        // 判断文件是否存在并且可读
        if (file_exists($file) && is_readable($file))
        {
            // 读取 SQL 文件
            $file = file_get_contents($file);

            // 去除注释
            $file = preg_replace('/--.*/i', '', $file);
            $file = preg_replace('/\/\*.*\*\/(\;)?/i', '', $file);

            // 以 ; 结束符号和换行符创建数组
            $file = explode(";\n", $file);

            // 遍历数组去掉空行
            foreach ($file as $value)
            {
                // 去掉左右空白内容
                $value = trim($value);

                // 如果去掉左右空白之后空了，就跳到下一次循环
                if (empty($value))
                {
                    continue;
                }
                else
                {
                    $sql[] = $value;
                }
            }

            // 如果是空的就返回一个错误信息
            if (empty($sql)) throw new Exception('需要导入的文件里面没有 SQL 语句');

            // 遍历刚才的数组
            foreach ($sql as $key => $value)
            {
                try
                {
                    // 执行 SQL
                    $this->runSQL([
                        'action' => [
                            'put' => [
                                'sql' => $value,
                            ],
                        ],
                    ]);
                }
                catch (Exception $e)
                {
                    throw new Exception('导入 SQL 时，第 '.$key.' 行出现错误，错误信息: '.$e->getMessage());
                }
            }
        }
        else
        {
            throw new Exception('需要导入的 SQL 文件 [ '.$file.'] 不存在或无法读取，请检查是否无访问权限');
        }

        // 返回执行后受影响的行数
        return $this->pdo->exec($sql);
    }

    // 检测表是否存在
    public function checkTable($tableName = '')
    {
        $tableName = (string) $tableName;
        if (empty($tableName)) throw new Exception('未定义需要查询的数据表名称');

        $getData = $this->runSQL([
            'action' => [
                'table' => [
                    'sql' => 'SHOW TABLES LIKE ?',
                    'pre' => [$tableName],
                ],
            ],
            'trans' => false,
        ]);

        if (current($getData['table']['result']) == $tableName)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    // 删除表
    public function deleteTable($tableName = '')
    {
        if (is_array($tableName))
        {
            $sql = '';
            foreach ($tableName as $key => $value)
            {
                if ($key == 0)
                {
                    $sql .= $value;
                }
                else
                {
                    $sql .= ", $value";
                }
            }
        }
        else
        {
            $sql = $tableName;
        }

        // 拼接 SQL
        $sql = (string) 'DROP TABLE '.$sql;

        if (empty($sql)) throw new Exception('已定义的数据表名称为空或并非字符串');

        // 执行删除命令
        $this->runSQL([
            'action' => [
                'delete' => [
                    'sql' => $sql,
                ],
            ],
        ]);

        // 检查是否有删除成功
        if (is_array($tableName))
        {
            foreach ($tableName as $value)
            {
                if ($this->checkTable($value))
                {
                    throw new Exception('数据表 [ '.$value.' ] 删除失败，请重试操作');
                }
            }
        }
        else
        {
            if ($this->checkTable($tableName))
            {
                throw new Exception('数据表 [ '.$tableName.' ] 删除失败，请重试操作');
            }
        }
    }
}