<?php
namespace app\api\controller;
use app\api\controller\Base;
use think\Db;
use \think\Session;
use app\index\model\Family;
use app\index\model\Data;
use app\index\model\FamilyServices;
use app\index\model\Service;
use app\index\model\Services;
use app\index\model\Wallet;
use app\index\model\MemberCard as MC;
use app\index\model\MemberCardType as MCT;
use app\index\model\CardTypeChildren as CTC;
use app\index\model\Cashier;
use app\index\model\Cashiers;
use app\index\model\AppConsumptionLog as ACL;
use app\index\model\Company;
use app\index\model\ConsumptionLog;
use think\Request;

class Index extends Base
{
	public $params;
	public $star = [3=>53,4=>54,1=>55,2=>56,0=>0];
    public $teachnician = [1=>27,2=>28,3=>29];

	public function _initialize()
    {
        
    }
    public function returnJoin(){

    }
    //入口
    public function Index(){
    	$this->params = $param = input();
    	if(isset($param['action'])&&$param['action']){
    		$return = $this->$param['action']();
    	}else{
    		$return = array('resp'=>1,'msg'=>'请求参数错误');
    	}
    	if(!isset($return['params']))$return['params']=[];
    	echo json_encode($return);
    }
    //获取技师能服务的项目
    protected function getFamilyService(){
    	if(isset($this->params['family_id'])&&$this->params['family_id']){
    		$family = model('family')->get($this->params['family_id']);
    		$re = Db::table('family_services')->where('family='.$this->params['family_id'])->find();
    		$services = [];
    		if($re){
    			//获取有绑定项目的技师能做的项目数据
    			$familyServices = new FamilyServices;
    			$where = [
    				'fs.family' => $this->params['family_id'],
    				's.company_id' => $family['company'],
    				's.status' => 1
    			];
    			$service = $familyServices->alias('fs')->join('service s','fs.service=s.id','left')->where($where)->group('fs.service')->order('s.sort ASC')->select();
    			foreach ($service as $k => $v) {
    				if($v['image']){
    					$v['image'] = 'http://'.$_SERVER['HTTP_HOST'].'/'.substr($v['image'],2);
    				}
    				$services[$k] = [
    					'service_name' => $v['name'],
    					'company_id' => $v['company_id'],
    					'service_id' => $v['service'],
    					'count' => $v['count'],
    					'image' => $v['image'],
    				];
    				$wheres = [
    					'fs.family' => $this->params['family_id'],
    					'fs.service' => $v['service'],
    					's.status' => 1,
    					's.time_long' => array('>','0'),
    				];
    				$servic = $familyServices->alias('fs')->join('services s','fs.services=s.id','left')->where($wheres)->select();
    				foreach ($servic as $key => $value) {
    					$services[$k]['list'][$key] = [
    						'service_name' => $v['name'],
    						'services_id' => $v['services'],
    						'services_time_long' => $value['time_long'],
    						'service_price' => $value['price'],
    						'service_app_visitor_price' => $value['app_price'],
    						'services_is_discount' => $value['is_discount'],
    					];
    				}
    				if(!isset($services[$k]['list'])){
    					unset($services[$k]);
    				}
    			}
    			$tempservices = [];
    			foreach ($services as $key => $value) {
    				$tempservices[] = $value;
    			}
    			return array('resp'=>0,'msg'=>'请求成功','params'=>$tempservices);
    		}else{
				//获取没有绑定项目的技师能做的项目数据
				return array('resp'=>0,'msg'=>'该技师无可服务项目');
    		}
    	}else{
    		return array('resp'=>1,'msg'=>'请求参数错误');
    	}
    }
    //根据门店获取该门店所有有技师服务的项目
    protected function getCompanyServices(){
    	if(isset($this->params['company_id'])&&is_numeric($this->params['company_id'])){
    		$familyServices = new FamilyServices;
    		$service = $familyServices->alias('fs')->join('service s','fs.service=s.id','left')->group('fs.service')->order('s.sort ASC')->where('s.company_id='.$this->params['company_id'].' and s.status=1')->select();
    		$services = [];
    		$this->star = array_flip($this->star);
    		foreach ($service as $k => $v) {
    			if($v['image']){
    				$v['image'] = 'http://'.$_SERVER['HTTP_HOST'].'/'.substr($v['image'],2);
    			}
    			$services[$k] = [
    				'service_name' => $v['name'],
    				'service_id' => $v['service'],
    				'company_id' => $v['company_id'],
    				'count' => $v['count'],
    				'image' => $v['image'],
    				'list' => []
    			];
    			$serviceslists = new Services;
    			$serviceslist = $serviceslists->field('s.*,f.id as fid')->alias('s')->join('family_services fs','s.id=fs.services','right')->join('family f','fs.family=f.id','left')->where('fs.service='.$v['service'].' and s.status=1 and f.status=1 and f.company='.$v['company_id'].' and s.time_long>0')->group('s.time_long')->select();

    			foreach ($serviceslist as $key => $value) {
    				$services[$k]['list'][$key] = [
    					'service_name' => $v['name'],
    					'time_long' => $value['time_long'],
    				];
    			}
    			if(empty($serviceslist)){
    				unset($services[$k]);
    			}
    		}
    		$tempservices = [];
    		foreach ($services as $key => $value) {
    			$tempservices[] = $value;
    		}
    		return array('resp'=>0,'msg'=>'请求成功','params'=>$tempservices);
    	}else{
    		return array('resp'=>1,'msg'=>'请求参数错误');
    	}
    }
    //根据门店获取该门店所有有技师服务的项目(有APP散客价的项目)
    protected function getCompanyVisitorServices(){
    	if(isset($this->params['company_id'])&&is_numeric($this->params['company_id'])){
    		$familyServices = new FamilyServices;
    		$service = $familyServices->field('s.*,fs.*')->alias('fs')->join('service s','fs.service=s.id','left')->join('services ss','ss.parent_id=s.id','left')->group('fs.service')->order('s.sort ASC')->where('s.company_id='.$this->params['company_id'].' and s.status=1 and ss.app_price>0')->select();
    		$services = [];
    		$this->star = array_flip($this->star);
    		foreach ($service as $k => $v) {
    			if($v['image']){
    				$v['image'] = 'http://'.$_SERVER['HTTP_HOST'].'/'.substr($v['image'],2);
    			}
    			$services[$k] = [
    				'service_name' => $v['name'],
    				'service_id' => $v['service'],
    				'company_id' => $v['company_id'],
    				'count' => $v['count'],
    				'image' => $v['image'],
    				'list' => []
    			];
    			$serviceslists = new Services;
    			$serviceslist = $serviceslists->field('s.*,f.id as fid')->alias('s')->join('family_services fs','s.id=fs.services','right')->join('family f','fs.family=f.id','left')->where('fs.service='.$v['service'].' and s.status=1 and f.status=1 and f.company='.$v['company_id'].' and s.time_long>0 and s.app_price>0')->group('s.time_long')->select();

    			foreach ($serviceslist as $key => $value) {
    				$services[$k]['list'][$key] = [
    					'service_name' => $v['name'],
    					'time_long' => $value['time_long'],
    					'service_price' => $value['price'],
    					'service_app_visitor_price' => $value['app_price'],
    				];
    			}
    			if(empty($serviceslist)){
    				unset($services[$k]);
    			}
    		}
    		$tempservices = [];
    		foreach ($services as $key => $value) {
    			$tempservices[] = $value;
    		}
    		return array('resp'=>0,'msg'=>'请求成功','params'=>$tempservices);
    	}else{
    		return array('resp'=>1,'msg'=>'请求参数错误');
    	}
    }
    //根据某个门店的某个项目，筛选哪些技师能做
    protected function getFamilyOfServices(){
    	if(isset($this->params['service_id'])&&isset($this->params['time_long'])){
    		$familyservices = new FamilyServices;
    		$where = [
    			's.time_long' => $this->params['time_long'],
    			's.parent_id' =>$this->params['service_id'],
    			'f.status' => 1,
    			's.status' => 1,
    			'se.status' => 1,
    		];
    		$list = $familyservices->field('f.*,s.name as sname,s.star,s.price,se.count,s.is_discount,s.app_price,s.id as services_id')->alias('fs')->join('family f','fs.family = f.id','left')->join('services s','fs.services = s.id','left')->join('service se','fs.service = se.id','left')->where($where)->group('f.id')->select();
    		$familylist = [];
    		$this->star = array_flip($this->star);
    		foreach ($list as $key => $value) {
    			$familylist[$key] = [
    				'service_name' => $value['sname'],
    				'js_name' => $value['name'],
    				'jid' => $value['id'],
    				'star' => $value['star']?$this->star[$value['star']]:$value['star'],
    				'service_price' => $value['price'],
    				'service_app_visitor_price' => $value['app_price'],
    				'js_no' => $value['number'],
    				'count' => $value['count'],
    				'js_year' => $value['js_year'],
    				'is_discount' => $value['is_discount'],
    				'services_id' => $value['services_id'],
    			];
    		}
    		return array('resp'=>0,'msg'=>'请求成功','params'=>$familylist);
    	}else{
    		return array('resp'=>1,'msg'=>'请求参数错误');
    	}
    }
    //获取子项目数据
    protected function getServicesInfo(){
    	if(isset($this->params['services_id'])&&is_numeric($this->params['services_id'])){
    		$services = new Services;
    		$services = $services->get($this->params['services_id']);

    		$this->star = array_flip($this->star);
    		$services_info = [
    			'parent_id' => $services['parent_id'],
    			'name' => $services['name'],
    			'time_long' =>$services['time_long'],
    			'price' => $services['price'],
    			'app_visitor_price' => $services['app_price'],
    			'star' => $this->star[$services['star']],
    			'status' => $services['status'],
    			'is_discount' => $services['is_discount'],
    		];
    		return array('resp'=>0,'msg'=>'请求成功','params'=>$services_info);
    	}else{
    		return array('resp'=>1,'msg'=>'请求参数错误');
    	}
    }
    //根据手机号查询会员卡信息
    //@ mobile_phone : 13313221123
    protected function getMemberCardByPhone(){
    	if(is_numeric($this->params['mobile_phone'])){
    		$members = MC::alias('mc')->field('mc.*,c.full_name as cname')->join('company c','mc.company_id=c.id','left')->where(['mobile_phone'=>$this->params['mobile_phone']])->select();
    		$list = [];
    		$dataUse = Data::where(['parent_id'=>52,'status'=>1])->select();
    		$star = [];
    		foreach ($dataUse as $k => $v) {
    			$star[$v['id']] = $v['name'];
    		}
    		foreach ($members as $k => $v) {
    			$card_info = MCT::alias('mct')->join('card_type_children ctc','mct.id = ctc.parent_id','left')->where(['mct.id'=>$v['card_type'],'ctc.pay_id'=>7])->find();
    			$wallet = Wallet::where(['member_id'=>$v['id']])->limit(50)->find();
    			if(isset($this->params['log'])&&$this->params['log']==1){
    				$app_consumption_log = ACL::where(['card_no'=>$v['card_no'],'status'=>1])->order('id DESC')->select();
	    			$loglist = [];
	    			foreach ($app_consumption_log as $key => $value) {
	    				$loglist[$key] = json_decode($value['text'],true);
	    				if(isset($loglist[$key]['services'])){
	    					foreach ($loglist[$key]['services'] as $ke => &$val) {
		    					$val['star'] = $star[$val['star']];
		    				}
	    				}
	    			}
    			}
    			//查询该卡最后一次消费
    			$cashier = Cashier::where(['member_id'=>$v['id']])->order('time desc')->find();
    			$list[$k] = [
    				'card_id' => $v['id'],
    				'card_no' => $v['card_no'],
    				'name' => $v['name'],
    				'card_name' => $card_info['name'],
    				'tax' => $card_info['discount'],
    				'cash' => $wallet['cash'],
    				'company_id' => $v['company_id'],
    				'company_name' => $v['cname'],
    				'company_area' => $v['company_area'],
    				'start_time' => $v['start_time'],
    				'status' => $v['status'],//卡状态（1：已开卡，2：已换卡，3：已转卡，4：已到期）
    				'mobile_phone' => $v['mobile_phone'],
    				'sex' => $v['sex'],
    				'last_consumption_time' => $cashier['time']?$cashier['time']:0, //最后消费时间
    				'last_consumption' => $cashier['real_money']?$cashier['real_money']:0, //最后消费金额
    			];
    			if(isset($this->params['log'])&&$this->params['log']==1){
    				$list[$k]['logs'] = $loglist;
    			}
    		}
    		return array('resp'=>0,'msg'=>'请求成功','params'=>$list);
    	}else{
    		return array('resp'=>1,'msg'=>'请求参数错误');
    	}
    }
    //消费记录详情
    protected function getMemberConsumptionLog(){
    	$level = [
    		53 => '高级',
    		54 => '特级',
    		55 => '四星',
    		56 => '六星',
    	];
    	$payway = [
    		7 => '会员卡',
    		11 => '现金',
    		18 => '支付宝',
    		15 => '银行卡',
    		19 => '微信',
    		8 => '电子钱包',
    		9 => '老疗程账户'
    	];

    	if(isset($this->params['card_no'])&&$this->params['card_no']){
    		$app_consumption_log = ACL::where(['card_no'=>$this->params['card_no']])->order('id DESC')->select();
    		$loglist = [];
	    	foreach ($app_consumption_log as $key => $value) {
	    		$loglist[$key] = json_decode($value['text'],true);
	    		foreach ($loglist[$key] as $k1 => &$v1) {
	    			if($k1=='payway_id'){
	    				$v1 = $payway[$v1];
	    				$loglist[$key]['payway'] = $v1;
	    			}
	    			if($k1=='services'){
	    				foreach ($v1 as $k2 => &$v2) {
	    					$v2['star'] = $level[$v2['star']];
	    					$v2['is_app'] = 0;
	    					$v2['discount_value'] = isset($v2['discount_value'])?(float)$v2['discount_value']:1;
	    					$v2['time'] = isset($v2['time'])&&$v2['time']&&$v2['time']>0?$v2['time']:strtotime('2017-08-11 12:11:31');
	    				}
	    			}
	    		}
	    	}
	    	return array('resp'=>0,'msg'=>'请求成功','params'=>$loglist);
    	}else{
    		return array('resp'=>1,'msg'=>'请求参数错误');
    	}
    }
    //根据会员卡号获取消费日志

