<?php
// 引入文件
require dirname(__DIR__).'/class/NeWorld.Common.Class.php';

// 使用 ClientAreaHomepage 可测试，改为 DailyCronJob 可每日执行
add_hook('DailyCronJob', 1, function () {
    // 每日任务执行时间
    $actionTime = date('Y-m-d, H:i:s');

    // 实例化数据库类
    $db = new NeWorld\Database;

    // 实例化扩展类
    $ext = new NeWorld\Extended;

    try
    {
        // 读取数据库中得到 NeWorld 产品
        $getData = $db->runSQL([
            'action' => [
                'product' => [
                    'sql' => 'SELECT id,license FROM NeWorldProduct',
                    'all' => true,
                ],
            ],
            'trans' => false,
        ]);

        // 判断是否为空
        if (empty($getData['product']['result'])) throw new Exception('当前站点无已激活的 NeWorld 产品，跳过本次任务');

        // 遍历产品
        foreach ($getData['product']['result'] as $value)
        {
            try
            {
                // 销毁 SESSION 中关于此授权的数据
                unset($_SESSION['NeWorld'][$value['license']]);

                // 实例化授权类（实例化授权类的同时会返回授权状态并且自动更新授权信息和产品版本等等信息）
                new NeWorld\License($value['license'], true);

                // 记录日志
                $ext->recordLog('NeWorld_Manager', '授权许可 [ '.$value['license'].' ] 已完成授权更新');
            }
            catch (Exception $e)
            {
                // 记录日志
                $ext->recordLog('NeWorld_Manager', '每日任务执行失败，错误信息: '.$e->getMessage());

                // 跳出进行下一次循环
                continue;
            }
        }

        // 记录日志
        $ext->recordLog('NeWorld_Manager', '每日任务由 '.$actionTime.' 开始、至此已执行完毕');
    }
    catch (Exception $e)
    {
        // 记录日志
        $ext->recordLog('NeWorld_Manager', $e->getMessage());
    }
});