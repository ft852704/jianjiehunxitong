<?php
namespace app\index\model;

use think\Model;

class Company extends Model
{

    // 验证规则
    protected $rule = [
        //公司添加字段
	            'number|公司编号'   => 'require',
				'full_name|中文全称' => 'require',
				'short_name|中文简称'   => 'require',
				'telephone|电话'   => 'require',
				'level|公司级别'     => 'require',
				'member_card_level|会员卡级别'     => 'require',
				'address|公司地址'     => 'require|min:5',
				'status|状态'     => 'require',
    ];
}