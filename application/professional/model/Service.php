<?php
namespace app\index\model;

use think\Model;

class Service extends Model
{

    // 验证规则
    protected $rule = [
        //项目字段
	            'number|项目编号'   => 'require|unique',
				'name|中文全称' => 'require|chs',
				'short_name|中文简称'   => 'require|chs',
				'type|项目类型'   => 'require',
				'technician|服务类型'     => 'require',
				'company_id|所属公司'     => 'require',
				'count|服务人数'     => 'require|max:1|number',
				'status|状态'     => 'require',
    ];
}