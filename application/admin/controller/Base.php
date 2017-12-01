<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;
use \think\Session;
use app\admin\model\CardLog;
use app\admin\model\ConsumptionLog;
use app\admin\model\MsgLog;

class Base extends Controller
{
	/*public function __construct(){
		Session::set('family_id',1); 
		Session::set('company_id',1);
		//Session::get('name');
	}*/
	public $msn = [
		 //短信验证码配置
	    'SMS_ACCOUNT'=>'N6420980',
	    'SMS_PWD'=>'1IblExO9zw64d9',
	    'SMS_SIGN'=>'【鼎族怡华】',
	];
	//职业人类型（1，策划师，2化妆师，3摄像师，4主持人,5摄影师）
	public $professional_type = [
		1 => '策划师',
		2 => '化妆师',
		3 => '摄像师',
		4 => '主持人',
		5 => '摄影师',
	];
	//1，未审核，2，已审核，3已完成，4，已生成订单
    public $bespeak_state = [
    	1 => '预约中',
    	2 => '预约成功',
    	3 => '见面成功',
    	4 => '已生成订单',
    	5 => '未见面',
    ];
    //订单状态（0未审核，1，未付款，2已付款,3已完成，4已退款）
    public $order_state = [
    	'未审核',
    	'未付款',
    	'已付款',
    	'已完成',
    	'已退款',
    ];
    //订单类型
    public $order_type = [
    	1 => '人员费订单',
    	2 => '布置费订单',
    ];
    public $sex = [
    	0 => '保密',
    	1 => '男',
    	2 => '女',
    ];
    //用户渠道
    public $from = [
    	1 => '自来',
    ];


	public $ic_key = 'dingzuyihua';

	public $app_url = 'api.dingzu.vip';

	public function _initialize()
    {
    	$request = request();
		//session存在时，不需要验证的权限
		$not_check = array('admin/Index/login','admin/Index/lost_password');
		//当前操作的请求 模块名/方法名
		if(in_array($request->module().'/'.$request->controller().'/'.$request->action(), $not_check)){
			return true;
		}
        //Session::set('family_id',1);
        //Session::set('family_name','何宇瀚');
		//Session::set('company_id',1);
		//Session::set('login_id',1);
		if(!Session::get('id')){
			$this->success('请登录', 'Index/login');
		}else{
			//检查账号权限
			/*if(!$this->is_power()){
				if($request->action()=='edit_business_documents'){

				}else{
					$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
				}
			}*/
			$role = Session::get('role');
			$this->assign('role',$role);
			$nick = Session::get('nick');
			$this->assign('nick',$nick);
		}
    }
    //获取公司下的所有门店
    //@ $company_id 公司ID
    //@ $status 状态 0:弃用状态，1：启用状态，2：全部状态
    public function getChildCompany($company_id=5,$status=2){
    	$data = array();
    	if($status==2){
    		$where = '';
    	}else{
    		$where = ' and status='.$status;
    	}
    	$re = Db::table('company')->where('id='.$company_id.$where)->find();
    	if($re['level']){
    		if($re['level']==1){
    			$list = Db::table('company')->field('id')->where('parent_id='.$company_id.$where)->select();
    			foreach ($list as $k => $v) {
    				$data[] = $v['id'];
    			}
    			return implode(',',$data);
    		}elseif($re['level']==2){
    			return $re['id'];
    		}
    	}else{
    		$data[] = $re['id'];
    		$re = Db::table('company')->field('id')->where('parent_id='.$re['id'].$where)->select();
    		foreach ($re as $key => $value) {
    			$data[] = $value['id'];
    			$data[] = $this->getChildCompany($value['id'],$status);
    		}
    		return implode(',',$data);
    	}
    }
    //获取指定大类的常用资料
    //@ $parent_id 父类ID（支持逗号隔开的字符串）
    public function getUsedData($parent_id=6){
    	if(is_numeric($parent_id)){
    		$where = [
    			'parent_id'=>$parent_id,
    			'status'=>1,
    		];
    		$list =Db::table('used_data')->field('id,name')->where($where)->select();
    	}else{
    		$where = [
    			'parent_id'=>['in',$parent_id],
    			'status'=>1,
    		];
    		$list =Db::table('used_data')->field('id,name')->where($where)->select();
    	}
    	return $list;
    }