    //非项目消费(非充值)订单生成
    protected function createOtherOrder(){
    	//app需要的消费日志
    	$company = Company::get($this->params['company_id']);
    	$aclogdata = [
    		'cashier_id' => '',
    		'time' => time(),//下单时间
    		'company_id' => $this->params['company_id'],//消费门店
    		'company_name' => $company['full_name'],//消费门店名字
    		'order_no' => '',//单号
    		'app_order_id' => $this->params['app_order_id'],
	    	'app_order_name' => $this->params['app_order_name']?$this->params['app_order_name']:'',
    		'card_no' => $this->params['card_no'],
    		'order_type' => $this->params['order_type'],//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
    	];
    	if($this->setAppConsumptionLog($aclogdata)){
    		return array('resp'=>0,'msg'=>'请求成功','params'=>[]);
    	}else{
    		return array('resp'=>1,'msg'=>'请求参数错误');
    	}
    	
    }
    //项目消费订单生成
    protected function GenerateOrder(){
    	$this->params = json_decode($this->params['data'],true);
    	if($this->params['card_no']){
    		//会员买单
    		//获取会员基本数据
    		$member_new = $member = MC::where(['card_no'=>$this->params['card_no']])->find();
    		$wallet_new = $wallet = Wallet::where(['member_id'=>$member['id']])->find();
    		$discount = 0;
    		$cashmsg = '该卡余额不足';
    		//判断会员卡是否有欠款未还
		        if($wallet['arrears_c']==1&&$member['from']==1){
		        	$use = Db::table('used_data')->where('id=80')->find();
			        if($use['status']){
			        	//上次充值金额
			        	$last_cashier = Cashier::where(['member_no'=>$data['card_no'],'order_type'=>['in','2,3,4']])->order('time DESC')->limit(1)->find();
			        	//换算出高折扣卡类型的折扣值
			        	$card = json_decode($this->getCTbyRecharge_transfercard($last_cashier['real_money'],0,$this->params['card_no']),true);
				        if($card['status']==1){
				        	if(count($card['data'])==1){
	    						$discount = $card['data'][0]['discount'];
				        	}elseif(count($card['data'])>1){
				        		//获取折扣值最大的折扣
				        		$discount = array_search(max(array_column($card['data'],'discount')),$card['data']);
				        	}else{
				        		//没有对应卡类型
				        		return array('resp'=>1,'msg'=>'没有对应的卡类型进行计算');
				        	}
				        }else{
				        	//没有对应卡类型
				        	return array('resp'=>1,'msg'=>'没有对应的卡类型进行计算');
				    	}
			        }
    				$cashmsg = '该卡有欠款，本次预约将以'.($discount*10).'折扣下订单，到店还款后该单会重新按此卡折扣下单';
		        }
    		$total = 0;
    		$cashier = new Cashier;
    		$cashiers = new Cashiers;
    		//项目数据
    		foreach ($this->params['services'] as $k => $v) {
    			$v['discount'] = $discount?$discount:$v['discount'];
    			//子项目数据
    			$fworker = Family::where(['number'=>$v['fworker']])->find();
		        $sworker = Family::where(['number'=>$v['sworker']])->find();
		        $services = Services::alias('ss')->field('ss.name as ssname,s.name as sname,s.count as count,ss.*')->join('service s','ss.parent_id=s.id','left')->where(['ss.id'=>$v['services_id']])->find();
    			$cashiersdata[$k] = [
		        	'services_name' => $services['ssname'],
		        	'service_name' => $services['sname'],
		        	'services_id' => $services['id'],
		        	'service_id' => $services['parent_id'],
		        	'star' => $services['star'],
		        	'time_long' => $services['time_long'],
		        	'deductible_pay' => 0,
		        	'deductible' => 0,
		        	'pay' => 7,
		        	'standard_price' => $services['price'],
		        	'discount' => $services['is_discount']?round($services['price']*$v['discount']):$services['price'],//判定是否打折
		        	'count' => $v['count'],
		        	'fworker_id' => $fworker['id'],
		        	'ftype' => 1,
		        	'ftype_name' => '点钟',
		        	'fworker' => $fworker['number'], 
		        	'sworker_id' => isset($sworker['id'])?$sworker['id']:0,
		        	'stype' =>  1,
		        	'stype_name' => '点钟',
		        	'sworker' => isset($sworker['number'])?$sworker['number']:'',
		        	'service_price' => $services['price'],
		        	'discount_value' => $v['discount'],
		        	'total' => $services['is_discount']?round($services['price']*$v['count']*$v['discount']):round($services['price']*$v['count']),//判定是否打折
		        	'is_app' => 1,
		        ];
		        $total+= round($services['price']*$v['count']*$v['discount']);
    		}
    		//验证储值账户余额是否足够支付订单
    		if($total>$wallet['cash']){
    			return array('resp'=>1,'msg'=>$cashmsg);
    		}
    		//订单数据
		    $cashierdata = [
		       	'company_id' => $this->params['company_id'],
		       	'type' => 1,
		       	'number' => '',//app订单单号为空
		       	'member_id' => $member['id'],
		       	'member_no' => $member['card_no'],
		       	'sex' => $this->params['sex'],
		       	'count' => $this->params['sex']==1?$this->params['count']:0,
		       	'girl_count' => $this->params['sex']==2?$this->params['count']:0,
		       	'remark' => 'APP散客项目消费',
		       	'real_money' => round($total),
		       	'time' => time(),
		       	'active_id' => 0,
		       	'order_type' => 1,
		       	'should_money' => $total,
		       	'status' => 1,
		       	'app_order_id' => $this->params['app_order_id'],
		       	'is_confirm' => 0,
		    ];
		    if(!$cashier->save($cashierdata)){
		    	return array('resp'=>1,'msg'=>'父订订单保存出错');
		    }
		    foreach ($this->params['services'] as $k => $v) {
    			//子项目数据
    			$cashiersdata[$k]['parent_id'] = $cashier['id'];
    		}
    		if(!$cashiers->saveAll($cashiersdata)){
    			return array('resp'=>1,'msg'=>'子订单保存出错');
    		}
    		$wallet_new->cash = $wallet_new->cash-$total;
    		if(!$wallet_new->save()){
    			return array('resp'=>1,'msg'=>'钱包扣款出错');
    		}
    		//app需要的消费日志
    		$company = Company::get($this->params['company_id']);
    		$aclogdata = [
    			'cashier_id' => $cashier->id,
    			'time' => time(),//下单时间
    			'discount_money' =>  $total, //消费金额
    			'company_id' => $this->params['company_id'],//消费门店
    			'company_name' => $company['full_name'],//消费门店名字
    			'discount' => $discount?$discount:$this->params['services'][0]['discount'],//折扣
    			'coupon' => '',//优惠券
    			'order_no' => '',//单号
    			'payway' => '会员卡', //支付方式
    			'payway_id' => 7,//支付方式
    			'pay_time' => time(),//支付时间
    			'pay_status' => 0,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
    			'app_order_id' => $this->params['app_order_id'],
	    		'app_order_name' => isset($this->params['app_order_name'])?$this->params['app_order_name']:$cashiersdata[0]['service_name'],
    			'card_no' => $member['card_no'],
    			'order_type' => 1,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    		'cashier_type' => 1,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
    			'services' => $cashiersdata,
    		];
    		$this->setAppConsumptionLog($aclogdata);
    		//钱包金额变动表数据
    		$cashchangedata = [
		    	'wallet_type' => 7,
			    'active_type' => 1,
				'order_no' => '',
			    'last_balance' => $wallet['cash'],
			    'this_balance' => $wallet_new['cash'],
			    'services_id' => $services['id'],
			    'services_count' => $services['count'],
			    'services_name' => $services['sname'],
			    'pay_type' => 7,
			    'pay_name' => 1,
			    'member_id' => $member->id,
			    'member_name' => $member->name,
			    'member_no' => $member->card_no,
			    'cashier_id' => $cashier->id,
			    'cash' => $total,
		    ];
		    $this->setConsumptionLog($cashchangedata);
		    return array('resp'=>0,'msg'=>'订单生成成功','params'=>['cashier_id'=>$cashier->id,'cash'=>$wallet_new['cash']]);
    	}else{
    		//非会员买单
    		//散客买单
    		$total = 0;
    		$cashier = new Cashier;
    		$cashiers = new Cashiers;
    		//项目数据
    		foreach ($this->params['services'] as $k => $v) {
    			//子项目数据
    			$fworker = Family::where(['number'=>$v['fworker']])->find();
		        $sworker = Family::where(['number'=>$v['sworker']])->find();
		        $services = Services::alias('ss')->field('ss.name as ssname,s.name as sname,s.count as count,ss.*')->join('service s','ss.parent_id=s.id','left')->where(['ss.id'=>$v['services_id']])->find();
    			$cashiersdata[$k] = [
		        	'services_name' => $services['ssname'],
		        	'service_name' => $services['sname'],
		        	'services_id' => $services['id'],
		        	'service_id' => $services['parent_id'],
		        	'star' => $services['star'],
		        	'time_long' => $services['time_long'],
		        	'deductible_pay' => 0,
		        	'deductible' => 0,
		        	'pay' => 7,
		        	'standard_price' => $services['price'],
		        	'discount' => $services['is_discount']?round($services['price']*$v['discount']):$services['price'],//判定是否打折
		        	'count' => $v['count'],
		        	'fworker_id' => $fworker['id'],
		        	'ftype' => 1,
		        	'ftype_name' => '点钟',
		        	'fworker' => $fworker['number'], 
		        	'sworker_id' => isset($sworker['id'])?$sworker['id']:0,
		        	'stype' =>  1,
		        	'stype_name' => '点钟',
		        	'sworker' => isset($sworker['number'])?$sworker['number']:'',
		        	'service_price' => $services['price'],
		        	'discount_value' => $v['discount'],
		        	'total' => $services['is_discount']?round($services['price']*$v['count']*$v['discount']):round($services['price']*$v['count']),//判定是否打折
		        	'is_app' => 1,
		        ];
		        $total+= round($services['price']*$v['count']*$v['discount']);
    		}
    		//订单数据
		    $cashierdata = [
		       	'company_id' => $this->params['company_id'],
		       	'type' => 2,
		       	'number' => '',//app订单单号为空
		       	'member_id' => '',
		       	'member_no' => '',
		       	'sex' => $this->params['sex'],
		       	'count' => $this->params['sex']==1?$this->params['count']:0,
		       	'girl_count' => $this->params['sex']==2?$this->params['count']:0,
		       	'remark' => 'APP散客项目消费',
		       	'real_money' => round($total),
		       	'time' => time(),
		       	'active_id' => 0,
		       	'order_type' => 1,
		       	'should_money' => $total,
		       	'status' => 1,
		       	'app_order_id' => $this->params['order_id'],
		       	'is_confirm' => 0,
		    ];
		    if(!$cashier->save($cashierdata)){
		    	return array('resp'=>1,'msg'=>'父订单保存出错');
		    }
		    foreach ($this->params['services'] as $k => $v) {
    			//子项目数据
    			$cashiersdata[$k]['parent_id'] = $cashier['id'];
    		}
    		if(!$cashiers->saveAll($cashiersdata)){
    			return array('resp'=>1,'msg'=>'子订单保存出错');
    		}
    		//app需要的消费日志
    		$company = Company::get($this->params['company_id']);
    		$aclogdata = [
    			'cashier_id' => $cashier->id,
    			'time' => time(),//下单时间
    			'discount_money' =>  $total, //消费金额
    			'company_id' => $this->params['company_id'],//消费门店
    			'company_name' => $company['full_name'],//消费门店名字
    			'discount' => $discount?$discount:$this->params['services'][0]['discount'],//折扣
    			'coupon' => '',//优惠券
    			'order_no' => '',//单号
    			'payway' => 'APP支付', //支付方式
    			'payway_id' => $this->params['pay_way'],//支付方式，现金11，支付宝18，银行卡15，微信19
    			'pay_time' => time(),//支付时间
    			'pay_status' => 0,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
    			'services' => $cashiersdata,
    			'app_order_id' => $this->params['app_order_id'],
	    		'app_order_name' => $this->params['app_order_name']?$this->params['app_order_name']:$cashiersdata[0]['service_name'],
    			'order_type' => 1,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    		'cashier_type' => 1,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
    		];
    		$this->setAppConsumptionLog($aclogdata);
		    return array('resp'=>0,'msg'=>'订单生成成功','params'=>['cashier_id'=>$cashier->id,'cash'=>$wallet_new['cash']]);
    	}
    }

