<?php
// 引入文件
require __DIR__.'/library/class/NeWorld.Common.Class.php';

try
{
    // 实例化扩展类
    $ext = new NeWorld\Extended;

    try
    {
        // 列出 hooks 目录
        $hooks = $ext->getDirectory([
            'name' => 'library/hooks'
        ]);

        // 文件的平行列表
        $hookList = '';

        // 循环引入文件
        foreach ($hooks as $value)
        {
            // 为了防止 include_once 提示 "Dynamic include expression 'xxxx'.$value' is not analysed."，所以这里分割数组再自己拼接
            $value = explode('.', $value)['0'];

            // 引入文件
            require __DIR__.'/library/hooks/'.$value.'.php';

            // 拼接文件名
            $hookList .= $value.', ';
        }

        // 成功返回结果
        $result = '已成功载入 [ '.substr($hookList, 0, -2).' ] 等 Hooks 文件';
    }
    catch (Exception $e)
    {
        // 失败返回结果
        $result = '引入 Hooks 失败，错误信息: '.$e->getMessage();
    }
    finally
    {
        // 记录日志
        $ext->recordLog('NeWorld_Hooks', $result);
    }
}
catch (Exception $e)
{
    // 出错的话什么都不做
}