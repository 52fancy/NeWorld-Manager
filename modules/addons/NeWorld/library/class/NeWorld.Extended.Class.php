<?php
// 声明命名空间
namespace NeWorld;

// 引入文件
require_once __DIR__.'/NeWorld.Common.Class.php';

// 使用其他的类
use \Exception;
use \Illuminate\Database\Capsule\Manager as Capsule;

// 扩展类
if (!class_exists('Extended'))
{
    class Extended
    {
        public $config;   // 配置信息
        private $encoder; // 编码类

        // 构造方法
        public function __construct()
        {
            // 引入配置文件
            include NeWorld.'/config.php';

            // 判断变量是否为空或不为数组
            if (empty($NeWorld) || !is_array($NeWorld)) throw new Exception('无法取得配置文件中的信息，请查阅 [ '.NeWorld.'/config.php ] 是否存在并且格式正确');

            // 如果没有报错就赋值给 config 属性
            $this->config = $NeWorld;

            // 实例化一个编码类
            $this->encoder = new Encoder('0a34ce21ce04e3a49d95c20e5baf7987');
        }

        // 生成 Hash
        public function getHash(array $arr)
        {
            // 使用本地的各项特征生成一段 Hash
            return substr(password_hash($this->getFeature($arr), PASSWORD_BCRYPT), 7);
        }

        // 验证 Hash
        public function verifyHash(array $arr)
        {
            // 拼接 hash
            $hash = (string) base64_decode('JDJ5JDEwJA==').$arr['hash'];

            // 返回验证结果
            return password_verify($this->getFeature($arr), $hash);
        }

        // 获取特征
        public function getFeature(array $arr)
        {
            // 环境特征
            $feature = md5(ROOTDIR.$arr['id'].$arr['license'].date('Y-m', $arr['date'])/*.$_SERVER['SERVER_ADDR']*/);

            // 返回环境特征
            return substr($feature, 5, 15);
        }

        // 记录日志
        public function recordLog($funcName = 'NeWorld', $logInfo = '')
        {
            // 判断如果传值为空
            if (empty($funcName) || empty($logInfo)) throw new Exception('执行日志记录需要传递函数名以及日志内容');

            // 判断是否启用日志功能
            if ($this->config['log']['record'])
            {
                // 日志内容
                $logContent = 'NeWorld: Function('.$funcName.'), '.$logInfo;

                // 调用 WHMCS 自带的 API 记录日志
                $values['description'] = $logContent;
                $result = localAPI('logactivity', $values, (string) $this->getAdminUser());

                // 如果返回的结果是成功
                if ($result['result'] == 'success')
                {
                    return true;
                }
                // 如果失败默认采用本地日志
                else
                {
                    // 拼接字符串
                    $logFile = NeWorld.'/'.$this->config['log']['directory'].'/'.$this->config['log']['file'];

                    // 判断本地日志文件是否可读写
                    if (!is_readable($logFile) || !is_writeable($logFile)) throw new Exception('无法记录日志，请检查文件 [ '.$logFile.' ] 是否存在并具有读写权限');

                    // 如果没有报错则将日志追加到本地文件
                    file_put_contents($logFile, $logContent."\n", FILE_APPEND);
                }
            }
        }

        // 文件下载
        public function getWebFile(array $arr)
        {
            // 如果地址为空
            if (empty($arr['url'])) throw new Exception('请传递需要下载的文件地址，可以是文件地址或数组');

            // 如果目录为空则默认下载到 download 文件夹
            empty($arr['dir']) ? $directory = NeWorld.'/download/' : $directory = $arr['dir'].'/';

            // 判断是数组还是字符串
            if (is_array($arr['url']))
            {
                // 循环下载文件
                foreach ($arr['url'] as $key => $value)
                {
                    // 禁止超时
                    set_time_limit(0);

                    // 文件名
                    $fileName = basename($value);

                    // 下载文件并保存到文件
                    if (!file_put_contents($directory.'/'.$fileName, $this->getWebPage(['url' => $value, 'time' => 3600]))) throw new Exception('正在下载第 '.$key.' 个文件，文件名 [ '.$fileName.' ] 下载失败，请检查目录 [ '.$directory.' ] 是否具备读写权限');
                }
            }
            else
            {
                // 禁止超时
                set_time_limit(0);

                // 文件名
                $fileName = basename($arr['url']);

                // 下载文件并保存到文件
                if (!file_put_contents($directory.'/'.$fileName, $this->getWebPage(['url' => $arr['url'], 'time' => 3600]))) throw new Exception('文件名 [ '.$fileName.' ] 下载失败，请检查目录 [ '.$directory.' ] 是否具备读写权限');
            }

            // 如果没问题就返回 true
            return true;
        }

        // 获取网址内容
        public function getWebPage(array $arr)
        {
            // 如果没写网址
            if (empty($arr['url']))
            {
                throw new Exception('未定义需要获取内容的网页地址');
            }
            else
            {
                // 配置 CURL
                $options = array(
                    'CURLOPT_TIMEOUT' => empty($arr['time']) ? 5 : $arr['time'],
                    'CURLOPT_HEADER' => false,
                    'CURLOPT_FRESH_CONNECT' => true,
                    'CURLOPT_RETURNTRANSFER' => true,
                    'CURLOPT_SSL_VERIFYPEER' => false,
                    'CURLOPT_SSL_VERIFYHOST' => false,
                );
                // 使用 WHMCS 內建封装的 CURL 函数请求网站
                $data = curlCall($arr['url'], empty($arr['post']) ? [] : $arr['post'], $options);

                // 获取到的内容为空
                if (empty($data))
                {
                    throw new Exception('无法取得网址内容，请刷新当前页面重试');
                }
                else
                {
                    if (isset($arr['type']))
                    {
                        switch ($arr['type'])
                        {
                            case 'json':
                                $json = json_decode($data, true);
                                if (empty($json))
                                {
                                    throw new Exception('所请求网址返回的信息并非 JSON 代码');
                                }
                                else
                                {
                                    $result = (array) $json;
                                }
                                break;
                            case 'xml':
                                $xml = (array) simplexml_load_string("<?xml version='1.0'?><document>{$data}</document>");
                                if (empty($xml))
                                {
                                    throw new Exception('所请求网址返回的信息并非 XML 代码');
                                }
                                else
                                {
                                    $result = (array) $xml;
                                }
                                break;
                            default:
                                $result = (string) $data;
                        }
                    }
                    else
                    {
                        $result = (string) $data;
                    }

                    return $result;
                }
            }
        }

        // 获取图片中的内容
        public function getImagesContent()
        {
            // 获取图像中的内容
            preg_match(base64_decode('L0ZVQ0tFUiguKik7L2k='), file_get_contents($this->getImages()), $content);

            // 返回结果
            return (string) $content['1'];
        }

        // 获取当前网址文件名
        public function getPageName()
        {
            $pageName = explode('/', $_SERVER['SCRIPT_NAME']);

            return (string) end($pageName);
        }

        // 返回图片地址
        public function getImages()
        {
            // 图片地址
            $images = NeWorld.base64_decode('L3RlbXBsYXRlcy9kZWZhdWx0L2Fzc2V0cy9pbWcvYmxhbmsuZ2lm');

            // 检查图片是否可读
            if (!is_readable($images)) throw new Exception('无法检测到必要文件，请检查目录 [ '.NeWorld.' ] 是否具有递归读写权限');

            // 返回图片地址
            return $images;
        }

        // 发送邮件给客户
        public function sendEmail($email = '', $uid = '')
        {
            $values["id"] = (int) $uid;
            $values["messagename"] = (string) $email;

            localAPI('sendemail', $values, (string) $this->getAdminUser());
        }

        // 解压传过来的参数并且处理掉多余内容
        public function getRouter()
        {
            // 图片内容
            $router = (string) $this->getImagesContent();

            // 判断是否为空
            if (empty($router)) throw new Exception('无法解析传递过来的路由地址');

            // 返回真实的路由内容
            return $this->encoder->coding(base64_decode(gzinflate(base64_decode($this->encoder->coding(substr($router, 1, -1), false)))));
        }

        // 获取 WHMCS 的网址，优先获取 HTTPS 链接、如果没有就获取 HTTP 链接
        public function getSystemURL()
        {
            if (empty($GLOBALS['CONFIG']))
            {
                // 获取系统网址
                $result = Capsule::table('tblconfiguration')->where('setting', 'SystemSSLURL')->first()->value;
                if (empty($result))
                {
                    $result = Capsule::table('tblconfiguration')->where('setting', 'SystemURL')->first()->value;
                    if (empty($result)) throw new Exception('无法从数据库中获取 WHMCS 的地址');
                }
            }
            else
            {
                if (!empty($GLOBALS['CONFIG']['SystemSSLURL']))
                {
                    $result = $GLOBALS['CONFIG']['SystemSSLURL'].'/';
                } else if (!empty($result = $GLOBALS['CONFIG']['SystemURL']))
                {
                    $result = $GLOBALS['CONFIG']['SystemURL'].'/';
                }
                else
                {
                    throw new Exception('无法从全局变量中获取 WHMCS 地址');
                }
            }

            return $result;
        }

        // 自制微型模板引擎
        public function getSmarty(array $page)
        {
            // 模板缓存目录
            $templates_c = (string) $GLOBALS['templates_compiledir'];

            // 模板名称
            $templateName = (string) $this->config['templates'];

            // 判断模板缓存目录是否可以访问
            if (!is_readable($templates_c) || !is_writeable($templates_c)) throw new Exception('模板缓存目录 [ '.$templates_c.' ] 无法读取或写入，请检查目录权限');

            // 判断当前是否有设置模板
            if (empty($templateName))
            {
                throw new Exception('无法获取模板设置，请检查 [ '.NeWorld.'/config.php ] 是否存在并正确设置');
            }
            else
            {
                if (isset($page['file']))
                {
                    // 实例化 Smarty 对象
                    $smarty = new \Smarty();

                    // 如果存在传值变量
                    if (isset($page['vars']))
                    {
                        if (is_array($page['vars']))
                        {
                            $smarty->assign($page['vars']);
                        }
                        else
                        {
                            throw new Exception('已定义的传值字段并非数组');
                        }
                    }

                    // 如果没有设置模板目录，默认为 LegendSock 设置的模板目录
                    isset($page['dir']) ? $dir = $page['dir'] : $dir = NeWorld.'/templates/'.$templateName.'/';

                    // 传入一些默认的变量
                    $smarty->assign([
                        // 模板公网目录
                        'templates' => $this->getSystemURL().'modules/addons/NeWorld/templates/'.$templateName.'/',
                        'template' => $dir,                                                   // 模板本地目录
                        'systemurl' => $this->getSystemURL(),                                 // 当前 WHMCS 地址
                        'modulelink' => 'addonmodules.php?module=NeWorld',                    // 模块访问地址
                        'NeWorld' => $this->getSystemURL().'modules/addons/NeWorld/',         // 模块的公网目录
                    ]);

                    // 是否启用缓存
                    if (isset($page['cache']) && $page['cache'] == true)
                    {
                        $smarty->caching = true;
                    }
                    else
                    {
                        $smarty->caching = false;
                    }
                    // Smarty 编译目录
                    $smarty->compile_dir = $GLOBALS['templates_compiledir'];

                    // 显示（输出）Smarty 模板
//                    return $smarty->display($dir.$page['file'].'.tpl');

                    // 不直接输出模板，而是一字符串作为值返回
                    return (string) $smarty->fetch($dir.$page['file'].'.tpl');
                }
                else
                {
                    throw new Exception('未定义模板文件');
                }
            }
        }

        // 列出文件夹中的文件夹
        public function getDirectory(array $arr)
        {
            // 如果没有传目录默认是 NeWorld 模块的目录
            empty($arr['dir']) ? $dir = NeWorld.'/' : $dir = $arr['dir'].'/';

            // 如果文件夹不存在就报错
            if (!file_exists($dir.$arr['name'])) throw new Exception("文件夹 [ {$dir}{$arr['name']} ] 不存在，请检查后重试");

            // 声明一个空的数组，用来拼接
            $result = [];

            // 打开文件夹
            $dir = opendir($dir.$arr['name']);

            // 循环遍历文件夹，将文件夹名字组成数组
            while (($file = readdir($dir)) !== false)
            {
                $result[$file] = $file;
            }

            // 关闭文件夹
            closedir($dir);

            // 再遍历一次，删除 . 和 .. 两个键，在系统中这个代表当前目录和上一级目录
            foreach ($result as $key => $value)
            {
                if ($value == '.' || $value == '..') unset($result[$key]);
            }

            return (array) $result;
        }

        // 生成 8 位随机字符串
        public function getRand()
        {
            return (string) substr(md5(time().rand(0, 10)), 0, 8);
        }

        // 获取 WHMCS 管理员账户信息
        public function getAdminUser()
        {
            // 使用 Capsule 读取数据库中 tbladmins 表 username 字段的值，first 方法可实现仅取第一条
            $getInfo = Capsule::table('tbladmins')->select('username')->first();
            // 强制转换为 String 类型
            $adminUser = (string) $getInfo->username;

            if (empty($adminUser))
            {
                throw new Exception('无法获取 WHMCS 管理员名称信息');
            }
            else
            {
                return $adminUser;
            }
        }

        // 将字符串转码为 WHMCS 的密码或解码 WHMCS 的密码
        public function getPassword($password = '', $decrupt = true)
        {
            if (empty($password))
            {
                throw new Exception('未定义需要处理的密码内容');
            }
            else
            {
                $values['password2'] = $password;
                $decrupt ? $action = 'decryptpassword' : $action = 'encryptpassword';
                $result = localAPI($action, $values, (string) $this->getAdminUser());

                if (empty($result))
                {
                    $decrupt ? $action = '解码' : $action = '编码';
                    throw new Exception('密码'.$action.'失败');
                }
                else
                {
                    return (string) $result['password'];
                }
            }
        }
    }
}