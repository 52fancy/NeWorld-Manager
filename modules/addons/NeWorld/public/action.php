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
    if (empty($_POST['id']) || empty($_POST['license']) || empty($_POST['verify'])) throw new Exception('无法操作信息，请尝试刷新当前页面重新操作');

    // 判断 SESSION 与 POST 过来的 verify 值是否匹配
    if ($_SESSION['NeWorld']['verify'] != $_POST['verify']) throw new Exception('无法验证请求来源，请刷新当前页面重试');

    // 把操作信息赋值给变量
    $id = (int) trim($_POST['id']);
    $action = (string) trim($_POST['action']);
    $license = (string) trim($_POST['license']);

    // 判断是否为空
    if (empty($id) || empty($action) || empty($license)) throw new Exception('缺少传递值，请尝试刷新当前页面重新操作');

    // 实例化数据库类
    $db = new NeWorld\Database;

    // 查询此授权是否已存在数据库中
    $checkData = $db->runSQL([
        'action' => [
            'neworld' => [
                'sql' => 'SELECT download,md5,name FROM NeWorldCache WHERE id = ?',
                'pre' => [$id],
            ],
        ],
        'trans' => false,
    ]);

    // 判断是否已存在这样的信息
    if (empty($checkData['neworld']['result'])) throw new Exception('所选的授权许可 [ '.$license.' ] 在当前网站中未缓存信息');

    // 赋值给变量
    $md5 = (string) $checkData['neworld']['result']['md5'];
    $name = (string) $checkData['neworld']['result']['name'];
    $download = (string) base64_decode($checkData['neworld']['result']['download']);

    // 判断行动
    switch ($action)
    {
        case 'install':
        case 'reinstall':
        case 'update':
            // 下载文件
            $ext->getWebFile(['url' => $download]);

            // 文件版本
            $version = (string) substr(basename($download), -10, -7);

            // 拼接文件名
            $fileName = (string) NeWorld.'/download/'.basename($download);

            // 判断文件是否存在
            if (!file_exists($fileName)) throw new Exception('文件下载失败，请检查目录是否具有递归读写权限');

            // 验证文件 md5
            if (md5_file($fileName) != $md5) throw new Exception('文件下载成功、但文件 MD5 不匹配，请刷新页面重试');

            // 实例化一个 PharData 类
            $PharData = new PharData($fileName);

            // 使用 PharData 类解压 tar.gz / zip 等文件
            $PharData->extractTo(ROOTDIR, null, true);

            // 把当前的版本写入至数据库中
            $db->runSQL([
                'action' => [
                    'version' => [
                        'sql' => 'UPDATE NeWorldProduct SET version = ? WHERE id = ?',
                        'pre' => [$version, $id],
                    ],
                ],
            ]);

            // 删除文件
            unlink($fileName);
            break;
        case 'delete':
            // 获取产品的名字（目录名）
            $getData = $db->runSQL([
                'action' => [
                    'product' => [
                        'sql' => 'SELECT name FROM NeWorldCache WHERE id = ?',
                        'pre' => [$id],
                    ],
                ],
                'trans' => false,
            ]);

            // 判断是否为空
            if (empty($getData['product']['result']['name'])) throw new Exception('无法从数据库中获取此产品的缓存信息，请尝试刷新当前页面重试');

            // 检查此产品在 WHMCS 中是否已启用
            $getData = $db->runSQL([
                'action' => [
                    'check' => [
                        'sql' => "SELECT * FROM tbladdonmodules WHERE module = ?",
                        'pre' => [$name],
                    ],
                ],
                'trans' => false,
            ]);

            // 检查是否有结果
            if ($getData['check']['rows'] != 0) throw new Exception('由于当前产品仍在使用中，因此无法为你删除授权许可');
            else
            {
                // 删除数据库中相应的条目
                $db->runSQL([
                    'action' => [
                        'cache' => [
                            'sql' => 'DELETE FROM NeWorldCache WHERE id = ?',
                            'pre' => [$id],
                        ],
                        'product' => [
                            'sql' => 'DELETE FROM NeWorldProduct WHERE id = ?',
                            'pre' => [$id],
                        ],
                    ],
                ]);
            }
            break;
        case 'check':
            // 实例化授权类
            $version = new \NeWorld\License($license, true);

            // 获取返回数组中的 version 键赋值给 $version
            $version = $version->getInfo()['version'];

            // 判断是否为空
            if (empty($version)) throw new Exception('无法获取版本编号，请尝试刷新当前页面重试');
            break;
        default:
            throw new Exception('无法识别的操作类型，请刷新当前页面重试');
    }

    // 返回信息
    $result = [
        'status' => 'success',
    ];

    // 如果不为空则返回版本号
    if (!empty($version)) $result['version'] = $version;
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
    // 结束程序并且以 json 格式返回结果
    die(json_encode($result));
}