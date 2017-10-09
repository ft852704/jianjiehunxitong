<?php
namespace app\index\model;

use think\Model;

class Data extends Model
{
	protected $name = 'used_data';
    // 验证规则
    public $rule = [
	            //常用资料添加字段
	            'number|编号'   => 'require',
				'name|名称' => 'require',
				'status|状态'     => 'require',
	        ];
}