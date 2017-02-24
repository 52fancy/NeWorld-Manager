<?php
// 禁止直接访问此文件
if (!defined('WHMCS')) die('Access denied.');

// 现在还没有引入 WHMCS 的 init.php 文件，所以这里我们定义一个常量、值为 WHMCS 的目录
if (!defined('ROOTDIR')) define('ROOTDIR', dirname(dirname(dirname(dirname(dirname(__DIR__))))));

// 如果不存在 NeWorld 常量就定义一个，值为 NeWorld Management Tools 模块的目录
if (!defined('NeWorld')) define('NeWorld', dirname(dirname(__DIR__)));

// 引入 WHMCS 的文件，让当前类可以使用 WHMCS 的资源
require_once ROOTDIR.'/init.php';

// 自动加载
spl_autoload_register(function ($className)
{
    // 所有类文件以"命名空间.类名.Class.php"命名，这里分割字符串拼接文件名
    $className = explode('\\', $className);

    // 引入配置文件
    require dirname(dirname(__DIR__)).'/config.php';

    // 允许自动加载的命名空间
    $nameSpace = $NeWorld['nameSpace'];

    // 判断是否允许此命名空间引入
    if (in_array($className['0'], $nameSpace))
    {
        // 拼接文件名
        $className = __DIR__.'/'.$className['0'].'.'.$className['1'].'.Class.php';

        // 检查文件是否存在
        if (file_exists($className))
        {
            // 如果存在，引入相应的类
            require $className;
        }
    }
    else
    {
        // 某些类中也有使用 NeWorld 的类
        switch ($className['0'])
        {
            case 'Extended':
            case 'Database':
            case 'Encoder':
            case 'License':
                // 引入扩展类
                require_once __DIR__.'/NeWorld.'.$className['0'].'.Class.php';
                break;
            default:
                break;
        }
    }
});