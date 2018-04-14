<?php
namespace app\index\validate;

use think\Validate;

class Company extends Validate
{
    // 验证规则
    protected $rule = [
        'nickname' => ['require', 'min'=>5, 'token'],
        'email'    => ['require', 'email'],
        'birthday' => ['dateFormat' => 'Y|m|d'],
    ];
}