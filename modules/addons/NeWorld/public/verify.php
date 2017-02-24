<?php
// 设置 WHMCS 常量
if (!defined('WHMCS')) define('WHMCS', '');

// 引入文件
require_once dirname(__DIR__).'/library/class/NeWorld.Common.Class.php';

// 实例化扩展类
$ext = new NeWorld\Extended;

try
{
    // 判断是否为空
    if (empty($_POST['license']) || empty($_POST['verify'])) throw new Exception('无法获取授权许可编号，请尝试刷新当前页面重新输出');

    // 判断 SESSION 与 POST 过来的 verify 值是否匹配
    if ($_SESSION['NeWorld']['verify'] != $_POST['verify']) throw new Exception('无法验证请求来源，请刷新当前页面重试');

    // 把授权许可赋值给变量
    $license = (string) trim($_POST['license']);

    // 实例化数据库类
    $db = new NeWorld\Database;

    // 查询此授权是否已存在数据库中
    $checkData = $db->runSQL([
        'action' => [
            'neworld' => [
                'sql' => 'SELECT name FROM NeWorldProduct WHERE license = ?',
                'pre' => [$license],
            ],
        ],
        'trans' => false,
    ]);

    // 判断是否已存在这样的信息
    if (!empty($checkData['neworld']['result'])) throw new Exception('当前输入的授权许可 [ '.$license.' ] 已授权至当前网站，请勿重复添加');

    // 实例化授权类
    $license = new NeWorld\License($license);

    // 返回产品验证信息
    $result = $license->getInfo();
}
catch (Exception $e)
{
    // 记录日志
    $ext->recordLog('NeWorld_Manager', $e->getMessage());

    // 返回错误信息
    $result = [
        'status' => 'error',
        'info' => $e->getMessage(),
    ];
}
finally
{
    // 结束程序并以 json 格式返回结果
    die(json_encode($result));
}