    //获取门店列表
    public function getCompanyList($id=5,$status=2){
    	//if(Session::get('role')!=1){
    	//	$id = Session::get('company_id');
    	//}
    	$where = $this->getChildCompany($id,$status);
    	return Db::table('company')->field('full_name,id')->where('id','in',$where)->order('parent_id')->select();
    }

    //会员卡操作日志记录
    public function setCardLog($cardlogdata){
    	$member = Db::table('member')->find($cardlogdata['member_id']);
    	$company = Db::table('company')->find(Session::get('company_id'));
    	$mark = '';
    	switch ($cardlogdata['active_type']) {
    		case 1:
    			# code...
    			$mark = '开卡';
    			break;
    		case 2:
    			# code...
    			$mark = '充值';
    			break;
    		case 3:
    			# code...
    			$mark = '转卡';
    			break;
    		case 4:
    			# code...
    			$mark = '换卡';
    			break;
    		case 5:
    			# code...
    			$mark = '还款';
    			break;
    		case 6:
    			# code...
    			$mark = '创度会员移植';
    			break;
    		case 7:
    			# code...
    			$mark = '修改基本资料';
    			break;
    		case 8:
    			# code...
    			$mark = '元备注信息:'.$cardlogdata['remark'].'||修改为:'.$member['remark'];
    			break;
    		default:
    			# code...
    			break;
    	}
    	$data = [
    		'company' => Session::get('company_id'),
    		'company_name' => $company['full_name'],
    		'activer' => Session::get('family_id'),
    		'active_name' => Session::get('family_name'),
    		'mark' => $mark,
    		'time' => time(),
    		'member_id' => $cardlogdata['member_id'],
    		'member_name' => $member['name'],
    		'member_card' => $member['card_no'],
    		'active_type' => $cardlogdata['active_type'],
    	];
    	$cardlog = new CardLog;
    	$cardlog->save($data);
    }
    //钱包金额变动日志
    public function setConsumptionLog($consumptionlogdata){
    	$company = Db::table('company')->find(Session::get('company_id'));
    	$cashchangedata = [
		    			'company_id' => Session::get('company_id'),
		    			'company_name' => $company['full_name'],
		    			'activer_id' => Session::get('family_id'),
		    			'activer_name' => Session::get('family_name'),
		    			'wallet_type' => $consumptionlogdata['wallet_type'],
		    			'active_type' => $consumptionlogdata['active_type'],
		    			'order_no' => $consumptionlogdata['order_no'],
		    			'last_balance' => $consumptionlogdata['last_balance'],
		    			'this_balance' => $consumptionlogdata['this_balance'],
		    			'services_id' => $consumptionlogdata['services_id'],
		    			'services_count' => $consumptionlogdata['services_count'],
		    			'services_name' => $consumptionlogdata['services_name'],
		    			'pay_type' => $consumptionlogdata['pay_type'],
		    			'services_family_id' => isset($consumptionlogdata['family_id'])?$consumptionlogdata['family_id']:Session::get('family_id'),
		    			'services_family' =>isset($consumptionlogdata['family_name'])?$consumptionlogdata['family_name']:Session::get('family_name'),
		    			'member_id' => $consumptionlogdata['member_id'],
		    			'member_name' => $consumptionlogdata['member_name'],
		    			'member_no' => $consumptionlogdata['member_no'],
		    			'time' => time(),
		    			'cashier_id' => $consumptionlogdata['cashier_id'],
		    			'cash' => $consumptionlogdata['cash'],
		    		];
		$consumptionlog = new ConsumptionLog;
    	$consumptionlog->insert($cashchangedata);
    }
    //发送短信备份
    public static function send_bak($mobile,$msg){
		$post["userName"]=$this->msn['SMS_ACCOUNT'];
		$post["password"]=$this->msn['SMS_PWD'];
		$post["mobiles"]=$mobile;
		$post["content"]=$msg;
		$post["batchId"]="";
		
		$res=$this->https_post( "http://114.215.136.196/api/groupsms/sendMsg", $post);

		if($res["responseCode"]==1)
			return true;
		return false;
	}

