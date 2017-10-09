<?php
namespace app\index\model;

use think\Model;

class Family extends Model
{

    // 验证规则
    protected $rule = [
        //员工资料
	            'number|员工编号'   => 'require',
				'name|员工名称' => 'require',
    ];
}