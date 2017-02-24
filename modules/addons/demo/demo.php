<?php
// 定义 NeWorld Manager 的目录
if (!defined('NeWorld')) define('NeWorld', dirname(__DIR__).'/NeWorld');

// 引入文件
require NeWorld.'/library/class/NeWorld.Common.Class.php';

// 当前模块(目录)名称
$moduleName = 'legendsock';

// 激活函数
if (!function_exists('demo_activate'))
{
    function demo_activate()
    {
        // 错误处理
        try
        {
            // 实例化模块类
            $addons = new NeWorld\Addons($GLOBALS['moduleName']);

            // 授权返回内容（一个数组，包含有 code/time/info 三个键，分别代表“额外代码”、“验证时间”、“授权信息”）
            $addons = $_SESSION['NeWorld'][$addons->license];

            // 实例化其他类
            // $ext = new NeWorld\Extended;

            // 模块激活代码
            // ........

            // 返回信息
            $result = [
                'status' => 'success',
                'description' => '产品启用成功',
            ];
        }
        catch (Exception $e)
        {
            // 返回信息
            $result = [
                'status' => 'error',
                'description' => '产品启用失败，错误信息: '.$e->getMessage(),
            ];
        }
        finally
        {
            return $result;
        }
    }
}

// 取消激活函数
if (!function_exists('demo_deactivate'))
{
    function demo_deactivate()
    {
        // 错误处理
        try
        {
            // 实例化模块类
            $addons = new NeWorld\Addons($GLOBALS['moduleName']);

            // 授权返回内容（一个数组，包含有 code/time/info 三个键，分别代表“额外代码”、“验证时间”、“授权信息”）
            $addons = $_SESSION['NeWorld'][$addons->license];

            // 实例化其他类
            // $ext = new NeWorld\Extended;

            // 模块取消激活的代码
            // ........

            // 返回信息
            $result = [
                'status' => 'success',
                'description' => '产品关闭成功',
            ];
        }
        catch (Exception $e)
        {
            // 返回信息
            $result = [
                'status' => 'error',
                'description' => '产品关闭失败，错误信息: '.$e->getMessage(),
            ];
        }
        finally
        {
            return $result;
        }
    }
}

// 配置函数
if (!function_exists('demo_config'))
{
    function demo_config()
    {
        return [
            'name' => 'demo Module',                                                   // 名称
            'description' => 'the module can ....',                                    // 描述
            'version' => '1.0',                                                        // 版本
            'author' => '<a target="_blank" href="https://neworld.org/">NeWorld</a>',  // 作者
        ];
    }
}

// 后台输出
if (!function_exists('demo_output'))
{
    function demo_output()
    {
        // 错误处理
        try
        {
            // 实例化模块类
            $addons = new NeWorld\Addons($GLOBALS['moduleName']);

            // 授权返回内容（一个数组，包含有 code/time/info 三个键，分别代表“额外代码”、“验证时间”、“授权信息”）
            $addons = $_SESSION['NeWorld'][$addons->license];

            // 实例化其他类
            // $ext = new NeWorld\Extended;

            // 模块输出代码
            // ........

            // 返回信息
            $result = 'xxxx';
        }
        catch (Exception $e)
        {
            // 返回信息
            $result = '控制面板启动失败，错误信息: '.$e->getMessage();
        }
        finally
        {
            echo $result;
        }
    }
}