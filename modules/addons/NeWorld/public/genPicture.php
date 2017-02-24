<?php
// 设置 WHMCS 常量
if (!defined('WHMCS')) define('WHMCS', '');

// 引入文件
require_once dirname(__DIR__).'/library/class/NeWorld.Common.Class.php';

try
{
    // 授权站点真实地址
    $verifySite = 'https://verify.neworld.org/';

    // 实例化编码类
    $encoder = new NeWorld\Encoder('0a34ce21ce04e3a49d95c20e5baf7987');

    // 图片路径
    $images = NeWorld.'/templates/default/assets/img/blank.gif';

    // 检测是否可读写
    if (is_readable($images) && is_writeable($images))
    {
        // 写入文件
        file_put_contents(
            $images, base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEHAAEALAAAAAABAAEAAAICTAEAO0ZVQ0tFUig=').
            $encoder->coding(base64_encode(gzdeflate(base64_encode($verifySite)))).');'
        );

        // 获取写入内容之后的图像
        $content = file_get_contents($images);

        // 匹配出对应的内容，赋值给 $contentAfter
        preg_match('/FUCKER(.*);/i', $content, $contentAfter);

        // 取出主要内容
        $contentAfter = substr($contentAfter['1'], 1, -1);

        // 获取真实的地址
        $reallySite = base64_decode(
            gzinflate(
                base64_decode(
                    $encoder->coding($contentAfter, false)
                )
            )
        );

        // 检测是否匹配
        if ($reallySite == $verifySite)
        {
            $result = 'Picture generated successfully!';
        }
        else
        {
            $result = 'Picture generated failed.';
        }
    }
    else
    {
        $result = 'Images path: [ '.$images.' ], No permission to manipulate image files!';
    }
}
catch (Exception $e)
{
    $result = $e->getMessage();
}
finally
{
    die($result);
}