	//发送短信
	public static function msnSend($mobile,$msg){
		return '20170912152104,0
17091215210425466';
		$msn = [
			 //短信验证码配置
		    'SMS_ACCOUNT'=>'N6420980',
		    'SMS_PWD'=>'1IblExO9zw64d9',
		    'SMS_SIGN'=>'【鼎族怡华】',
		];

		$post=array();
		$post['un'] = iconv('GB2312', 'GB2312', $msn['SMS_ACCOUNT']);
		$post['pw'] = iconv('GB2312', 'GB2312', $msn['SMS_PWD']);
		$post["phone"]=$mobile;
		$post["msg"]=$msg;
		$post["rd"]=1;
		$res=self::https_post( "http://sms.253.com/msg/send", $post);
		trace($res,'mms');
		
		return $res;
	}
	//权限判定
	public function is_power($action = ''){
		if(Session::get('role')==1){
			$login_ace = model('ace')->where('parent_id > 0')->select();
		}else{
			//获取账号权限
			$login_ace = model('login_ace')->alias('l')->field('a.*')->join('ace a','l.ace_id=a.id','left')->where(['l.login_id'=>Session::get('login_id')])->select();

			if(empty($login_ace)){
				//获取角色权限
				$login_ace = model('role_ace')->alias('r')->field('a.*')->join('ace a','r.ace_id=a.id','left')->where(['r.role_id'=>Session::get('role')])->select();
				if(empty($login_ace)){
					return false;
				}
			}
		} 

		$power = [];
		foreach ($login_ace as $k => $v) {
			$power[] = strtolower($v['control']).'/'.strtolower($v['action']);
			if($v['about_action']){
				foreach (explode(',', $v['about_action']) as $key => $value) {
					$power[] = strtolower($v['control']).'/'.strtolower($value);
				}
			}
		}
		$power = array_unique($power);
		//将权限输出到模板
		$this->assign('power',$power);
		$request = request();
		$action = $action?$action:strtolower($request->controller()).'/'.strtolower($request->action());
		
		if(in_array(strtolower($action), $power)){
			return true;
		}else{
			return false;
		}
	}
    //curl请求
    public static function https_post($url,$data){
		$fields_string="";
		$count=1;
		if(is_array($data)){
			foreach($data as $key=>$v) { $fields_string .= $key.'='.$v.'&' ; }
			$fields_string=rtrim($fields_string ,'&') ;
			$count=count($data);
		}else{
			$fields_string=$data;
		}
		$ch = curl_init($url);
		// curl_setopt($ch, CURLOPT_ENCODING ,'utf-8');
		curl_setopt($ch, CURLOPT_POST,count($data)) ;
		curl_setopt($ch, CURLOPT_POSTFIELDS,$fields_string) ;
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true) ; // 获取数据返回
			$res=curl_exec($ch);//json_decode(curl_exec($ch),true);
			$error = curl_error($ch);
			$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch) ;
			return $res;
	}
	/*短信日志
		@param type 日志类型，1：消费验证码，2：收银结束语，3：充值结束语
		@param content 短信内容
		@param status 发送状态 1：成功  0：失败
		@param mobile_phone 接收短信手机号
		@param company_id 发送公司
		@param family_id 发送人
	*/
    public static function msgLog($data=[]){
    	$save = [
    		'type' => $data['type'],
    		'content' => $data['content'],
    		'time' => time(),
    		'status' => $data['status'],
    		'mobile_phone' => $data['phone'],
    		'company_id' => Session::get('company_id'),
    		'family_id' => Session::get('login_id'),
    		're_code' => $data['re_code'],
    	];
    	$ml = new MsgLog;
    	return $ml->save($save);
    }
    
}
