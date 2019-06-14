<?php
namespace tinymeng\uploads\Helper;

class Str
{
    public static function uFirst($str)
    {
        return ucfirst(strtolower($str));
    }

    public static function random($length = 16)
    {
        $str_pol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        return substr(str_shuffle($str_pol), 0, $length);
    }

}
