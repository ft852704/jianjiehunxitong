<?php
namespace app\index\model;

use think\Model;

class Services extends Model
{

    // 验证规则
    protected $rule = [
        //项目字段
	            'parent_id| 项目类'   => 'require',
				'name|名称' => 'require',
				'time_long|时长'   => 'require',
				'technician|技师类型' => 'require',
				'star|星级'   => 'require',
				'price|价格'   => 'require',
				'is_discount|是否参与打折'     => 'require',
				'is_default|是否默认'     => 'require',
				'status|状态'     => 'require',
    ];
}