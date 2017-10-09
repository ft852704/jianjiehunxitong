<?php
namespace app\api\controller;
use think\Controller;
use think\Db;
use \think\Session;

class Base extends Controller
{
	/*public function __construct(){
		Session::set('family_id',1); 
		Session::set('company_id',1);
		//Session::get('name');
	}*/
	public function _initialize()
    {
        Session::set('family_id',1); 
		Session::set('company_id',1);
    }
    public function index1()
    {
        return 'hello world1';
    }
    //获取公司下的所有门店
    //@ $company_id 公司ID
    public function getChildCompany($company_id=1){
    	$data = array();
    	$re = Db::table('company')->where('id='.$company_id)->find();
    	if($re['level']){
    		if($re['level']==1){
    			$list = Db::table('company')->field('id')->where('parent_id='.$company_id)->select();
    			foreach ($list as $k => $v) {
    				$data[] = $v['id'];
    			}
    			return implode(',',$data);
    		}elseif($re['level']==2){
    			return $re['id'];
    		}
    	}else{
    		$data[] = $re['id'];
    		$re = Db::table('company')->field('id')->where('parent_id='.$re['id'])->select();
    		foreach ($re as $key => $value) {
    			$data[] = $value['id'];
    			$data[] = $this->getChildCompany($value['id']);
    		}
    		return implode(',',$data);
    	}
    }
    //获取指定大类的常用资料
    //@ $parent_id 父类ID（支持逗号隔开的字符串）
    public function getUsedData($parent_id=6){
    	if(is_numeric($parent_id)){
    		$list =Db::table('used_data')->field('id,name')->where('parent_id='.$parent_id)->select();
    	}else{
    		$list =Db::table('used_data')->field('id,name')->where('parent_id','in',$parent_id)->select();
    	}
    	return $list;
    }

    //获取门店列表
    public function getCompanyList($id=1){
    	$where = $this->getChildCompany($id);
    	return Db::table('company')->field('full_name,id')->where('id','in',$where)->order('parent_id')->select();
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
	//消费订单
	public function createOrder(){
		$data = [
			'app_order_name' => '',//APP订单名称
			'app_order_id' => 1198,//APP订单id
			'card_no' => 'lihaiceshi',//会员卡号
			'company_id' => 2,//公司ID
			'sex' => 0,//性别 1男，2女
			'count' => 1,//客数
			'pay_way' => 7,//支付方式，现金11，支付宝18，银行卡15，微信19，储值账户7(如果会员卡买单pay_way只会等于7)
			'order_id' => 1212,//APP订单id
			'services' => [
				[
					'services_id' => 1719,
					'fworker' => '612',//第一个服务康复师
					'sworker' => 0,//第二个康复师
					'discount' => 0.55,//折扣
					'count' => 1,//服务数量
				],
				[
					'services_id' => 1722,
					'fworker' => '612',//第一个服务康复师
					'sworker' => 522,//第二个康复师
					'discount' => 0.55,//折扣
					'count' => 1,//服务数量
				]
			]
		];
		$url = 'http://211.149.247.27:81/api/index/index/?action=GenerateOrder';
		echo self::https_post($url,['data'=>json_encode($data)]);
	}
	//其他类型订单
	public function createOtherOrder1(){
		$data = [
			'app_order_id' => 444,//APP订单id
			'card_no' => '0280661VIP',//会员卡号
			'company_id' => 1,//公司ID
			'sex' => 1,//性别 1男，2女
			'count' => 2,//客数
			'pay_way' => 18,//支付方式，现金11，支付宝18，银行卡15，微信19，储值账户7(如果会员卡买单pay_way只会等于7)
			'services' => [
				[
					'services_id' => 3175,//子项目id
					'fworker' => '1303',//第一个服务康复师
					'sworker' => 0,//第二个康复师
					'discount' => 0.7,//折扣
					'count' => 1,//服务数量
				]
			]
		];
		$url = '211.149.247.27:81/api/index/index/?action=createOtherOrder';
		echo self::https_post($url,['data'=>json_encode($data)]);
	}
}