    //取消订单
    public function cancelOrder(){
    	//取消订单日志
    	//取出订单日志
    	$order_log = ACL::where(['app_order_id'=>$this->params['app_order_id']])->find();
    	if($order_log['status']===0){
    		return array('resp'=>1,'msg'=>'该单为已删除订单，取消失败');
    	}
    	if($order_log){
    		if($order_log['order_type']==1){
    			$cashier = Cashier::get($order_log['cashier_id']);
    			if($cashier['status']==2){
    				return array('resp'=>1,'msg'=>'该单为已删除订单，取消失败');
    			}
    			//修改日志
    			ACL::where('app_order_id',$this->params['app_order_id'])->setField('status',0);
    			//退款
    			return $this->delOrder($order_log['cashier_id']);
    		}else{
    			//非项目消费订单
    			if(ACL::where('app_order_id',$this->params['app_order_id'])->setField('status',0)){
    				return array('resp'=>0,'msg'=>'订单取消成功','params'=>[]);
    			}else{
    				return array('resp'=>1,'msg'=>'订单取消失败');
    			}
    		}
    	}else{
    		return array('resp'=>1,'msg'=>'订单不存在');
    	}
    	//退单

    	return array('resp'=>0,'msg'=>'订单取消成功','params'=>[]);
    }

