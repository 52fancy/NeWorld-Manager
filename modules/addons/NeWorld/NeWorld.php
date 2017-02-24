<?php
// 引入文件
require_once __DIR__.'/library/class/NeWorld.Common.Class.php';

// 判断函数是否不存在
if (!function_exists('NeWorld_config'))
{
    // 设置项目
    function NeWorld_config()
    {
        // 实例化扩展类
        $ext = new NeWorld\Extended;

        // 返回结果
        return [
            'name' => 'NeWorld Manager',
            'description' => '这是基于 NeWorld 平台的联合管理模块，可在线安装、升级、与管理你的授权',
            'version' => $ext->config['version'],    // 读取配置文件中的版本
            'author' => '<a target="_blank" href="https://neworld.org/">NeWorld</a>',
        ];
    }
}

// 判断是否存在函数
if (!function_exists('NeWorld_activate'))
{
    // 激活模块
    function NeWorld_activate()
    {
        try
        {
            // 实例化数据库类
            $db = new NeWorld\Database;

            // 检查表名是否存在
            if ($db->checkTable('NeWorld') || $db->checkTable('NeWorldCache') || $db->checkTable('NeWorldProduct'))
                throw new Exception('当前数据库中已存在 NeWorld Manager 相关的数据表，请检查数据库是否清洁');

            // 导入数据库
            $db->putSQL([
                'sql' => 'activate',
            ]);

            // 检查表名是否存在
            if (!$db->checkTable('NeWorld') || !$db->checkTable('NeWorldCache') || !$db->checkTable('NeWorldProduct'))
                throw new Exception('数据库操作失败，所需要的表无法导入，请登录 phpMyAdmin 检查数据库操作权限是否正常');

            // 生成一些默认数据
            $default = $db->runSQL([
                'action' => [
                    'insert' => [
                        'sql' => 'INSERT INTO NeWorld(setting,value) VALUES (?,?),(?,?)',
                        'pre' => ['version', '1.2', 'notice', json_encode([
                            ['通知待更新', '通知待更新', '通知待更新'],
                            ['通知待更新', '通知待更新', '通知待更新'],
                            ['通知待更新', '通知待更新', '通知待更新'],
                        ])],
                    ],
                ],
            ]);

            // 判断是否写入成功
            if ($default['insert']['rows'] != 2) throw new Exception('数据库默认值写入失败，请登录 phpMyAdmin 检查数据库操作权限是否正常');

            // 返回信息
            $result = [
                'status' => 'success',
                'description' => '模块已成功激活，请单击 [ Configure ] 按钮、勾选 [ Full Administrator ] 后保存即可访问控制台',
            ];
        }
        catch (Exception $e)
        {
            // 返回信息
            $result = [
                'status' => 'error',
                'description' => $e->getMessage(),
            ];
        }
        finally
        {
            return $result;
        }
    }
}

// 判断函数是否不存在
if (!function_exists('NeWorld_deactivate'))
{
    // 卸载模块
    function NeWorld_deactivate()
    {
        try
        {
            // 实例化数据库类
            $db = new NeWorld\Database;

            // 检查表名是否存在
            if (!$db->checkTable('NeWorld') || !$db->checkTable('NeWorldCache') || !$db->checkTable('NeWorldProduct'))
                throw new Exception('数据库中缺少相应的表，请检查当前数据库中 [ NeWorld / NeWorldCache / NeWorldProduct ] 等数据表是否存在');

            // 删除数据库
            $db->deleteTable(['NeWorld', 'NeWorldCache', 'NeWorldProduct']);

            // 检查表名是否存在
            if ($db->checkTable('NeWorld') || $db->checkTable('NeWorldCache') || $db->checkTable('NeWorldProduct'))
                throw new Exception('数据库中仍然存在相应的表，请登录 phpMyAdmin 检查数据库操作权限是否正常');

            // 返回信息
            $result = [
                'status' => 'success',
                'description' => '模块已成功关闭，现在你可以安全删除 NeWorld Manager 相关文件',
            ];
        }
        catch (Exception $e)
        {
            // 返回信息
            $result = [
                'status' => 'error',
                'description' => $e->getMessage(),
            ];
        }
        finally
        {
            return $result;
        }
    }
}

