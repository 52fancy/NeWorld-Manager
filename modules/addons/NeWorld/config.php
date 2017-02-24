<?php
// NeWorld 模块配置文件
$NeWorld = [
    'version' => '1.3',          // 本地版本
    'log' => [
        'record' => false,       // 是否开启
        'directory' => 'log',    // 日志目录
        'file' => 'NeWorld.log', // 日志文件名
    ],
    'templates' => 'default',    // 模板选择
    'nameSpace' => [             // 允许加载类的命名空间
        'NeWorld',
        'yourNameSpace',
    ],
];