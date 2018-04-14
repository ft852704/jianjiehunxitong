<?php
namespace app\professional\controller;
use think\Controller;
use think\Db;
use \think\Session;
use app\professional\model\CardLog;
use app\professional\model\ConsumptionLog;
use app\professional\model\MsgLog;

class Base extends Controller
{
	/*public function __construct(){
		Session::set('family_id',1); 
		Session::set('company_id',1);
		//Session::get('name');
	}*/
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
    public $sex = [
    	0 => '保密',
    	1 => '男',
    	2 => '女',
    ];
    public $role = [
    	'保密',
    	'新郎',
    	'新娘',
    ];
    //订单状态（0未审核，1，未付款，2已付款,3已完成，4已退款）
    public $order_state = [
    	'未审核',
    	'未付款',
    	'已付款',
    	'已完成',
    	'已退款',
    ];
    //平台佣金率
    public $pepole_tax = 0.05;
	public function _initialize()
    {
    	$request = request();
		//session存在时，不需要验证的权限
		$not_check = array('professional/login','professional/lost_password');
		//当前操作的请求 模块名/方法名
		if(in_array($request->module().'/'.$request->action(), $not_check)){
			return true;
		}
        //Session::set('family_id',1);
        //Session::set('family_name','何宇瀚');
		//Session::set('company_id',1);
		//Session::set('login_id',1);
		if(!Session::get('professional_id')){
			$this->success('请登录', 'Index/login');
		}
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