    //项目消费订单废单
    public function delOrder($id = 0){
    	if($id&&is_numeric($id)){
    		$cashier = new Cashier;
    		$order = Cashier::get($id);

    		$log = new ConsumptionLog;
			$company = 0;//Db::table('company')->find(Session::get('company_id'));
    		
    		if($cashier->save(['status'=>2],array('id'=>$id))){
    			$cashier = Cashier::get($id);
    			if($cashier->order_type==1){
    				//消费单据作废钱包回滚
	    			if($order['member_id']){
	    				$member = MC::get($order['member_id']);
		    			//退回钱包金额
		    			$cashierdata = Cashiers::alias('cs')->join('cashier c','cs.parent_id = c.id','left')->where(['c.id'=>$id,'c.status'=>2])->select();
		    			$walletmoney = [
		    				'cash' => 0,
		    				'ewallet' => 0,
		    				'old_cash' => 0,
		    			];
		    			foreach ($cashierdata as $k => $v) {
		    				if($v['pay']==7){
		    					$walletmoney['cash']+=$v['discount'];
		    				}elseif($v['pay']==8){
		    					$walletmoney['ewallet']+=$v['discount'];
		    				}elseif($v['pay']==9){
		    					$walletmoney['old_cash']+=$v['discount'];
		    				}
		    				//是否有抵用券，如有，需在此把抵用券恢复可使用状态
		    				//if($v['deductible_pay']==17){
		    				//	Cos::where(['code'=>$v['coupons_code']])->setField('status',0);
		    				//}
		    			}
		    			$wallet = Wallet::where(['member_id'=>$member['id']])->find();
		    			$wallet_old = [
		    				'cash' => $wallet->cash,
		    				'ewallet' => $wallet->ewallet,
		    				'old_cash' => $wallet->old_cash,
		    			];
		    			$wallet->cash+=$walletmoney['cash'];
		    			$wallet->ewallet+=$walletmoney['ewallet'];
		    			$wallet->old_cash+=$walletmoney['old_cash'];
		    			if($wallet->save()){
		    				//添加退回日志
		    				$pay_list = [
		    					'cash' => 7,
		    					'ewallet' => 8,
		    					'old_cash' => 9,
		    				];
		    				foreach ($walletmoney as $k => $v) {
		    					if($v){
		    						$log = new ConsumptionLog;
			    					//$company = Db::table('company')->find(Session::get('company_id'));
			    					$logdata = [
						    			'company_id' => 5,
						    			'company_name' => '',
						    			'activer_name' => '用户自行操作',
						    			'wallet_type' => $pay_list[$k],
						    			'active_type' => 4,
						    			'order_no' => $order['number'],
						    			'last_balance' => $wallet_old['cash'],
						    			'this_balance' => $wallet['cash'],
						    			'services_id' => 0,
						    			'services_count' => 0,
						    			'services_name' => '',
						    			'pay_type' => $pay_list[$k],
						    			'member_id' => $member['id'],
						    			'member_name' => $member['name'],
						    			'member_no' => $member['card_no'],
						    			'time' => time(),
						    			'cashier_id' => $order['id'],
						    			'cash' => $v,
					    			];
						    		$log->insert($logdata);
		    					}
		    				}

		    			}else{
				        	return ['resp'=>1,'msg'=>'取消失败'];
				     	}

				        return array('resp'=>0,'msg'=>'订单取消成功','params'=>[]);
	    			}else{
	    				return array('resp'=>0,'msg'=>'订单取消成功','params'=>[]);
	    			}
    			}
		    } else {
		        return ['resp'=>1,'msg'=>'取消失败'];
		    }
    	}else{
    		return array('resp'=>0,'msg'=>'订单取消成功','params'=>[]);
    	}
    }
    //钱包金额变动日志
    public function setConsumptionLog($consumptionlogdata){
    	$company = Db::table('company')->find($this->params['company_id']);
    	$cashchangedata = [
			'company_id' => $this->params['company_id'],
			'company_name' => $company['full_name'],
			'activer_id' => 1,
			'activer_name' => 'app生成',
			'wallet_type' => $consumptionlogdata['wallet_type'],
			'active_type' => $consumptionlogdata['active_type'],
			'order_no' => $consumptionlogdata['order_no'],
			'last_balance' => $consumptionlogdata['last_balance'],
			'this_balance' => $consumptionlogdata['this_balance'],
			'services_id' => $consumptionlogdata['services_id'],
			'services_count' => $consumptionlogdata['services_count'],
			'services_name' => $consumptionlogdata['services_name'],
			'pay_type' => $consumptionlogdata['pay_type'],
			'services_family_id' => isset($consumptionlogdata['family_id'])?$consumptionlogdata['family_id']:1,
			'services_family' => isset($consumptionlogdata['family_name'])?$consumptionlogdata['family_name']:'系统',
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
    //app消费日志
    public function setAppConsumptionLog($data=[]){
    	$idata = [
    		//'order_type' => $data['order_type'],
    		'time' => time(),
    		'app_order_id' => $data['app_order_id'],
    		'cashier_id' => $data['cashier_id'],
    		'order_no' => $data['order_no'],
    		'text' => json_encode($data),
    		'card_no' => $data['card_no']?$data['card_no']:'',
    		'order_type' => $data['order_type'],
    	];
    	if(ACL::insert($idata)){
    		return true;
    	}else{
    		return false;
    	}
    }
    //获取会员卡充值标准所对应的卡类型(还款，收银)
    public function getCTbyRecharge_transfercard($real = 0,$ajax = 1,$card_no=null){
    	$use = Db::table('used_data')->where('id=80')->find();
		if(!$use['status']){
			if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'未开启还款，收银，会员卡类型变更限制']);
				exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'未开启还款，收银，会员卡类型变更限制']);
    		}
		}
    	$data = input();
    	$data['real'] = isset($data['real'])?$data['real']:$real;
    	$data['card_no'] = isset($data['card_no'])?$data['card_no']:$card_no;
    	if(!$data['card_no']){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'请输入会员卡号']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'请输入会员卡号']);
    		}
    	}
    	$re = MC::alias('mc')->field('w.*,mc.from')->join('wallet as w','mc.id=w.member_id','left')->where(['mc.card_no'=>$data['card_no']])->find();
    	if($re['from']==2){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'老会员卡']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'老会员卡']);
    		}
    	}
    	if($re['arrears_c']!=1){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'会员卡欠款后第一次消费，无需更换卡类型']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'会员卡欠款后第一次消费，无需更换卡类型']);
    		}
    	}
    	//上次充值金额
		$last_cashier = Cashier::where(['member_no'=>$data['card_no'],'order_type'=>['in','2,3,4']])->order('time DESC')->limit(1)->find();
    	//$total = $data['real'] + $last_cashier['real_money'];
    	//$member_type = MCT::alias('mct')->join('member as m','mct.id=m.card_type','left')->where(['m.card_no'=>$data['card_no']])->find();
    	return $this->getCTbyOpen($last_cashier['real_money'],0,$ajax);
    }
    //获取会员卡开卡标准所对应的卡类型
    public function getCTbyOpen($real = 0,$arrears = 0,$ajax = 1){
    	$use = Db::table('used_data')->where('id=78')->find();
    	if(!$use['status']){
    		$list = CardTypeCompany::alias('ctc')->field("mct.*")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.from'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
    		$clist = [];
    		foreach ($list as $k => $v) {
    			$clist[] = [
	    			'id' => $v['id'],
	    			'name' => $v['name'],
    				'discount' => $v['discount'],
	    		];
    		}
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'未开启开卡标准','data'=>$clist]);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'未开启开卡标准','data'=>$clist]);
    		}
    	}
    	$total = $real;

    	$where = 'mct.status=1 and mct.from=1 and mct.is_open=1 and ctc.company_id='.$this->params['company_id'].' and ((ctc.open_standard <='.$total.' and ctc.open_standard_e >='.$total.') or (ctc.open_standard <='.$total.' and ctc.open_standard_e=0))';
    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.id,mct.name,ctc.open_standard,ctc.open_standard_e")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where($where)->select();
    	if(empty($ctlist)){
    		if($ajax){
    			echo json_encode(['status'=>2,'msg'=>'没有对应卡类型']);
    			exit;
    		}else{
    			return json_encode(['status'=>2,'msg'=>'没有对应卡类型']);
    		}
    	}
    	$list = [];
    	foreach ($ctlist as $k => $v) {
    		$list[] = [
    			'id' => $v['id'],
    			'name' => $v['name'],
    			'discount' => $v['discount'],
    		];
    	}
    	if($ajax){
    		echo json_encode(['status'=>1,'data'=>$list]);
    		exit;
    	}else{
    		return json_encode(['status'=>1,'data'=>$list]);
    	}
    }
	//导入技师数据
    protected function getDate(){
    	$all = Db::table('dz_jishi')->select();

    	$data = [];
    	foreach ($all as $k => $v) {
    		$v['js_shop_id'] = ($v['js_shop_id']===8||$v['js_shop_id']===0)?5:$v['js_shop_id'];
    		$data[$k] = [
    			//'id' => $v['id'],
    			'number' => $v['js_no'],
    			'name' => $v['js_name'],
    			'nickname' => $v['js_nickname'],
    			'mobile_phone' => '',
    			'company' => $v['js_shop_id'],
    			'sex' => $v['js_sex'],
    			'js_pic_path' => $v['js_pic_path'],
    			'js_desc' => $v['js_desc'],
    			'js_level' => $v['js_level'],
    			'js_sex_type' => $v['js_sex_type'],
    			'js_tech' => $v['js_tech'],
    			'js_year' => $v['js_year'],
    			'js_is_promote' => $v['js_is_promote'],
    			'job' => 68,
    		];
    		if($v['js_sex_type']==1){
    			$data[$k]['therapist_star'] = $this->star[$v['js_level']];
    		}elseif($v['js_sex_type']==2){
				$data[$k]['acupuncture_star'] = $this->star[$v['js_level']];
    		}elseif($v['js_sex_type']==3){
    			$data[$k]['chinese_star'] = $this->star[$v['js_level']];
    		}else{
    			echo $k .' ,';
    		}
    	}
    		$re = model('family')->saveAll($data);
    		var_dump($re);
    		echo "<hr />";
    }
}