// 判断函数是否不存在
if (!function_exists('NeWorld_output'))
{
    // 插件输出
    function NeWorld_output($vars)
    {
        try
        {
            // 实例化扩展类
            $ext = new NeWorld\Extended;

            try
            {
                // 实例化数据库类
                $db = new NeWorld\Database;

                // 生成随机数值放入 SESSION 做验证
                $_SESSION['NeWorld']['verify'] = $ext->getRand();

                // 默认会返回控制台的变量组
                $result = [
                    'version' => $vars['version'],
                    'verify' => $_SESSION['NeWorld']['verify'],
                    'notice' => '', // 默认输出的通知为空
                ];

                // 读取数据库中已激活的产品
                $getData = $db->runSQL([
                    'action' => [
                        'product' => [
                            'sql' => 'SELECT * FROM NeWorldProduct',
                            'all' => true,
                        ],
                        'version' => [
                            'sql' => "SELECT value FROM NeWorld WHERE setting = 'version'",
                        ],
                        'notice' => [
                            'sql' => "SELECT value FROM NeWorld WHERE setting = 'notice'",
                        ],
                    ],
                    'trans' => false,
                ]);

                // 返回通知信息
                if (empty($getData['product']['result']))
                {
                    $result['notice'] .= $ext->getSmarty([
                        'file' => 'tips/warning',
                        'vars' => [
                            'message' => '当前的 WHMCS 系统中没有已授权的产品，请在下方添加授权',
                        ],
                    ]);
                }
                else
                {
                    $result['notice'] .= $ext->getSmarty([
                        'file' => 'tips/info',
                        'vars' => [
                            'message' => '当前的 WHMCS 系统中共有 '.(int) trim($getData['product']['rows']).' 个已激活的授权许可',
                        ],
                    ]);
                }

                // 判断是否有 NeWorld Manager 新版
                if ($getData['version']['result']['value'] > $vars['version']) $result['notice'] .= $ext->getSmarty([
                    'file' => 'tips/warning',
                    'vars' => [
                        'message' => '当前系统检测到 NeWorld Manager 存在新的版本，版本号为: '.$getData['version']['result']['value'].'，您可以 <a target="_blank" href="https://neworld.org">前往官网</a> 下载最新的模块',
                    ],
                ]);

                // 返回通知
                $result['noticelist'] = json_decode($getData['notice']['result']['value'], true);

                // 返回给模板
                $result['product'] = $getData['product']['result'];

                // 遍历产品数组
                foreach ($result['product'] as $key => $value)
                {
                    try
                    {
                        // 读取产品在数据库中的缓存
                        $getData = $db->runSQL([
                            'action' => [
                                'cache' => [
                                    'sql' => 'SELECT * FROM NeWorldCache WHERE id = ?',
                                    'pre' => [$value['id']],
                                ],
                            ],
                            'trans' => false,
                        ]);

                        // 判断内容是否为空
                        if (empty($getData['cache']['result'])) throw new Exception('无法查找此产品在数据库中的缓存信息');

                        // 返回版本号
                        $result['product'][$key]['lastversion'] = $getData['cache']['result']['version'];

                        // 返回下载地址
                        $result['product'][$key]['download'] = $getData['cache']['result']['download'];

                        // 返回名称
                        $result['product'][$key]['name'] = base64_decode($result['product'][$key]['name']);

                        // 判断应该输出的按钮
                        if ($result['product'][$key]['version'] == '-')
                        {
                            // 返回按钮
                            $result['product'][$key]['button'] = 'install';
                        }
                        else
                        {
                            // 版本号运算
                            $version = $result['product'][$key]['lastversion'] - $result['product'][$key]['version'];

                            // 判断版本号新旧
                            if ($version > 0)
                            {
                                // 返回按钮
                                $result['product'][$key]['button'] = 'update';
                            }
                            else
                            {
                                // 返回按钮
                                $result['product'][$key]['button'] = 'reinstall';
                            }
                        }
                    }
                    catch (Exception $e)
                    {
                        // 销毁要返回的数组
                        unset($result['product'][$key]);

                        // 返回提示
                        $result['notice'] .= $ext->getSmarty([
                            'file' => 'tips/danger',
                            'vars' => [
                                'message' => '无法获取产品名称 [ '.$value['name'].' ] 在数据库中的缓存信息，错误信息: '.$e->getMessage(),
                            ],
                        ]);
                    }
                }

                // 把 $result 放入模板需要输出的变量组中
                $result = $ext->getSmarty([
                    'file' => 'manager',
                    'vars' => $result,
                ]);
            }
            catch (Exception $e)
            {
                // 输出错误信息
                $result = $ext->getSmarty([
                    'file' => 'tips/danger',
                    'vars' => [
                        'message' => $e->getMessage(),
                    ],
                ]);
            }
            finally
            {
                echo $result;
            }
        }
        catch (Exception $e)
        {
            // 如果报错则终止并输出错误
            die($e->getMessage());
        }
    }
}