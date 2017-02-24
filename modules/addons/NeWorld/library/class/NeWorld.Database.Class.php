<?php
// 声明命名空间
namespace NeWorld;

// 引入文件
require_once __DIR__.'/NeWorld.Common.Class.php';

// 使用其他的类
use \PDO;
use \Exception;
//use \Illuminate\Database\Capsule\Manager as Capsule;

// 数据库类
if (!class_exists('Database'))
{
    class Database
    {
        // 屬性
        private $pdo;
        private $data;

        // 构造方法
//        public function __construct($database = '', $username = '', $password = '', $hostname = 'localhost', $port = 3306, $charset = 'UTF8')
//        {
//            // 强制转换类型
//            $database = (string) $database;
//            $username = (string) $username;
//            $password = (string) $password;
//            $hostname = (string) $hostname;
//            $port = (int) $port;
//            // 如果数据库用户名是空的
//            if (empty($username))
//            {
//                // 使用 WHMCS 自带的 Capsule 解析 pdo (由于无法使用预处理)
////                $this->pdo = Capsule::connection()->getPdo();
//                // 从 WHMCS 的全局变量中获取数据库信息;
//                empty($GLOBALS['db_port']) ? $port = 3306 : $port = $GLOBALS['db_port'];
//                $this->pdo = new PDO("mysql:dbname={$GLOBALS['db_name']};host={$GLOBALS['db_host']};port={$port}",
//                    $GLOBALS['db_username'], $GLOBALS['db_password'], [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.$charset]);
//            }
//            else
//            {
//                // 实例化一个 pdo 对象
//                $this->pdo = new PDO("mysql:dbname={$database};host={$hostname};port={$port}",
//                    $username, $password, [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.$charset]);
//            }
//        }
        public function __construct(array $data = ['database' => '', 'username' => '', 'password' => '', 'hostname' => 'localhost', 'charset' => 'UTF8', 'port' => 3306])
        {
            // 将信息赋值给 data 属性
            $this->data = $data;

            // 如果数据库用户名是空的
            if (empty($this->data['username']))
            {
                // 将全局变量中的数据库信息赋值给 data 属性
                $this->data = [
                    'database' => (string) $GLOBALS['db_name'],
                    'username' => (string) $GLOBALS['db_username'],
                    'password' => (string) $GLOBALS['db_password'],
                    'hostname' => empty($GLOBALS['db_host']) ? $this->data['hostname'] : (string) $GLOBALS['db_host'],
                    'charset' => $this->data['charset'],
                    'port' => empty($GLOBALS['db_port']) ? $this->data['port'] : (int) $GLOBALS['db_port'],
                ];
            }

            // 实例化 PDO 对象
            $this->pdo = new PDO("mysql:dbname={$this->data['database']};host={$this->data['hostname']};port={$this->data['port']}",
                $this->data['username'], $this->data['password'], [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.$this->data['charset']]);
        }

        // 返回数据库链接信息
        public function getInfo()
        {
            return $this->data;
        }

        // 运行 SQL 语句
        public function runSQL(array $action = ['action' => [], 'trans' => true])
        {
            // 判断数组是否为空
            if (empty($action['action'])) throw new Exception('未传入需要操作的 SQL 语句');

            try
            {
                // 开启事务，相当于新建了一个还原点，如果出现错误的话要还原到这里之后的变化
                if ($action['trans']) $this->pdo->beginTransaction();

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
                if ($action['trans']) $this->pdo->commit();

                // 返回结果
                return $result;
            }
            catch (Exception $e)
            {
                // 如果执行失败了，那就还原到刚刚开启事务的地方
                if ($action['trans']) $this->pdo->rollBack();

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
            // 定义为字符串
            $tableName = (string) $tableName;

            // 判断是否为空
            if (empty($tableName)) throw new Exception('未定义需要查询的数据表名称');

            // 在数据库中查询表
            $getData = $this->runSQL([
                'action' => [
                    'table' => [
                        'sql' => 'SHOW TABLES LIKE ?',
                        'pre' => [$tableName],
                    ],
                ],
                'trans' => false,
            ]);

            // 查询出来的结果
            $result = current($getData['table']['result']);

            // 判断表名是否存在
            if ($result == $tableName || $result == strtolower($tableName) || $result == strtoupper($tableName))
            {
                // 在存在表名(不分大小写)的情况下返回 true
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
}