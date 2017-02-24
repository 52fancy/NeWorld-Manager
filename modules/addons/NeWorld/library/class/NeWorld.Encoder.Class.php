<?php
// 声明命名空间
namespace NeWorld;

// 引入文件
require_once __DIR__.'/NeWorld.Common.Class.php';

// 使用其他的类
use \Exception;

// 字符串编码类
if (!class_exists('Encoder'))
{
    class Encoder
    {
        // 属性
        private $key;

        // 构造方法
        public function __construct($key)
        {
            // 把传参赋值给 key 属性
            $this->key = (string) $key;

            // 判断是否为空
            if (empty($this->key)) throw new Exception('传递的密钥是空的');

            // 返回 true
            return true;
        }

        // 编码器(网上找的)
        public function coding($string, $encrypt = true)
        {
            $key = md5($this->key);
            $keyLength = strlen($key);
            $string = $encrypt ? substr(md5($string.$key), 0, 8).$string : base64_decode($string);
            $stringLength = strlen($string);
            $rndkey = $box = array();
            $result = '';
            for ($i=0;$i<=255;$i++)
            {
                $rndkey[$i] = ord($key[$i % $keyLength]);
                $box[$i] = $i;
            }
            for ($j=$i=0;$i<256;$i++)
            {
                $j = ($j + $box[$i] + $rndkey[$i]) % 256;
                $tmp = $box[$i];
                $box[$i] = $box[$j];
                $box[$j] = $tmp;
            }
            for ($a=$j=$i=0;$i<$stringLength;$i++)
            {
                $a = ($a + 1) % 256;
                $j = ($j + $box[$a]) % 256;
                $tmp = $box[$a];
                $box[$a] = $box[$j];
                $box[$j] = $tmp;
                $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
            }
            if(!$encrypt)
            {
                if (substr($result, 0, 8) == substr(md5(substr($result, 8).$key), 0, 8))
                {
                    return substr($result, 8);
                }
                else
                {
                    return '';
                }
            }
            else
            {
                return str_replace('=', '', base64_encode($result));
            }
        }
    }
}