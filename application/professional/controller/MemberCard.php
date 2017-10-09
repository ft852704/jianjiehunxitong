<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use app\index\model\MemberCardType as MCT;
use app\index\model\MemberCard as MC;
use app\index\model\CardTypeChildren as CTC;
use app\index\model\CardLog;
use app\index\model\ConsumptionLog;
use app\index\model\Company;
use app\index\model\Wallet;
use app\index\model\Cashier;
use app\index\model\Cashiers;
use app\index\model\CashierPayWay as CPW;
use app\index\model\CardTypeCompany;
use app\index\model\ChuangduLog;
use app\index\model\Family as FamilyModel;
use app\index\model\SuperEditLog;
use app\index\model\AppConsumptionLog as ACL;

use \think\Session;

use think\Db;
class MemberCard extends Base
{
	public $member_rule = [
	            //会员卡字段
	            'card_no|会员卡编号'   => 'require|unique:member',
				'card_type|会员卡类型' => 'require',
				//'name|会员名字' => 'require|chs',
				'mobile_phone|会员手机号' => 'number|length:11',
	        ];
	public $cashier_rule = [
	            'number|单号' => 'require'
	        ];
	public $pageSize = 20;
	public $statusList = [
			1 => '已开卡',
			2 => '已换卡',
			3 => '已转卡',
			4 => '已到期',
	];
	public $active_type = [
		1 => '消费',
		2 => '充值',
		3 => '还款',
		4 => '作废退款',
		5 => '管理员修改',
	];
	//会员卡主页
    public function index()
    {
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$member = new MC;
    		if(isset($data['birthday'])){
    			$data['birthday'] = $data['birthday']?strtotime($data['birthday']):0;
    		}
    		$member_old = $member->get($data['id']);
    		if($member_old['status']!=1){
    			$re = array(
    					'status' => 2,
    					'msg' => '该会员卡不能修改资料'
    				);
    		} 
    		if($member->save($data,'id='.$data['id'])){
    			//会员卡日志表
    				$active_type = isset($data['remark'])?8:7;
		    		$cardlogdata = [
		    			'company' => Session::get('company_id'),
		    			'member_id' => $data['id'],
		    			'active_type' => $active_type,
		    			'remark' => isset($data['remark'])?$data['remark']:'',
		    		];
		    		$this->setCardLog($cardlogdata);

    			$re = array(
    					'status' => 1,
    					'msg' => '保存成功'
    				);
    		}else{
    			$re = array(
    					'status' => 2,
    					'msg' => '保存失败'
    				);
    		}
    		echo json_encode($re);
    	}else{
    		//卡可消费地区
    		$company = Company::where('status=1 and level<=1')->select();
    		$companylist = [];
    		foreach ($company as $k => $v) {
    			$companylist[$v['id']] = $v['full_name'];
    		}
    		$this->assign('companylist',$companylist);

    		//所有门店
    		$companyall = Company::select();
    		$companyalllist = [];
    		foreach ($companyall as $k => $v) {
    			$companyalllist[$v['id']] = $v['full_name'];
    		}
    		$this->assign('companyalllist',$companyalllist);

    		//所有启用的门店
    		$companystatus = Company::where('status=1')->order('level ASC')->select();
    		$companystatuslist = [];
    		foreach ($companystatus as $k => $v) {
    			$companystatuslist[$v['id']] = $v['full_name'];
    		}
    		$this->assign('companystatuslist',$companystatuslist);

    		$this->assign('statuslist',$this->statusList);

    		//会员卡类型
    		$cardtype = MCT::select();
    		$cardtypelist = [];
    		foreach ($cardtype as $k => $v) {
    			$cardtypelist[$v['id']] = $v['name'];
    		}
    		$this->assign('cardtype',$cardtypelist);

    		return $this->fetch();
    	}
    }

    //会员卡列表分页
    public function cardList(){
    	$data = input();
    	$currentPage = isset($data['page'])?$data['page']:1;
    	$where = [];
    	if(!isset($data['flag'])){
    		if(isset($data['search'])&&$data['search']){
		    	if($data['search_type']==1){
		    		$where =  array('m.name'=>array('like','%'.$data['search'].'%'));
		    	}elseif($data['search_type']==2){
		    		$where =  array('card_no'=>array('like','%'.$data['search'].'%'));
		    	}elseif($data['search_type']==3){
		    		$where =  array('mobile_phone'=>array('like','%'.$data['search'].'%'));
		    	}
		    }
	    	/*if(isset($data['company'])){
		    	$companystr = $this->getChildCompany($data['company']);
		    	$where['company_id'] = array('in',$companystr);
	    	}*/
    	}
    	//注意，分页的where方法中有使用到别名时，必须在前面查总记录数的时候把别名一起带上，否则会报错
    	$list['pageCount'] = ceil(MC::where($where)->alias('m')->count('*')/$this->pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $this->pageSize;
    	$cardList = MC::field('m.*,t.name as tname')->alias('m')->join('member_card_type t','m.card_type=t.id','left')->where($where)->limit(($currentPage-1)*$this->pageSize.','.$this->pageSize)->select();
    	$list['sta']=empty($cardList)?0:1;
    	foreach ($cardList as $k => $v) {
    		$list['list'][$k] = [
    			'id' => $v['id'],
    			'card_no' => $v['card_no'],
    			'type' => $v['tname'],
    			'name' => $v['name'],
    			'mobile_phone' => $v['mobile_phone'],
    		];
    	}
    	echo json_encode($list);
    }

    //会员卡账户历史列表
    public function histryLog(){
    	$data = input();
    	$currentPage = isset($data['page'])?$data['page']:1;
    	$pageSize = 5;
    	$where = [
    		'member_id' => $data['mid'],
    	];
    	$list['pageCount'] = ceil(ConsumptionLog::where($where)->count('*')/$pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $pageSize;

    	$logList = ConsumptionLog::limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->group('id DESC')->where($where)->select();

    	$list['sta']=empty($logList)?0:1;
    	$used_data = $this->getUsedData('6,10');
    	foreach ($used_data as $key => $value) {
    		$wallet_type[$value['id']] = $value['name'];
    	}
    	foreach ($logList as $k => $v) {
    		$list['list'][$k] = [
    			'company_name' => $v['company_name'],
    			'activer_name' => $v['activer_name'],
    			'date' => date('y-m-d h:i:s',$v['time']),
    			'wallet_type' => $wallet_type[$v['wallet_type']],
    			'active_type' => $this->active_type[$v['active_type']],
    			'order_no' => $v['order_no'],
    			'last_balance' => $v['last_balance'],
    			'cash' => $v['cash'],
    			'this_balance' => $v['this_balance'],
    		];
    	}
    	echo json_encode($list);
    }
    //会员卡消费历史--老的结构
    public function old_consumptionLog(){
    	$data = input();
    	$currentPage = isset($data['page'])?$data['page']:1;

    	$list['pageCount'] = ceil(ConsumptionLog::count('*')/$this->pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $this->pageSize;
    	$where = [
    		'member_id' => $data['mid'],
    		'active_type' => 1
    	];
    	$logList = ConsumptionLog::limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->group('id DESC')->where($where)->select();
    	$list['sta']=empty($cardList)?0:1;
    	$list['sta']=empty($logList)?0:1;

    	foreach ($logList as $k => $v) {
    		$list['list'][$k] = [
    			'company_name' => $v['company_name'],
    			'activer_name' => $v['activer_name'],
    			'date' => date('y-m-d h:i:s',$v['time']),
    			'order_no' => $v['order_no'],
    			'active_type' => $v['active_type'],
    			'order_no' => $v['order_no'],
    			'services_id' => $v['services_id'],
    			'services_count' => $v['services_count'],
    			'cash' => $v['cash'],
    			'pay_type' => $v['pay_type'],
    			'services_family' => $v['services_family'],
    		];
    	}
    	echo json_encode($list);
    }
    //会员卡消费历史--新结构
    public function consumptionLog(){
    	$data = input();
    	$currentPage = isset($data['page'])?$data['page']:1;
    	$pageSize = 5;

    	$list['pageCount'] = ceil(Cashiers::alias('cs')->join('cashier c','cs.parent_id=c.id','left')->where('c.member_id='.$data['mid'].' and c.order_type<>2')->count('*')/$pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $pageSize;
    	$where = [
    		'c.member_id' => $data['mid'],
    		'c.order_type' => ['NEQ',2],
    	];
    	//$logList = ConsumptionLog::limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->group('id DESC')->where($where)->select();
    	$logList = Cashiers::alias('cs')->field('cs.*,cy.full_name,c.number,c.time,c.active_id')->join('cashier c','cs.parent_id=c.id','left')->join('company cy','c.company_id=cy.id','left')->group('cs.id DESC')->limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->where($where)->select();

    	//$list['sta']=empty($cardList)?0:1;
    	$list['sta']=empty($logList)?0:1;

    	$used_data = $this->getUsedData('6,10');
    	foreach ($used_data as $key => $value) {
    		$wallet_type[$value['id']] = $value['name'];
    	}

    	foreach ($logList as $k => $v) {
    		$list['list'][$k] = [
    			'company_name' => $v['full_name'],
    			'activer_name' => $v['active_id'],
    			'date' => date('y-m-d h:i:s',$v['time']),
    			'order_no' => $v['number'],
    			'active_type' => $this->active_type[$v['order_type']],
    			'services_id' => $v['services_name'],
    			'services_count' => $v['count'],
    			'cash' => $v['discount'],
    			'pay_type' => $wallet_type[$v['pay']],
    			'services_family' => $v['fworker'],
    		];
    		if($v['fworker']){
    			$ftemp = FamilyModel::where('number='.$v['fworker'])->find();
    			$list['list'][$k]['services_family'] = $ftemp['name'];
    		}
    	}
    	echo json_encode($list);
    }

    //会员卡操作历史
    public function operationLog(){
    	$data = input();
    	$currentPage = isset($data['page'])?$data['page']:1;

    	$list['pageCount'] = ceil(CardLog::where('member_id='.$data['mid'])->count('*')/$this->pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $this->pageSize;
    	$operationLog = CardLog::limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->group('id DESC')->where('member_id='.$data['mid'])->select();
    	$list['sta']=empty($operationLog)?0:1;
    	foreach ($operationLog as $k => $v) {
    		$list['list'][$k] = [
    			'company_name' => $v['company_name'],
    			'active_name' => $v['active_name'],
    			'date' => date('Y-m-d h:i:s',$v['time']),
    			'mark' => $v['mark'],
    		];
    	}
    	echo json_encode($list);
    }

    //根据ID获取会员信息
    public function memberInfo($status = 0){
    	$data = input();
    	$where = [
    		'mc.id' => $data['mid'],
    	];
    	if($status){
    		$where['mc.status'] = ['in'=>$status];
    	}
    	$member = MC::alias('mc')->field('*,name as member_name,mc.id as mcid')->join('wallet w','mc.id = w.member_id','left')->where($where)->find();
    	$member['birthday'] = date('Y-m-d',$member['birthday']);
    	$member['start_time'] = date('Y-m-d',$member['start_time']);
    	$member['end_time'] = date('Y-m-d',$member['end_time']);
    	$member['last_recharge_time'] = date('Y-m-d',$member['last_recharge_time']);
    	//$member = MC::get($data['mid']);
    	echo json_encode($member);
    }
    //根据会员卡号获取会员信息
    public function memberInfoByCardno($status = 0,$ajax = 1){
    	$data = input();
    	$where = [
    		'mc.card_no' => $data['card_no'],
    	];
    	/*if($status){
    		$where['mc.status'] = ['in'=>$status];
    	}*/
    	//更新创度的卡备注，钱包
    	$member = MC::where(['card_no'=>$data['card_no']])->find();
    	if($member['is_update']===0){
    	//if(0){
    		$company = Company::get($member['company_id']);
    		$sendParam = [
    			'card_no' => $member['card_no'],
    			'companyCode' => $company['number'],
    		];
    		$re = json_decode(self::https_post($this->app_url.'/inter/card_note?card_no='.$sendParam['card_no'].'&companyCode='.$sendParam['companyCode'],[]),true);
    		$msg = json_decode($re['msg'],true);
    		$wdata = [];
    		if(!$msg['errorCode']&&!is_null($msg['errorCode'])){
    			if($msg['result']){
    				foreach ($msg['result']['memberCardAccountInfo'] as $k => $v) {
	    				if($v['cardAccountName']=='电子钱包'){
	    					$wdata['ewallet'] = $v['remainAmt'];
	    					if($wdata['ewallet']){
	    						$a = Wallet::where(['member_id'=>$member['id']])->setField('ewallet',$wdata['ewallet']);
	    					}
	    				}elseif($v['cardAccountName']=='老系统疗程'){
	    					$wdata['old_cash'] = $v['remainAmt'];
	    					if($wdata['old_cash']){
	    						$b = Wallet::where(['member_id'=>$member['id']])->setField('old_cash',$wdata['old_cash']);
	    					}
	    				}
	    			}
	    			if($msg['result']['note']){
	    				MC::where(['id'=>$member['id']])->setField('remark',$msg['result']['note']);
	    			}
	    			MC::where(['id'=>$member['id']])->setField('is_update',1);
    			}
    		}
    	}

    	$member = MC::alias('mc')->field('*,name as member_name,mc.id as mcid,mc.start_time as start_time,mc.end_time as end_time')->join('wallet w','mc.id = w.member_id','left')->where($where)->find();
    	if(empty($member)){
    		$member['code'] = 0;//数据不存在
    		echo json_encode($member);
    		exit;
    	}
    	//如果会员卡类型没有分发给该店，该店不能充值
    	$card_info = CardTypeCompany::where(['card_type_id'=>$member['card_type'],'company_id'=>Session::get('company_id')])->find();
    	$ct_info = MCT::where(['id'=>$member['card_type']])->find();
 		if(empty($card_info)){
 			$card_info = $ct_info;
 			$member['ct_is_true'] = 0;
 		}else{
 			$member['ct_is_true'] = 1;
 		}
 		$member['is_overdue'] = 0;
 		//有效期控制
		$time = time();
		if($member['start_time']>$time){
			$member['is_overdue'] = 1;
		}else{
			if($member['end_time']&&($member['end_time']+24*3600-1)<$time){
				$member['is_overdue'] = 1;
			}
		}
		
 		$use = Db::table('used_data')->where('id=78')->find();

 		$member['open_standard'] = $card_info['open_standard'];
 		$member['open_standard_e'] = $card_info['open_standard_e'];
 		$member['recharge_standard'] = $card_info['recharge_standard'];
 		$member['recharge_standard_e'] = $card_info['recharge_standard_e'];
 		$member['is_recharge'] = $ct_info['is_recharge'];

 		$member['is_used'] = $use['status'];
    	
    	$wallet = Wallet::where(['member_id'=>$member['mcid']])->find();
    	$member['arrears_c'] = $wallet['arrears_c'];
    	$member['from'] = $ct_info['from'];

    	$member['birthday'] = date('Y-m-d',$member['birthday']);
    	$member['start_time'] = date('Y-m-d',$member['start_time']);
    	$member['end_time'] = date('Y-m-d',$member['end_time']);
    	$member['last_recharge_time'] = date('Y-m-d',$member['last_recharge_time']);
    	$member['code'] = 1;//数据存在
    	//$member = MC::get($data['mid']);
    	if($ajax==1){
    		echo json_encode($member);exit;
    	}else{
    		return json_encode($member);
    	}
    }
    //根据IC会员卡号获取会员信息
    public function memberEncodeInfoByCardno($status = 0){
    	$data = input();
    	$data['code'] = strtolower($data['code']);
    	if(md5($data['card_no'].$this->ic_key)!=$data['code']){
    		echo json_encode(array('code'=>2));
    		exit;
    	}
    	$where = [
    		'mc.card_no' => $data['card_no'],
    	];
    	if($status){
    		$where['mc.status'] = ['in'=>$status];
    	}
    	if($status){
    		$where['mc.status'] = $status;
    	}

    	$member = MC::alias('mc')->field('*,name as member_name,mc.id as mcid')->join('wallet w','mc.id = w.member_id','left')->where($where)->find();

    	if(empty($member)){
    		$member['code'] = 0;//数据不存在
    		echo json_encode($member);
    		exit;
    	}
    	$member['is_overdue'] = 0;
 		//有效期控制
		$time = time();
		if($member['start_time']>$time){
			$member['is_overdue'] = 1;
		}else{
			if($member['end_time']&&($member['end_time']+24*3600-1)<$time){
				$member['is_overdue'] = 1;
			}
		}

    	$card_info = CardTypeCompany::where(['card_type_id'=>$member['card_type'],'company_id'=>Session::get('company_id')])->find();
    	$ct_info = MCT::where(['id'=>$member['card_type']])->find();
    	if(empty($card_info)){
 			$card_info = $ct_info;
 			$member['ct_is_true'] = 0;
 		}else{
 			$member['ct_is_true'] = 1;
 		}
 		$use = Db::table('used_data')->where('id=78')->find();

 		$member['is_used'] = $use['status'];

 		$member['open_standard'] = $card_info['open_standard'];
 		$member['open_standard_e'] = $card_info['open_standard_e'];
 		$member['recharge_standard'] = $card_info['recharge_standard'];
 		$member['recharge_standard_e'] = $card_info['recharge_standard_e'];
 		$member['is_recharge'] = $ct_info['is_recharge'];

    	$wallet = Wallet::where(['member_id'=>$member['mcid']])->find();
    	$member['arrears_c'] = $wallet['arrears_c'];
    	$member['from'] = $ct_info['from'];

    	$member['birthday'] = date('Y-m-d',$member['birthday']);
    	$member['start_time'] = date('Y-m-d',$member['start_time']);
    	$member['end_time'] = date('Y-m-d',$member['end_time']);
    	$member['last_recharge_time'] = date('Y-m-d',$member['last_recharge_time']);
    	$member['code'] = 1;//数据存在
    	//$member = MC::get($data['mid']);
    	echo json_encode($member);
    }
    //根据会员卡号返回IC卡加密格式数据
    public function memberEncodeCardno(){
    	$data = input();
    	$re = MC::where(['card_no'=>$data['card_no']])->find();
    	if(empty($re)){
    		echo md5($data['card_no'].$this->ic_key);
    	}else{
    		echo '';
    	}
    	//$member = MC::get($data['mid']);
    }
    //根据会员卡号返回IC卡加密格式数据(不判定)
    public function memberEncodeCardno_no(){
    	$data = input();
    	$re = MC::where(['card_no'=>$data['card_no']])->find();
    	if(!$re){//不存在
    		$result['status'] = 1;
    		$result['data'] = md5($data['card_no'].$this->ic_key);
    	}else{//存在
    		$result['status'] = 2;
    		$result['data'] = md5($data['card_no'].$this->ic_key);
    	}
    	echo json_encode($result);
    	//$member = MC::get($data['mid']);
    }

    //会员卡开卡
    public function openCard(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$walletdata = [];
    		if(!isset($data['card_no'])&&$data['card_no']){
    			return $this->error('请选择会员卡类型');
    		}
	        $member = new MC;
	        /*if($data['wallet']==7){
	        	$use = Db::table('used_data')->where('id=78')->find();
		        if($use['status']){
		        	$card = json_decode($this->getCTbyOpen($data['real'],$data['arrears'],0),true);
			        if($card['status']){
			        	$data['card_type'] = $card['id'];
			        }else{
			        	return $this->error('没有对应的会员卡类型，开卡失败');
			    	}
		        }
	        }*/
	        
    		//会员卡表
    		$memberdata = [
    			'card_no' => $data['card_no'],
    			'card_type' => $data['card_type'],
    			'company_area' => $data['company_area'],
    			'mobile_phone' => $data['mobile_phone'],
    			'sex' => $data['sex'],
    			'start_time' => $data['start_time']?strtotime($data['start_time']):0,
    			'end_time' => $data['end_time']?strtotime($data['end_time']):0,
    			'ID_NO' =>$data['ID_NO'],
    			'birthday' => $data['birthday']?strtotime($data['birthday']):0,
    			'company_id' => Session::get('company_id'),
    			'status' => 1,
    			'name' => $data['name'],
    			'is_msn' => isset($data['is_msn'])?$data['is_msn']:0,
    			'last_recharge' => $data['real'],
    			'last_recharge_time' => time(),
    			'last_recharge_addr' => Session::get('company_id'),
    			'from' => 1,
    			'recharge' => $data['wallet']==7||$data['wallet']==9?$data['real']:0,
    			'time' => time(),
    		];
    		// 会员卡数据验证
	        $validate = new Validate($this->member_rule);
	        $result   = $validate->check($memberdata);
	        
	        // 订单数据验证
	        $validate1 = new Validate($this->cashier_rule);
	        $result1 = $validate1->check(['number'=>$data['work_order']]);
	        if(!$result1){
	            return  $validate1->getError();
	        }
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($member->save($memberdata)){
	        		//钱包表
		    		if($data['wallet']==7){
		    			$walletdata['cash_arrears'] = $data['arrears'];
		    			$walletdata['cash'] = $data['real'];
		    			//$walletdata['arrears_c'] = 2;
		    		}elseif($data['wallet']==8){
		    			$walletdata['ewallet'] = $data['real'];
		    		}elseif($data['wallet']==9){
		    			$walletdata['old_cash'] = $data['real'];
		    			$walletdata['old_cash_arrears'] = $data['arrears'];
		    		}
		    		if($data['arrears']){
		    			$walletdata['arrears_c'] = 2;
		    		}
		    		$walletdata['member_id'] = $member->id;
	        		$wallet = new Wallet;
	        		$wallet->save($walletdata);

	        		//会员卡日志表
		    		$cardlogdata = [
		    			'company' => Session::get('company_id'),
		    			'member_id' => $member->id,
		    			'active_type' => 1,
		    		];
		    		$this->setCardLog($cardlogdata);

		    		//订单

		    		$cashier = new Cashier;
		    		$cashierdata = [
		    			'company_id' => Session::get('company_id'),
		    			'type' => 1,
		    			'number' => $data['work_order'],
		    			'member_id' => $member->id,
		    			'member_no' => $data['card_no'],
		    			'sex' => $data['sex'],
		    			'count' => 1,
		    			'real_money' => $data['real'],
		    			'time' => time(),
		    			'active_id' => Session::get('family_id'),
		    			'order_type' => 3,
		    			'should_money' => $data['total'],
		    			'status' => 1
		    		];
		    		$cashier->save($cashierdata);
		    		//订单子表
		    		//$cashiersdata = [];
		    		//订单支付类型
		    		$cpwdata = [];
		    		foreach ($data['pay_way'] as $k => $v) {
		    			if($data['money'][$k]){
		    				$cpwdata[$k] = [
			    				'pay_type' => $v,
			    				'money' => $data['money'][$k],
			    				'cashier_id' => $cashier->id,
			    				'family_no' => $data['number'][$k],
			    				'share_results' => $data['share_results'][$k],
			    				'wallet_type' => $data['wallet'],
			    			]; 
		    			}
		    		}
		    		$cpw = new CPW;
		    		$cpw->saveAll($cpwdata);

		    		//金额变动表
		    		$cashchangedata = [
		    			'wallet_type' => $data['wallet'],
		    			'active_type' => 2,
		    			'order_no' => $data['work_order'],
		    			'cash' => $data['real'],
		    			'last_balance' => 0,
		    			'this_balance' => $data['real'],
		    			'services_id' => 0,
		    			'services_count' => 1,
		    			'services_name' => '开卡充值',
		    			'pay_type' => $data['pay_way'][0],
		    			'pay_name' => 1,
		    			'member_id' => $member->id,
		    			'member_name' => $member->name,
		    			'member_no' => $member->card_no,
		    			'cashier_id' => $cashier->id,
		    		];
		    		$this->setConsumptionLog($cashchangedata);
		    		$wallet = $wallet->where(['member_id'=>$member->id])->find();
		    		$this->sendRechargeMsg($memberdata['mobile_phone'],$memberdata['name'],$memberdata['card_no'],$data['real'],$wallet);

		    		//app需要的消费日志
	    			$company = Company::get(Session::get('company_id'));
	    			
	    			$aclogdata = [
	    				'cashier_id' => $cashier->id,
	    				'time' => time(),//下单时间
	    				'discount_money' =>  $data['real'], //消费金额
	    				'company_id' => Session::get('company_id'),//消费门店
	    				'company_name' => $company['full_name'],//消费门店名字
	    				'coupon' => '',//优惠券
	    				'order_no' => $data['work_order'],//单号
	    				'payway' => '', //支付方式
	    				'payway_id' => $data['pay_way'][0],//支付方式
	    				'pay_time' => time(),//支付时间
	    				'pay_status' => 1,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
	    				'cpw' => $cpwdata,
	    				'app_order_id' => '',
	    				'app_order_name' => $company['full_name'].'充值',//订单名称
	    				'card_no' => $member->card_no,//会员卡号
	    				'order_type' => 7,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    				'cashier_type' => 3,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
	    			];
	    			$this->setAppConsumptionLog($aclogdata);

		    		//$this->print_cashier($cashier->id,$data['wallet']);
		    		$this->redirect('MemberCard/print_cashier', ['cashier_id' => $cashier->id,'url'=>'/Index/MemberCard/openCard','msg'=>'开卡成功']);
		    		//return $this->success('会员卡添加成功','/Index/member_card/opencard');
	        	}else{
	        		return $this->error($member->getError());
	        	}
	        }
    	}else{
    		//会员卡类型
	    	//ctlist = CardTypeCompany::alias('ctc')->field("mct.*")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
	    	$ctlist = [];
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	if(Session::get('role')==2){
	    		unset($paylist[1]);//消除电子钱包
	    	}
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1 and level=1 or level=0')->select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData(10);
	    	$this->assign('paywaylist',$paywaylist);
	    	//有效期
	    	$this->assign('time',['stime'=>date('Y-m-d'),'etime'=>date('Y-m-d',time()+3600*24*365*20)]);

	    	return $this->fetch(); 
    	}
    }
    //获取开卡标准对应的卡类型
    public function getOpenStandarType(){
    	$data = input();
    }
    
    //会员卡充值
    public function rechargeCard($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$data['total'] = $data['arrears']+$data['real'];
    		$member = new MC;

    		$member_old = $member->where(array('card_no'=>$data['card_no']))->find();
    		if(empty($member_old)){
    			return $this->error('会员卡不存在');
    		}
    		if($member_old->status!=1){
    			return $this->error('会员卡不是已开卡状态，不能充值');
    		}

    		//有效期控制
		    $time = time();
		    if($member_old['start_time']>$time){
		    	return $this->error('该卡不在有效期内，不能消费！');
		    }
		    if($member_old['end_time']&&($member_old['end_time']+24*3600-1)<$time){
		    	return $this->error('该卡不在有效期内，不能消费！');
		     }

	        /*if($data['wallet']==7){
	        	$use = Db::table('used_data')->where('id=78')->find();
		        if($use['status']){
		        	$card = json_decode($this->getCTbyRecharge($data['real'],$data['arrears'],0,$data['card_no']),true);
			        if($card['status']==1){
			        	$data['card_type'] = $card['id'];
			        }else{
			        	return $this->error('没有对应的会员卡类型，充值失败');
			    	}
		        }
	        }*/
	        //判断是否满足充值起充金额
	        if($data['wallet']==7){
	        	$use = Db::table('used_data')->where('id=78')->find();
		        if($use['status']){
		        	//判断是否能充值
		        	$member_card_info = json_decode($this->memberInfoByCardno($data['card_no'],0));
		        	if(!($member_card_info->ct_is_true&&$member_card_info->is_recharge)){
		        		return $this->error('本店不能充值该类型卡，或该卡类型未开启充值功能');
		        	}
		        	if(($data['real']+$data['arrears'])<$member_card_info->recharge_standard){
		        		return $this->error('不满足起充标准，请重新输入金额');
		        	}
		        }
	        }
    		
    		/*if(!$data['card_type']){
    			return $this->error('该会员的会员卡类型不能充值');
    		}*/

    		//钱包欠款验证
    		$wallet = new Wallet;
	        $wallet_old = $wallet->where(array('member_id'=>$member_old['id']))->find();
	        $use = Db::table('used_data')->where('id=80')->find();
		    if($use['status']){
		        if($wallet_old->old_cash_arrears>0||$wallet_old->cash_arrears>0){
	    			return $this->error('会员卡还有欠款，请让会员付清欠款后再进行充值操作');
	    		}
		    }
			

    		//会员卡表
    		$memberdata = [
    			//'card_type' => $data['card_type'],
    			'company_area' => $data['company_area'],
    			'company_id' => Session::get('company_id'),
    			'last_recharge' => $data['real'],
    			'last_recharge_time' => time(),
    			'last_recharge_addr' => Session::get('company_id'),
    			'recharge' => $data['wallet']==7||$data['wallet']==9?$member_old->recharge+$data['real']:$member_old->recharge+0,
    			'last_card_type' => $member_old['card_type'],
    		];
    		// 会员卡数据验证
	        //$validate = new Validate($this->member_rule);
	        //$result   = $validate->check($memberdata);
	        // 订单数据验证
	        $validate1 = new Validate($this->cashier_rule);
	        $result1 = $validate1->check(['number'=>$data['card_no']]);
	        if(!$result1){
	            return  $validate1->getError();
	        }
	        if(0){
	           // return  $validate->getError();
	        }else{
	        	if($member->save($memberdata,array('card_no'=>$data['card_no']))){
	        		//钱包表
		    		if($data['wallet']==7){
		    			$walletdata['cash_arrears'] = $wallet_old['cash_arrears']+$data['arrears'];
		    			$walletdata['cash'] = $wallet_old['cash']+$data['real'];
		    			//$walletdata['arrears_c'] = 2;
		    		}elseif($data['wallet']==8){
		    			$walletdata['ewallet'] = $wallet_old['ewallet']+$data['real'];
		    		}elseif($data['wallet']==9){
		    			$walletdata['cash'] = $wallet_old['cash'];
		    			$walletdata['old_cash'] = $wallet_old['old_cash']+$data['real'];
		    			$walletdata['old_cash_arrears'] = $wallet_old['old_cash_arrears']+$data['arrears'];
		    		}
		    		if($data['arrears']){
		    			$walletdata['arrears_c'] = 2;
		    		}
	        		$wallet->save($walletdata,array('member_id'=>$member_old['id']));
	        		//会员卡日志表
		    		$cardlogdata = [
		    			'company' => Session::get('company_id'),
		    			'member_id' => $member_old['id'],
		    			'active_type' => 2,
		    		];
		    		$this->setCardLog($cardlogdata);

		    		//订单

		    		$cashier = new Cashier;
		    		$cashierdata = [
		    			'company_id' => Session::get('company_id'),
		    			'type' => 1,
		    			'number' => $data['work_order'],
		    			'member_id' => $member_old['id'],
		    			'member_no' => $member_old['card_no'],
		    			'sex' => $member_old['sex'],
		    			'count' => 1,
		    			'real_money' => $data['real'],
		    			'time' => time(),
		    			'active_id' => Session::get('family_id'),
		    			'order_type' => 2,
		    			'should_money' => $data['total'],
		    			'status' => 1
		    		];
		    		$cashier->save($cashierdata);
		    		//订单子表
		    		//$cashiersdata = [];
		    		//订单支付类型
		    		$cpwdata = [];
		    		foreach ($data['pay_way'] as $k => $v) {
		    			if($data['money'][$k]){
		    				$cpwdata[$k] = [
			    				'pay_type' => $v,
			    				'money' => $data['money'][$k],
			    				'cashier_id' => $cashier->id,
			    				'family_no' => $data['number'][$k],
			    				'share_results' => $data['share_results'][$k],
			    				'wallet_type' => $data['wallet'],
			    			]; 
		    			}
		    		}
		    		$cpw = new CPW;
		    		$cpw->saveAll($cpwdata);
		    		//金额变动表
		    		$cashchangedata = [
		    			'wallet_type' => $data['wallet'],
		    			'active_type' => 2,
		    			'order_no' => $data['work_order'],
		    			'cash' => $data['real'],
		    			'last_balance' => $wallet_old['cash'],
		    			'this_balance' => $wallet['cash'],
		    			'services_id' => 0,
		    			'services_count' => 1,
		    			'services_name' => '充值',
		    			'pay_type' => $data['pay_way'][0],
		    			'pay_name' => 1,
		    			'member_id' => $member_old['id'],
		    			'member_name' => $member_old['name'],
		    			'member_no' => $member_old['card_no'],
		    			'cashier_id' => $cashier->id,
		    		];
		    		$this->setConsumptionLog($cashchangedata);
		    		//发送短信
		    		$this->sendRechargeMsg($member_old['mobile_phone'],$member_old['name'],$member_old['card_no'],$data['real'],$wallet->where(array('member_id'=>$member_old['id']))->find());
		    		//app需要的消费日志
	    			$company = Company::get(Session::get('company_id'));
	    			
	    			$aclogdata = [
	    				'cashier_id' => $cashier->id,
	    				'time' => time(),//下单时间
	    				'discount_money' =>  $data['real'], //充值金额
	    				'company_id' => Session::get('company_id'),//消费门店
	    				'company_name' => $company['full_name'],//消费门店名字
	    				'coupon' => '',//优惠券
	    				'order_no' => $data['work_order'],//单号
	    				'payway' => '', //支付方式
	    				'payway_id' => $data['pay_way'][0],//支付方式
	    				'pay_time' => time(),//支付时间
	    				'pay_status' => 1,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
	    				'cpw' => $cpwdata,
	    				'app_order_id' => '',
	    				'app_order_name' => $company['full_name'].'充值',//订单名称
	    				'card_no' => $member_old['card_no'],//会员卡号
	    				'order_type' => 7,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    				'cashier_type' => 2,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
	    			];
	    			$this->setAppConsumptionLog($aclogdata);

		    		$this->redirect('MemberCard/print_cashier', ['cashier_id' => $cashier->id,'url'=>'/Index/MemberCard/rechargeCard','msg'=>'充值成功']);
		    		//return $this->success('会员卡充值成功','/Index/member_card/rechargeCard');
		    	}
		    }
    	}else{
    		//会员卡类型
    		$ctlist = MCT::select();
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	if(Session::get('role')!=1){
	    		unset($paylist[1]);//消除电子钱包
	    	}
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1 and level=1 or level=0')->select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData(10);
	    	$this->assign('paywaylist',$paywaylist);
	    	return $this->fetch(); 
    	}
    	
    }
    //会员卡转卡
    public function transferCard($id=null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		if(!isset($data['card_no'])&&$data['card_no']){
    			return $this->error('请选择会员卡类型');
    		}
    		$member = new MC;
    		$member_old = $member->where(array('card_no'=>$data['card_no']))->find();
    		if(empty($member_old)){
    			return $this->error('会员卡不存在');
    		}
    		if($member_old->status!=1){
    			return $this->error('会员卡不是已开卡状态，不能转卡');
    		}
    		//钱包欠款验证
    		$wallet = new Wallet;
	        $wallet_old = $wallet->where(array('member_id'=>$member_old['id']))->find();
	        $use = Db::table('used_data')->where('id=80')->find();
		    if($use['status']){
		        if($wallet_old->old_cash_arrears>0||$wallet_old->cash_arrears>0){
	    			return $this->error('会员卡还有欠款，请让会员付清欠款后再进行转卡操作');
	    		}
		    }
    		// 订单数据验证
	        $validate1 = new Validate($this->cashier_rule);
	        $result1 = $validate1->check(['number'=>$data['work_order']]);
	        if(!$result1){
	            return  $validate1->getError();
	        }

    		if(isset($data['is_change'])){
    			/*$use = Db::table('used_data')->where('id=78')->find();
		        if($use['status']){
		        	$card = json_decode($this->getCTbyRecharge($data['real'],$data['arrears'],0,$data['card_no']),true);
			        if($card['status']==1){
			        	$data['newcardid'] = $card['id'];
			        }else{
			        	return $this->error('没有对应的会员卡类型，充值失败');
			    	}
		        }*/

    			//会员卡表
	    		$memberdata = [
	    			'card_no' => $data['newcardid'],
	    			'card_type' => $data['newcardtype'],
	    			'company_area' => $member_old['company_area'],
	    			'mobile_phone' => $member_old['mobile_phone'],
	    			'sex' => $member_old['sex'],
	    			'start_time' => $member_old['start_time'],
	    			'end_time' => $member_old['end_time'],
	    			'ID_NO' =>$member_old['ID_NO'],
	    			'birthday' => $member_old['birthday'],
	    			'company_id' => $member_old['company_id'],
	    			'status' => 1,
	    			'name' => $member_old['name'],
	    			'is_msn' => $member_old['is_msn'],
	    			'last_recharge' => $data['real'],
	    			'last_recharge_time' => time(),
	    			'last_recharge_addr' => Session::get('company_id'),
	    			'from' => 1,
	    			'recharge' => $member_old['recharge']+$data['real'],
	    			'last_card_id' => $member_old['id'],
	    			'last_card_type' => $member_old['card_type'],
	    		];
	    		// 会员卡数据验证
		        $validate = new Validate($this->member_rule);
		        $result   = $validate->check($memberdata);

		        if(!$result){
		            return  $validate->getError();
		        }
		        if(!$member->save($memberdata)){
		        	return $this->error($member->getError());
		        }
		        $member_old->status = 2;
		    	$member_old->save();

		        //钱包
		        
		    	if($data['wallet']==7){
		    		$walletdata['cash_arrears'] = $wallet_old['cash_arrears']+$data['arrears'];
		    		$walletdata['cash'] = $wallet_old['cash']+$data['real'];
		    		$walletdata['arrears_c'] = ($wallet_old['cash_arrears']+$data['arrears'])>0?2:0;
		    	}elseif($data['wallet']==8){
		    		$walletdata['ewallet'] = $wallet_old['ewallet']+$data['real'];
		    	}elseif($data['wallet']==9){
		    		$walletdata['old_cash'] = $wallet_old['old_cash']+$data['real'];
		    		$walletdata['old_cash_arrears'] = $wallet_old['old_cash_arrears']+$data['arrears'];
		    	}
		    		if($data['arrears']){
		    			$walletdata['arrears_c'] = 2;
		    		}
		    		$walletdata['member_id'] = $member['id'];
	        		$wallet->save($walletdata);
	        		//订单

		    		$cashier = new Cashier;
		    		$cashierdata = [
		    			'company_id' => Session::get('company_id'),
		    			'type' => 1,
		    			'number' => $data['work_order'],
		    			'member_id' => $member['id'],
		    			'member_no' => $member['card_no'],
		    			'sex' => $member['sex'],
		    			'count' => 1,
		    			'real_money' => $data['real'],
		    			'time' => time(),
		    			'active_id' => Session::get('family_id'),
		    			'order_type' => 4,
		    			'should_money' => $data['total'],
		    			'status' => 1,
		    		];
		    		$cashier->save($cashierdata);

		    		//订单支付类型
		    		$cpwdata = [];
		    		foreach ($data['pay_way'] as $k => $v) {
		    			if($data['money'][$k]){
		    				$cpwdata[$k] = [
			    				'pay_type' => $v,
			    				'money' => $data['money'][$k],
			    				'cashier_id' => $cashier->id,
			    				'family_no' => $data['number'][$k],
			    				'share_results' => $data['share_results'][$k],
			    				'wallet_type' => $data['wallet'],
			    			]; 
		    			}
		    		}
		    		$cpw = new CPW;
		    		$cpw->saveAll($cpwdata);

		    		//金额变动表
		    		$cashchangedata = [
		    			'wallet_type' => $data['wallet'],
		    			'active_type' => 2,
		    			'order_no' => $data['work_order'],
		    			'cash' => $data['real'],
		    			'last_balance' => $wallet_old['cash'],
		    			'this_balance' => $wallet['cash'],
		    			'services_id' => 0,
		    			'services_count' => 1,
		    			'services_name' => '充值',
		    			'pay_type' => $data['pay_way'][0],
		    			'pay_name' => 1,
		    			'member_id' => $member['id'],
		    			'member_name' => $member['name'],
		    			'member_no' => $member['card_no'],
		    			'cashier_id' => $cashier->id,
		    		];
		    		$this->setConsumptionLog($cashchangedata);

		    		//app需要的消费日志
	    			$company = Company::get(Session::get('company_id'));
	    			
	    			$aclogdata = [
	    				'cashier_id' => $cashier->id,
	    				'time' => time(),//下单时间
	    				'discount_money' =>  $data['real'], //消费金额
	    				'company_id' => Session::get('company_id'),//消费门店
	    				'company_name' => $company['full_name'],//消费门店名字
	    				'coupon' => '',//优惠券
	    				'order_no' => $data['work_order'],//单号
	    				'payway' => '', //支付方式
	    				'payway_id' => $data['pay_way'][0],//支付方式
	    				'pay_time' => time(),//支付时间
	    				'pay_status' => 1,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
	    				'cpw' => $cpwdata,
	    				'app_order_id' => '',
	    				'app_order_name' => $company['full_name'].'充值',//订单名称
	    				'card_no' => $member['card_no'],//会员卡号
	    				'order_type' => 7,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    				'cashier_type' => 4,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
	    			];
	    			$this->setAppConsumptionLog($aclogdata);
	        		
		    		$wallet = $wallet->where(array('member_id'=>$member['id']))->find();
    		}else{
		        /*if($data['wallet']==7){
		        	$use = Db::table('used_data')->where('id=78')->find();
			        if($use['status']){
			        	$card = json_decode($this->getCTbyRecharge($data['real'],$data['arrears'],0,$data['card_no']),true);
				        if($card['status']==1){
				        	$data['card_type'] = $card['id'];
				        }else{
				        	return $this->error('没有对应的会员卡类型，转卡失败');
				    	}
			        }
		        }*/
    			
    			//会员卡表
	    		$member_old->last_card_type = $member_old['card_type'];
	    		$member_old->card_type = $data['newcardtype'];
	    		$member_old->recharge = $member_old['recharge']+$data['real'];
	    		$member_old->last_recharge = $data['real'];
	    		$member_old->last_recharge_time = time();
	    		$member_old->last_recharge_addr = Session::get('company_id'); 
	    		$member_old->from = 1;
	    		if(!$member_old->save()){
		        	return $this->error($member_old->getError());
		        }
		        //钱包
		        $wallet = new Wallet;
	        		$wallet_old = $wallet->where(array('member_id'=>$member_old['id']))->find();
		    		if($data['wallet']==7){
		    			$walletdata['cash_arrears'] = $wallet_old['cash_arrears']+$data['arrears'];
		    			$walletdata['cash'] = $wallet_old['cash']+$data['real'];
		    			$walletdata['arrears_c'] = ($wallet_old['cash_arrears']+$data['arrears'])>0?2:0;
		    		}elseif($data['wallet']==8){
		    			$walletdata['ewallet'] = $wallet_old['ewallet']+$data['real'];
		    		}elseif($data['wallet']==9){
		    			$walletdata['old_cash'] = $wallet_old['old_cash']+$data['real'];
		    			$walletdata['old_cash_arrears'] = $wallet_old['old_cash_arrears']+$data['arrears'];
		    		}
	        		$wallet->save($walletdata,array('member_id'=>$member_old['id']));

	        		//订单

		    		$cashier = new Cashier;
		    		$cashierdata = [
		    			'company_id' => Session::get('company_id'),
		    			'type' => 1,
		    			'number' => $data['work_order'],
		    			'member_id' => $member_old['id'],
		    			'member_no' => $member_old['card_no'],
		    			'sex' => $member_old['sex'],
		    			'count' => 1,
		    			'real_money' => $data['real'],
		    			'time' => time(),
		    			'active_id' => Session::get('family_id'),
		    			'order_type' => 4,
		    			'should_money' => $data['total'],
		    			'status' => 1,
		    		];
		    		$cashier->save($cashierdata);

		    		//订单支付类型
		    		$cpwdata = [];
		    		foreach ($data['pay_way'] as $k => $v) {
		    			/*if($data['money'][$k]){
		    				$cpwdata[$k] = [
			    				'pay_type' => $v,
			    				'money' => $data['money'][$k],
			    				'cashier_id' => $cashier->id,
			    				'family_no' => $data['number'][$k],
			    				'share_results' => $data['share_results'][$k],
			    				'wallet_type' => $data['wallet'],
			    			]; 
		    			}*/
		    			$cpwdata[$k] = [
			    				'pay_type' => $v,
			    				'money' => $data['money'][$k],
			    				'cashier_id' => $cashier->id,
			    				'family_no' => $data['number'][$k],
			    				'share_results' => $data['share_results'][$k],
			    				'wallet_type' => $data['wallet'],
			    			]; 
		    		}
		    		$cpw = new CPW;
		    		$re = $cpw->saveAll($cpwdata);


		    		//金额变动表
		    		$cashchangedata = [
		    			'wallet_type' => $data['wallet'],
		    			'active_type' => 2,
		    			'order_no' => $data['work_order'],
		    			'cash' => $data['real'],
		    			'last_balance' => $wallet_old['cash'],
		    			'this_balance' => $wallet['cash'],
		    			'services_id' => 0,
		    			'services_count' => 1,
		    			'services_name' => '充值',
		    			'pay_type' => $data['pay_way'][0],
		    			'pay_name' => 1,
		    			'member_id' => $member_old['id'],
		    			'member_name' => $member_old['name'],
		    			'member_no' => $member_old['card_no'],
		    			'cashier_id' => $cashier->id,
		    		];
		    		$this->setConsumptionLog($cashchangedata);

		    		$wallet = $wallet_old;

		    		//app需要的消费日志
	    			$company = Company::get(Session::get('company_id'));
	    			
	    			$aclogdata = [
	    				'cashier_id' => $cashier->id,
	    				'time' => time(),//下单时间
	    				'discount_money' =>  $data['real'], //消费金额
	    				'company_id' => Session::get('company_id'),//消费门店
	    				'company_name' => $company['full_name'],//消费门店名字
	    				'coupon' => '',//优惠券
	    				'order_no' => $data['work_order'],//单号
	    				'payway' => '', //支付方式
	    				'payway_id' => $data['pay_way'][0],//支付方式
	    				'pay_time' => time(),//支付时间
	    				'pay_status' => 1,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
	    				'cpw' => $cpwdata,
	    				'app_order_id' => '',
	    				'app_order_name' => $company['full_name'].'充值',//订单名称
	    				'card_no' => $member_old['card_no'],//会员卡号
	    				'order_type' => 7,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    				'cashier_type' => 4,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
	    			];
	    			$this->setAppConsumptionLog($aclogdata);
    		}
    			//会员卡日志表
		    	$cardlogdata = [
		    		'company' => Session::get('company_id'),
		    		'member_id' => $member_old['id'],
		    		'active_type' => 3,
		    	];
		    	$this->setCardLog($cardlogdata);
		    	$this->sendRechargeMsg($member_old['mobile_phone'],$member_old['name'],$member_old['card_no'],$data['real'],$wallet);

		    	$this->redirect('MemberCard/print_cashier', ['cashier_id' => $cashier->id,'url'=>'/Index/MemberCard/transferCard','msg'=>'转卡成功']);
		    	//return $this->success('会员卡转卡成功','/Index/member_card/transferCard');
    	}else{
    		//会员卡类型
	    	$nctlist = CardTypeCompany::alias('ctc')->field("mct.*")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
	    	$this->assign('nctlist',$nctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	unset($paylist[1]);//消除电子钱包
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1 and level=1 or level=0')->select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData(10);
	    	$this->assign('paywaylist',$paywaylist);
	    	return $this->fetch();
    	}
    }
    //会员卡换卡
    public function changeCard($parent_id = 0){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$member = new MC;
    		$member_old = $member->where(array('card_no'=>$data['card_no']))->find();
    		if(empty($member_old)){
    			return $this->error('会员卡不存在');
    		}
    		$member_new = $member->where(['card_no'=>$data['newcardid']])->find();
    		if($member_new){
    			return $this->error('新会员卡已存在');
    		}
    		if($member_old->status!=1){
    			return $this->error('会员卡不是已开卡状态，不能换卡');
    		}
    		//会员卡表
    		$memberdata = [
    			'card_no' => $data['newcardid'],
    			'card_type' => $member_old['card_type'],
    			'company_area' => $member_old['company_area'],
    			'mobile_phone' => $member_old['mobile_phone'],
    			'sex' => $member_old['sex'],
    			'start_time' => $member_old['start_time'],
    			'end_time' => $member_old['end_time'],
    			'ID_NO' =>$member_old['ID_NO'],
    			'birthday' => $member_old['birthday'],
    			'company_id' => $member_old['company_id'],
    			'status' => 1,
    			'name' => $member_old['name'],
    			'is_msn' => $member_old['is_msn'],
    			'last_recharge' => $member_old['last_recharge'],
    			'last_recharge_time' => $member_old['last_recharge_time'],
    			'last_recharge_addr' => $member_old['last_recharge_addr'],
    			'from' => $member_old['from'],
    			'recharge' => $member_old['recharge'],
    			'last_card_id' => $member_old['id'],
    		];
    		// 会员卡数据验证
	        $validate = new Validate($this->member_rule);
	        $result   = $validate->check($memberdata);
	        
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($member->save($memberdata)){
		    		$member_old->status = 2;
		    		$member_old->save();
	        		//钱包表

	        		$wallet = new Wallet;
	        		$wallet_old = $wallet->where(array('member_id'=>$member_old->id))->find();
	        		$walletdata = [
	        			'member_id' => $member->id,
	        			'cash_arrears' => $wallet_old['cash_arrears'],
	        			'cash' => $wallet_old['cash'],
	        			'ewallet' => $wallet_old['ewallet'],
	        			'old_cash' => $wallet_old['old_cash'],
	        			'old_cash_arrears' => $wallet_old['old_cash_arrears'],
	        		];
	        		$wallet->save($walletdata);

	        		//会员卡日志表
		    		$cardlogdata = [
		    			'company' => Session::get('company_id'),
		    			'member_id' => $member->id,
		    			'active_type' => 4,
		    		];
		    		$this->setCardLog($cardlogdata);
		    		
		    		return $this->success('会员卡换卡成功','/Index/member_card/changeCard');
		    	}
		    }
    	}else{
	    	//会员卡类型
	    	$ctlist = MCT::select();
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1')->select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData(10);
	    	$this->assign('paywaylist',$paywaylist);
	    	return $this->fetch(); 
	    }
    }
    //会员卡还款
    public function refundCard($parent_id=0){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		//var_dump($data);exit;
    		//$data['total'] = $data['arrears']+$data['real'];
    		$member = new MC;

    		$member_old = $member->where(array('card_no'=>$data['card_no']))->find();
    		if(empty($member_old)){
    			return $this->error('会员卡不存在');
    		}
    		if($member_old->status!=1){
    			return $this->error('会员卡不是已开卡状态，不能还款');
    		}
    		//判定会员卡类型选择
    		/*if($data['wallet']==7){
    			$use = Db::table('used_data')->where('id=80')->find();
		        if($use['status']){
		        	//上次充值金额
		        	$last_cashier = Cashier::where(['member_no'=>$data['card_no'],'order_type'=>2])->order('time DESC')->limit(1)->find();
		        	if(empty($last_cashier)){
		        		$last_cashier['real_money'] = 0;
		        	}
		        	$card = json_decode($this->getCTbyRecharge_transfercard($data['real'],0,$data['card_no']),true);
			        if($card['status']){
			        	//原卡类型（原卡类型为欠款改卡类型前一次的卡类型）为A，欠款转折扣类型为B，
						//步骤1：判定上次充值金额加本次还款金额总额是否达到原卡类型充值标准，若未达到，则自动将会员卡类型转为该金额对应开卡标准的会员卡类型X
						  // 步骤2：若达到原卡类型充值标准，则判定该总额对应哪种类型的开卡标准
						   //若只达到高于原卡类型折扣的会员卡类型，则还原的卡类型即为原卡类型A
						   //步骤3：若达到低于原卡类型折扣的会员金卡类型的开卡标准，则还原卡类型为该金额对应的开卡标准的会员卡类型C
			        	$data['card_type'] = $card['id'];
			        }else{
			        	//计算本次还款和上次充值的和所对应的卡类型
			        	$card = json_decode($this->getCTbyOpen($data['real'],$last_cashier['real_money'],0),true);
			        	if($card['status']){
				        	$data['card_type'] = $card['id'];
				        }else{
				        	return $this->error('没有对应的会员卡类型，开卡失败');
				    	}
			    	}
		        }
    		}*/

    		//会员卡表
    		$memberdata = [
    			'recharge' => $data['wallet']==7||$data['wallet']==9?$member_old->recharge+$data['real']:$member_old->recharge+0,
    		];
    		if(isset($data['card_type'])){
    			$memberdata['card_type'] = $data['card_type'];
    		}
    		//记录上一次卡类型
    		$memberdata['last_card_type'] = $member_old->card_type;
    		//$memberdata['last_card_type'] = $member_old['card_type'];
    		// 会员卡数据验证
	        //$validate = new Validate($this->member_rule);
	        //$result   = $validate->check($memberdata);
	        // 订单数据验证
	        $validate1 = new Validate($this->cashier_rule);
	        $result1 = $validate1->check(['number'=>$data['card_no']]);
	        if(!$result1){
	            return  $validate1->getError();
	        }
	        if(0){
	           // return  $validate->getError();
	        }else{
	        	//钱包表
	        		$wallet = new Wallet;
	        		$wallet_old = $wallet->where(array('member_id'=>$member_old['id']))->find();
		    		if($data['wallet']==7){
		    			if($wallet_old['cash_arrears']==$data['real']){
			    			$walletdata['cash'] = $wallet_old['cash'] + $data['real'];
			    			$walletdata['cash_arrears'] = 0;
			    			$walletdata['arrears_c'] = 0;
		    			}else{
		    				$this->error('请一次性将欠款还清');
		    			}
		    		}elseif($data['wallet']==9){
		    			if(($wallet_old['old_cash_arrears']==$data['real'])){
		    				$walletdata['old_cash_arrears'] = 0;
		    				$walletdata['old_cash'] = $walletdata['old_cash'] + $data['real'];
		    			}else{
		    				//$walletdata['old_cash'] += $data['real']-$wallet_old['old_cash_arrears'];
		    				$this->error('请一次性将欠款还清');
		    			}
		    		}
	        	if($wallet->save($walletdata,array('member_id'=>$member_old['id']))){
	        		$member->save($memberdata,array('card_no'=>$data['card_no']));
	        		//会员卡日志表
		    		$cardlogdata = [
		    			'company' => Session::get('company_id'),
		    			'member_id' => $member_old['id'],
		    			'active_type' => 5,
		    		];
		    		$this->setCardLog($cardlogdata);

		    		//订单
		    		$cashier = new Cashier;
		    		$cashierdata = [
		    			'company_id' => Session::get('company_id'),
		    			'type' => 1,
		    			'number' => $data['work_order'],
		    			'member_id' => $member_old['id'],
		    			'member_no' => $member_old['card_no'],
		    			'sex' => $member_old['sex'],
		    			'count' => 1,
		    			'real_money' => $data['real'],
		    			'time' => time(),
		    			'active_id' => Session::get('family_id'),
		    			'order_type' => 5,
		    			'should_money' => $data['real'],
		    			'status' => 1
		    		];
		    		$cashier->save($cashierdata);
		    		//订单子表
		    		//$cashiersdata = [];
		    		//订单支付类型
		    		$cpwdata = [];
		    		foreach ($data['pay_way'] as $k => $v) {
		    			if($data['money'][$k]){
		    				$cpwdata[$k] = [
			    				'pay_type' => $v,
			    				'money' => $data['money'][$k],
			    				'cashier_id' => $cashier->id,
			    				'family_no' => $data['number'][$k],
			    				'share_results' => $data['share_results'][$k],
			    				'wallet_type' => $data['wallet'],
			    			]; 
		    			}
		    		}
		    		$cpw = new CPW;
		    		$cpw->saveAll($cpwdata);

		    		//金额变动表
		    		$cashchangedata = [
		    			'wallet_type' => $data['wallet'],
		    			'active_type' => 3,
		    			'order_no' => $data['work_order'],
		    			'cash' => $data['real'],
		    			'last_balance' => $data['wallet']==7?$wallet_old['cash']:$walletdata['cash'],
		    			'this_balance' => $data['wallet']==7?$wallet_old['old_cash']:$walletdata['old_cash'],
		    			'services_id' => 0,
		    			'services_count' => 1,
		    			'services_name' => '还款',
		    			'pay_type' => $data['pay_way'][0],
		    			'pay_name' => 1,
		    			'member_id' => $member_old['id'],
		    			'member_name' => $member_old['name'],
		    			'member_no' => $member_old['card_no'], 
		    			'cashier_id' => $cashier->id,
		    		];
		    		$this->setConsumptionLog($cashchangedata);

		    		//app需要的消费日志
	    			$company = Company::get(Session::get('company_id'));
	    			$aclogdata = [
	    				'cashier_id' => $cashier->id,
	    				'time' => time(),//下单时间
	    				'discount_money' =>  $data['real'], //消费金额
	    				'company_id' => Session::get('company_id'),//消费门店
	    				'company_name' => $company['full_name'],//消费门店名字
	    				'coupon' => '',//优惠券
	    				'order_no' => $data['work_order'],//单号
	    				'payway' => '', //支付方式
	    				'payway_id' => $data['pay_way'][0],//支付方式
	    				'pay_time' => time(),//支付时间
	    				'pay_status' => 1,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
	    				'cpw' => $cpwdata,
	    				'app_order_id' => '',
	    				'app_order_name' => $company['full_name'].'充值',//订单名称
	    				'card_no' => $member_old['card_no'],//会员卡号
	    				'order_type' => 7,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    				'cashier_type' => 5,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
	    			];
	    			$this->setAppConsumptionLog($aclogdata);

		    		$this->redirect('MemberCard/print_cashier', ['cashier_id' => $cashier->id,'url'=>'/Index/MemberCard/refundCard','msg'=>'还款成功']);
		    		//return $this->success('会员卡还款成功','/Index/member_card/refundCard');
		    	}
		    }
    	}else{
    		//会员卡类型
	    	$ctlist = MCT::where('status=1 and is_open=1')->select();
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	unset($paylist[1]);//消除电子钱包
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1')->select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData(10);
	    	$this->assign('paywaylist',$paywaylist);
	    	return $this->fetch();
    	}
		 
    }
    //创度会员卡移植
    public function transplantCard($parent_id=0){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$walletdata = [];

	        $member = new MC;
	        
    		//会员卡表
    		$memberdata = [
    			'card_no' => $data['card_no'],
    			'card_type' => $data['card_type'],
    			'company_area' => $data['company_area'],
    			'mobile_phone' => $data['mobile_phone'],
    			'sex' => $data['sex'],
    			'start_time' => $data['start_time']?strtotime($data['start_time']):0,
    			'end_time' => $data['end_time']?strtotime($data['end_time']):0,
    			'ID_NO' => $data['ID_NO'],
    			'birthday' => $data['birthday']?strtotime($data['birthday']):0,
    			'company_id' => Session::get('company_id'),
    			'status' => 1,
    			'name' => $data['name'],
    			'is_msn' => isset($data['is_msn'])?$data['is_msn']:0,
    			'last_recharge' => $data['cash']+$data['ewallet']+$data['old_cash'],
    			'last_recharge_time' => time(),
    			'last_recharge_addr' => '',
    			'remark' => $data['remark'],
    			'from' => 2,
    			'recharge' => $data['cash']+$data['old_cash'],
    		];
    		// 会员卡数据验证
	        $validate = new Validate($this->member_rule);
	        $result   = $validate->check($memberdata);
	        
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($member->save($memberdata)){
	        		//钱包表
		    		$walletdata['cash_arrears'] = $data['cash_arrears'];
		    		$walletdata['cash'] = $data['cash'];
		    		$walletdata['ewallet'] = $data['ewallet'];
		    		$walletdata['old_cash'] = $data['old_cash'];
		    		$walletdata['old_cash_arrears'] = $data['old_cash_arrears'];

		    		$walletdata['member_id'] = $member->id;
	        		$wallet = new Wallet;
	        		$wallet->save($walletdata);

	        		//创度会员卡转换日志表
	        		$chuangdulog = new ChuangduLog;

		    		$chuangdulogdata = [
		    			'name' => $data['name'],
		    			'card_no' => $data['card_no'],
		    			'time' => time(),
		    			'activer_id' => Session::get('login_id'),
		    			'activer' => Session::get('family_name'),
		    			'type' => 2,
		    			'cash' => $data['cash'],
		    			'ewallet' => $data['ewallet'],
		    			'old_cash' => $data['old_cash'],
		    		];
		    		$chuangdulog->save($chuangdulogdata);

		    		return $this->success('创度会员卡移植成功','/Index/member_card/transplantcard');
	        	}else{
	        		return $this->error($member->getError());
	        	}
	        }
    	}else{
    		//会员卡类型
	    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.*")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'mct.from'=>2,'ctc.company_id'=>Session::get("company_id")])->select();
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	if(Session::get('role')==2){
	    		unset($paylist[1]);//消除电子钱包
	    	}
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1 and level=1 or level=0')->select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData(10);
	    	$this->assign('paywaylist',$paywaylist);
	    	//有效期
	    	$this->assign('time',['stime'=>date('Y-m-d'),'etime'=>date('Y-m-d',time()+3600*24*365*20)]);

	    	return $this->fetch(); 
    	}
    }

    //超级管理员修改所有会员字段
    public function superEdit(){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$walletdata = [];

	        $member = new MC;
	        $member_old = MC::where(['card_no'=>$data['card_no']])->find();
	        $wallet_old = Wallet::where(['member_id'=>$member_old['id']])->find();
	        //会员卡修改前数据
	        $member_old_data = [
	        	'card_no' => $member_old['card_no'],
	        	'card_type' => $member_old['card_type'],
    			'company_area' => $member_old['company_area'],
    			'mobile_phone' => $member_old['mobile_phone'],
    			'sex' => $member_old['sex'],
    			'start_time' => $member_old['start_time'],
    			'end_time' => $data['end_time'],
    			'ID_NO' => $member_old['ID_NO'],
    			'birthday' => $member_old['birthday'],
    			'company_id' => $member_old['company_id'],
    			'status' =>  $member_old['status'],
    			'name' =>  $member_old['name'],
    			'is_msn' =>  $member_old['is_msn'],
    			'last_recharge' =>  $member_old['last_recharge'],
    			'last_recharge_time' =>  $member_old['last_recharge_time'],
    			'last_recharge_addr' =>  $member_old['last_recharge_addr'],
    			'remark' =>  $member_old['remark'],
    			'from' =>  $member_old['from'],
    			'recharge' =>  $member_old['recharge'],
    			'cash_arrears' => $wallet_old['cash_arrears'],
    			'cash' => $wallet_old['cash'],
    			'ewallet' => $wallet_old['ewallet'],
    			'old_cash' => $wallet_old['old_cash'],
    			'old_cash_arrears' => $wallet_old['old_cash_arrears'],
	        ];

    		//会员卡表
    		$memberdata = [
    			'card_type' => $data['card_type'],
    			'company_area' => $data['company_area'],
    			'mobile_phone' => $data['mobile_phone'],
    			'sex' => $data['sex'],
    			'start_time' => $data['start_time']?strtotime($data['start_time']):0,
    			'end_time' => $data['end_time']?strtotime($data['end_time']):0,
    			'ID_NO' => $data['ID_NO'],
    			'birthday' => $data['birthday']?strtotime($data['birthday']):0,
    			'status' => 1,
    			'name' => $data['name'],
    			'is_msn' => isset($data['is_msn'])?$data['is_msn']:0,
    			'last_recharge_time' => time(),
    			'last_recharge_addr' => '',
    			'remark' => $data['remark'],
    			'time' => time(),
    		];
    		// 会员卡数据验证
	        //$validate = new Validate($this->member_rule);
	        //$result   = $validate->check($memberdata);
	        
	        if(!1){
	            return  $validate->getError();
	        }else{
	        	if($member->save($memberdata,['card_no'=>$data['card_no']])){
	        		$member = MC::where(['card_no'=>$data['card_no']])->find();
	        		//钱包表
		    		$walletdata['cash_arrears'] = $data['cash_arrears'];
		    		$walletdata['cash'] = $data['cash'];
		    		$walletdata['ewallet'] = $data['ewallet'];
		    		$walletdata['old_cash'] = $data['old_cash'];
		    		$walletdata['old_cash_arrears'] = $data['old_cash_arrears'];
		    		$walletdata['time'] = time();

	        		$wallet = new Wallet;
	        		$wallet->save($walletdata,['member_id'=>$member->id]);

	        		//更改后会员卡信息
	        		$member_new_data = [
	        			'card_no' => $member['card_no'],
			        	'card_type' => $member['card_type'],
		    			'company_area' => $member['company_area'],
		    			'mobile_phone' => $member['mobile_phone'],
		    			'sex' => $member['sex'],
		    			'start_time' => $member['start_time'],
		    			'end_time' => $member['end_time'],
		    			'ID_NO' => $member['ID_NO'],
		    			'birthday' => $member['birthday'],
		    			'company_id' => $member['company_id'],
		    			'status' =>  $member['status'],
		    			'name' =>  $member['name'],
		    			'is_msn' =>  $member['is_msn'],
		    			'last_recharge' =>  $member['last_recharge'],
		    			'last_recharge_time' =>  $member['last_recharge_time'],
		    			'last_recharge_addr' =>  $member['last_recharge_addr'],
		    			'remark' =>  $member['remark'],
		    			'from' =>  $member['from'],
		    			'recharge' =>  $member['recharge'],
		    			'cash_arrears' => $wallet['cash_arrears'],
		    			'cash' => $wallet['cash'],
		    			'ewallet' => $wallet['ewallet'],
		    			'old_cash' => $wallet['old_cash'],
		    			'old_cash_arrears' => $wallet['old_cash_arrears'],
			        ];
	        		//超级管理员修改会员卡日志表
	        		$superlog = new SuperEditLog;

		    		$superlogdata = [
		    			'before' => serialize($member_old_data),
		    			'after' => serialize($member_new_data),
		    			'time' => time(),
		    			'activer' => Session::get('login_id'),
		    		];
		    		$superlog->save($superlogdata);

		    		//会员卡钱包日志
		    		if($walletdata['cash']!=$wallet_old['cash']){
		    			$cLog = [
		    				'wallet_type' => 7,
			    			'active_type' => 5,
			    			'order_no' => '',
			    			'last_balance' => $wallet_old['cash'],
			    			'this_balance' => $walletdata['cash'],
			    			'services_id' => 0,
			    			'services_count' => 0,
			    			'services_name' => '',
			    			'pay_type' => 11,
			    			'services_family_id' => Session::get('family_id'),
			    			'services_family' =>Session::get('family_name'),
			    			'member_id' => $member['id'],
			    			'member_name' => $member['name'],
			    			'member_no' => $member['card_no'],
			    			'cashier_id' => 0,
			    			'cash' => $walletdata['cash']-$wallet_old['cash'],
		    			];

		    			$this->setConsumptionLog($cLog);
		    		}
		    		if($walletdata['ewallet']!=$wallet_old['ewallet']){
		    			$cLog = [
		    				'wallet_type' => 8,
			    			'active_type' => 5,
			    			'order_no' => '',
			    			'last_balance' => $wallet_old['ewallet'],
			    			'this_balance' => $walletdata['ewallet'],
			    			'services_id' => 0,
			    			'services_count' => 0,
			    			'services_name' => '',
			    			'pay_type' => 11,
			    			'services_family_id' => Session::get('family_id'),
			    			'services_family' =>Session::get('family_name'),
			    			'member_id' => $member['id'],
			    			'member_name' => $member['name'],
			    			'member_no' => $member['card_no'],
			    			'cashier_id' => 0,
			    			'cash' => $walletdata['ewallet']-$wallet_old['ewallet'],
		    			];

		    			$this->setConsumptionLog($cLog);
		    		}
		    		if($walletdata['old_cash']!=$wallet_old['old_cash']){
		    			$cLog = [
		    				'wallet_type' => 9,
			    			'active_type' => 5,
			    			'order_no' => '',
			    			'last_balance' => $wallet_old['old_cash'],
			    			'this_balance' => $walletdata['old_cash'],
			    			'services_id' => 0,
			    			'services_count' => 0,
			    			'services_name' => '',
			    			'pay_type' => 11,
			    			'services_family_id' => Session::get('family_id'),
			    			'services_family' =>Session::get('family_name'),
			    			'member_id' => $member['id'],
			    			'member_name' => $member['name'],
			    			'member_no' => $member['card_no'],
			    			'cashier_id' => 0,
			    			'cash' => $walletdata['old_cash']-$wallet_old['old_cash'],
		    			];

		    			$this->setConsumptionLog($cLog);
		    		}

		    		//会员卡操作日志
		    		if($member_old['mobile_phone']!=$member['mobile_phone']||$member_old['name']!=$member['name']||$member_old['card_type']!=$member['card_type']){
		    			$company = Company::get(Session::get('company_id'));
		    			if($member_old['card_type']!=$member['card_type']){
		    				$card_old_type = MCT::get($member_old['card_type']);
		    				$card_type = MCT::get($member['card_type']);
		    			}else{
		    				$card_old_type = $card_type = MCT::get($member_old['card_type']);
		    			}
		    			$cardlogdata = [
				    		'mark' => '修改前会员卡资料为，手机号：'.$member_old['mobile_phone'].'，姓名：'.$member_old['name'].'，卡类型：'.$card_old_type.'|修改后为，'.'手机号：'.$member['mobile_phone'].'，姓名：'.$member['name'].'卡类型：'.$card_type,
				    		'time' => time(),
				    		'member_id' => $member['id'],
				    		'member_name' => $member['name'],
				    		'member_card' => $member['card_no'],
				    		'active_type' => 7,
				    	];
		    			$this->setCardLog($cardlogdata);
		    		}
		    		
		    		return $this->success('会员卡修改成功','/Index/member_card/superEdit');
	        	}else{
	        		return $this->error($member->getError());
	        	}
	        }
    	}else{
    		//会员卡类型
	    	$ctlist = MCT::where(['status'=>1])->order('id desc')->select();
	    	foreach ($ctlist as $k => $v) {
	    		if($v['from']==1){
	    			$ctlist[$k]['new_name'] = '新-'.$v['name']; 
	    		}elseif($v['from']==2){
	    			$ctlist[$k]['new_name'] = '老-'.$v['name']; 
	    		}
	    	}
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	if(Session::get('role')==2){
	    		unset($paylist[1]);//消除电子钱包
	    	}
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1 and level=1 or level=0')->select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData(10);
	    	$this->assign('paywaylist',$paywaylist);
	    	//有效期
	    	$this->assign('time',['stime'=>date('Y-m-d'),'etime'=>date('Y-m-d',time()+3600*24*365*20)]);

	    	return $this->fetch(); 
    	}
    }
    //删除会员
    public function superdelete(){
    	$data = input();
    	$member = MC::where(['card_no'=>$data['card_no']])->find();
    	$wallet = Wallet::where(['member_id'=>$member['id']])->find();
    	if($member->delete()){
    		if($wallet->delete()){
    			return $this->success('会员卡删除成功','/Index/member_card/superEdit');
    		}else{
    			return $this->error($wallet->getError());
    		}
    	}else{
    		return $this->error($member->getError());
    	}
    }

    //修改备注
    public function editRemark(){
    	$data = input();
    	if(!$data['card_no']){
    		echo json_encode(['status'=>0]);
    	}else{
    		$member = new MC;
    		$member_old = MC::where(['card_no'=>$data['card_no']])->find();
    		if($member->where(['card_no'=>$data['card_no']])->setField('remark',$data['remark'])){
    			$member = $member->where(['card_no'=>$data['card_no']])->find();
    			//会员卡日志表
		    		$cardlogdata = [
		    			'company' => Session::get('company_id'),
		    			'member_id' => $member['id'],
		    			'active_type' => 8,
		    			'remark' => isset($member_old['remark'])?$member_old['remark']:'',
		    		];
		    		$this->setCardLog($cardlogdata);
    			echo json_encode(['status'=>1]);
    		}else{
    			echo json_encode(['status'=>0]);
    		}
    	}
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
	    		];
    		}
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'未开启开卡标准','data'=>$clist]);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'未开启开卡标准','data'=>$clist]);
    		}
    	}
    	$data = input();
    	$data['real'] = isset($data['real'])?$data['real']:$real;
    	$data['arrears'] = isset($data['arrears'])?$data['arrears']:$arrears;

    	$total = $data['real'] + $data['arrears'];

    	$where = 'mct.status=1 and mct.from=1 and mct.is_open=1 and ctc.company_id='.Session::get("company_id").' and ((ctc.open_standard <='.$total.' and ctc.open_standard_e >='.$total.') or (ctc.open_standard <='.$total.' and ctc.open_standard_e=0))';
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
    		];
    	}
    	if($ajax){
    		echo json_encode(['status'=>1,'data'=>$list]);
    		exit;
    	}else{
    		return json_encode(['status'=>1,'data'=>$list]);
    	}
    }
    /*public function getCTbyOpen($real = 0,$arrears = 0,$ajax = 1){
    	$use = Db::table('used_data')->where('id=78')->find();
    	if(!$use['status']){
    		if($ajax){
    			echo json_encode(['status'=>2,'msg'=>'未开启开卡标准']);
    			exit;
    		}else{
    			return json_encode(['status'=>2,'msg'=>'未开启开卡标准']);
    		}
    	}
    	$data = input();
    	$data['real'] = isset($data['real'])?$data['real']:$real;
    	$data['arrears'] = isset($data['arrears'])?$data['arrears']:$arrears;

    	$total = $data['real'] + $data['arrears'];
    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.id,mct.open_standard")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
    	if(empty($ctlist)){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    		}
    	}
    	$list = [];
    	foreach ($ctlist as $k => $v) {
    		if($total - $v['open_standard']>=0){
    			$list[$v['id']] = $total - $v['open_standard'];
    		}
    	}
    	if(empty($list)){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    		}
    	}
    	$min = array_search(min($list),$list);
    	if($min){
    		if($ajax){
    			echo json_encode(['status'=>1,'id'=>$min]);
    			exit;
    		}else{
    			return json_encode(['status'=>1,'id'=>$min]);
    		}
    	}else{
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    		}
    	}
    }*/
    //获取会员卡充值标准所对应的卡类型(充值，转卡)
    public function getCTbyRecharge($real = 0,$arrears = 0,$ajax = 1,$card_no=null){
    	$use = Db::table('used_data')->where('id=78')->find();
    	if(!$use['status']){
    		$list = CardTypeCompany::alias('ctc')->field("mct.*")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.from'=>1,'mct.is_recharge'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
    		$clist = [];
    		foreach ($list as $k => $v) {
    			$clist[] = [
	    			'id' => $v['id'],
	    			'name' => $v['name'],
	    		];
    		}
    		if($ajax){
    			echo json_encode(['status'=>2,'msg'=>'未开启充值标准','data'=>$clist]);
    			exit;
    		}else{
    			return json_encode(['status'=>2,'msg'=>'未开启充值标准','data'=>$clist]);
    		}
    	}
    	$data = input();
    	$data['real'] = isset($data['real'])?$data['real']:$real;
    	$data['arrears'] = isset($data['arrears'])?$data['arrears']:$arrears;

    	$total = $data['real'] + $data['arrears'];

    	$where = 'mct.status=1 and mct.from=1 and mct.is_recharge=1 and ctc.company_id='.Session::get("company_id").' and ((ctc.recharge_standard <='.$total.' and ctc.recharge_standard_e >='.$total.') or (ctc.recharge_standard <='.$total.' and ctc.recharge_standard_e=0))';
    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.id,mct.name,ctc.recharge_standard_e,ctc.recharge_standard")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where($where)->select();
    	if(empty($ctlist)){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    		}
    	}
    	$list = [];
    	foreach ($ctlist as $k => $v) {
    		$list[] = [
    			'id' => $v['id'],
    			'name' => $v['name'],
    		];
    	}
    	if($ajax){
    		echo json_encode(['status'=>1,'data'=>$list]);
    		exit;
    	}else{
    		return json_encode(['status'=>1,'data'=>$list]);
    	}
    }
    /*
    public function getCTbyRecharge($real = 0,$arrears = 0,$ajax = 1,$card_no=null){
    	$use = Db::table('used_data')->where('id=78')->find();
		if(!$use['status']){
			if($ajax){
    			echo json_encode(['status'=>2,'msg'=>'未开启充值转卡标准']);
    			exit;
    		}else{
    			return json_encode(['status'=>2,'msg'=>'未开启充值转卡标准']);
    		}
		}
    	$data = input();
    	$data['real'] = isset($data['real'])?$data['real']:$real;
    	$data['arrears'] = isset($data['arrears'])?$data['arrears']:$arrears;
    	$data['card_no'] = isset($data['card_no'])?$data['card_no']:$card_no;
    	if(!$data['card_no']){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'请输入会员卡号']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'请输入会员卡号']);
    		}
    	}
    	//判定是否满足会员卡本身卡类型的充值标准
    	//如不满足，直接打回
    	//如满足，往下走
    	$total = $data['real'] + $data['arrears'];
    	$member_type = MCT::alias('mct')->join('member as m','mct.id=m.card_type','left')->where(['m.card_no'=>$data['card_no']])->find();
    	if($member_type['recharge_standard']>$total){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'不满足充值标准，不能充值']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'不满足充值标准，不能充值']);
    		}
    	}
    	//获取该门店所有会员卡类型
    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.id,mct.recharge_standard,ctcd.discount,mct.open_standard")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->join('card_type_children ctcd','ctc.card_type_id = ctcd.parent_id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id"),'ctcd.pay_id'=>7])->select();
    	if(empty($ctlist)){
    		if($ajax){
    			echo json_encode(['status'=>1,'id'=>$member_type['card_type']]);
    			exit;
    		}else{
    			return json_encode(['status'=>1,'id'=>$member_type['card_type']]);
    		}
    	}
    	//获取目前所有满足现在金额 开卡标准的开类型
    	$list = [];
    	$ratelist = [];
    	foreach ($ctlist as $k => $v) {
    		if($total - $v['open_standard']>=0){
    			$list[$v['id']] = $total - $v['open_standard'];
    			$ratelist[$v['id']] = $v['discount'];
    		}
    	}
    	if(empty($list)){
    		if($ajax){
    			echo json_encode(['status'=>1,'id'=>$member_type['card_type']]);
    			exit;
    		}else{
    			return json_encode(['status'=>1,'id'=>$member_type['card_type']]);
    		}
    	}
    	//判定新会员卡类型和以前会员卡类型的折扣
    	//如新会员卡类型折扣低，会员卡升级到新的会员卡类型
    	//如以前会员卡类型折扣低，保持现有会员卡类型
    	$min = array_search(min($list),$list);
    	if($min){
    		$card_rate = CTC::where(['parent_id'=>$member_type['card_type'],'pay_id'=>7])->find();
    		if($ratelist[$min]<$card_rate['discount']){
    			if($ajax){
	    			echo json_encode(['status'=>1,'id'=>$min]);
	    			exit;
	    		}else{
	    			return json_encode(['status'=>1,'id'=>$min]);
	    		}
    		}else{
    			if($ajax){
	    			echo json_encode(['status'=>1,'id'=>$member_type['card_type']]);
	    			exit;
	    		}else{
	    			return json_encode(['status'=>1,'id'=>$member_type['card_type']]);
	    		}
    		}
    	}else{
    		if($ajax){
    			echo json_encode(['status'=>1,'id'=>$member_type['card_type']]);
    			exit;
    		}else{
    			return json_encode(['status'=>1,'id'=>$member_type['card_type']]);
    		}
    	}
    }
    */
    public function getabc(){
    	echo $this->getCTbyRecharge_transfercard(0,2000,0,'0285826');
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
		$last_cashier = C::where(['member_no'=>$data['card_no'],'order_type'=>2])->order('time DESC')->limit(1)->find();
    	$total = $data['real'] + $last_cashier['real_money'];
    	$member_type = MCT::alias('mct')->join('member as m','mct.id=m.card_type','left')->where(['m.card_no'=>$data['card_no']])->find();
    	
    	return $this->getCTbyOpen($data['real'],$last_cashier['real_money'],$ajax);
    }
        //发送充值短信
    public function sendRechargeMsg($mobile_phone,$name,$card_no,$total,$wallet){
    	$msg = '尊敬的会员'.$name.',会员卡卡号【'.$card_no.'】本次充值金额为'.$total.'元，储值账户余额'.$wallet['cash'].'元，电子钱包余额'.$wallet['ewallet'].'元，老疗程系统余额'.$wallet['old_cash'].'元，登录手机APP【鼎族】可查看消费明细';
		//$re = self::msnSend($mobile_phone,$msg);
		$r = $re = '20170912152104,0
17091215210425466';
    	//$r = $re = self::msnSend($data['phone'],$data['msg']);
$im = '
';
    	$re = explode(',', $re);
    	$re = explode($im, $re[1]);
		self::msgLog(['type'=>3,'content'=>$msg,'phone'=>$mobile_phone,'status'=>$re,'re_code'=>$r]);
    }
    //打印充值发票
    public function print_cashier($cashier_id=0,$url='',$msg=''){
    	if($cashier_id){
    		$cashier = Cashier::get($cashier_id);
    		$this->assign('cashier',$cashier);
    		//支付方式、支付金额
    		$used_data = $this->getUsedData(10);
    		$paylist = [];
    		foreach ($used_data as $k => $v) {
    			$paylist[$v['id']] = $v['name'];
    		}
			$cpw = CPW::where(['cashier_id'=>$cashier_id])->select();
			foreach ($cpw as $k => &$v) {
				$v['pay_name'] = $paylist[$v['pay_type']];
			}
			$this->assign('cpw',$cpw);

    		$this->assign('member',MC::get($cashier['member_id']));

    		$wallet = Wallet::where(['member_id'=>$cashier['member_id']])->find();
    		$this->assign('wallet',$wallet);

    		$this->assign('company',Company::get(Session::get('company_id')));

			//充值前的数据
			$before_recharge = [];
			//如果消费之后  充值前剩余金额  的值会出现负值（该函数只能用于充值的时候，不能用于重打小票）
			$before_recharge['last_balance'] = ($cpw[0]['wallet_type']==7?$wallet['cash']:$wallet['old_cash'])-$cashier['real_money'];
			$before_recharge['should_money'] = $cashier['should_money'];
			$before_recharge['arrears_money'] = $cashier['should_money']-$cashier['real_money'];
			$this->assign('before_recharge',$before_recharge);
			$this->assign('wallet_type',$cpw[0]['wallet_type']);
    		$this->assign('msg',$msg);
    		$this->assign('code',1);
    		$this->assign('url',$url);
    		$this->assign('wait',3000);
    		return $this->fetch();
    	}
    }
    //财务高级功能
    //批量开卡
    public function batchOpenCard(){
    	if(Request::instance()->isPost()){
    		$data = input();
    		$time = time();
    		$length = strlen($data['end_no']);
    		for (; $data['satart_no'] <= $data['end_no']; $data['satart_no']++) { 
    			//是否遇4跳过
    			if(isset($data['exclude'])){
    				if(strpos((string)$data['satart_no'], '4')!==false){
				        continue;
				    }
    			}

	    		$card_no = $data['prefix_no'].str_pad($data['satart_no'],$length,'0',STR_PAD_LEFT);

	    		//检查是否被占用
    			$member = MC::where(['card_no'=>$card_no])->find();
    			if(!empty($member)){
    				echo $card_no.'卡号已存在<br/>';
    				continue;
    			}
    			$member = new MC;

	    		$memberdata = [
	    			'card_no' => $card_no,
	    			'card_type' => $data['card_type'],
	    			'company_area' => $data['company_area'],
	    			'mobile_phone' => $data['mobile_phone'],
	    			'sex' => $data['sex'],
	    			'start_time' => $data['start_time']?strtotime($data['start_time']):0,
	    			'end_time' => $data['end_time']?strtotime($data['end_time']):0,
	    			//'birthday' => $data['birthday']?strtotime($data['birthday']):0,
	    			'company_id' => $data['company'],
	    			'status' => 1,
	    			'name' => $data['name'],
	    			'last_recharge' => $data['cash'],
	    			'last_recharge_time' => time(),
	    			'last_recharge_addr' => Session::get('company_id'),
	    			'from' => 3,
	    			'recharge' => $data['cash']?$data['cash']:0,
	    			'time' => $time,
	    		];
	    		if($member->save($memberdata)){
	    			$wallet = new Wallet;
	    			$walletdata = [
	    				'member_id' => $member['id'],
	    				'cash' => $data['cash']?$data['cash']:0,
	    				'ewallet' => $data['ewallet']?$data['ewallet']:0,
	    				'old_cash' => $data['old_cash']?$data['old_cash']:0,
	    			];
	    			if($wallet->save($walletdata)){
	    				echo $card_no.'生成成功<br/>';
	    			}else{
	    				return $this->error('从会员卡：'.$card_no.'之后生成失败。并且'.$card_no.'的钱包未生成成功。');
	    			}
	    		}else{
		            return $this->error('从会员卡：'.$card_no.'开始生成失败');
	    		}
	    	}
	    	$this->success('会员资料添加成功','/Index/member_card/batchOpenCard','',3000);
    	}
    	//会员卡类型
    	$clist = MCT::where(['status'=>1])->select();
    	$this->assign('ctlist',$clist);
    	//卡可消费地区
	    $company = Company::where('status=1 and level=1 or level=0')->select();
	    $companylist = [];
	    foreach ($company as $k => $v) {
	    	$companylist[$v['id']] = $v['full_name'];
	    }
	    $this->assign('companylist',$companylist);
	    //公司列表
	    $comlist = $this->getCompanyList(5,1);
	    $this->assign('comlist',$comlist);

	    //有效期
	    $this->assign('time',['stime'=>date('Y-m-d'),'etime'=>date('Y-m-d',time()+3600*24*365*20)]);

    	return $this->fetch();
    }
    //检测自动生成号段是否有重复
    public function checkCardNo(){
    	$data = input();
    	$str = '';
    	for (; $data['start'] < $data['end']; $data['start']++) { 
    		$str.=$data['prefix_no'].$data['start'].',';
    	}
    	$where['card_no'] = array('in',$str);
    	$member = MC::where($where)->find();
    	if($member){
    		$re = [
    			'status' => 1,
    			'msg' => '会员卡号已经存在，请重新输入号段。'
    		];
    	}else{
    		$re = [
    			'status' => 1,
    			'msg' => '该号段可以生成'
    		];
    	}
    	echo json_encode($re);
    }
    //所开卡列表
    public function batchCardList(){
    	$list = MC::alias('mc')->field('mc.*,c.full_name,mct.name as cname,w.ewallet')->join('company c','mc.company_id=c.id','left')->join('member_card_type mct','mc.card_type=mct.id','left')->join('wallet w','mc.id=w.member_id','left')->where(['mc.from'=>3])->order('mc.time desc')->paginate(15,false);
    	$this->assign('list',$list);
    	return $this->fetch();
    }
    //app消费日志
    public function setAppConsumptionLog($data=[]){
    	$idata = [
    		'order_type' => $data['order_type'],
    		'time' => time(),
    		'app_order_id' => '',
    		'cashier_id' => $data['cashier_id'],
    		'order_no' => $data['order_no'],
    		'text' => json_encode($data),
    		'card_no' => $data['card_no'],
    	];
    	if(ACL::insert($idata)){
    		return true;
    	}else{
    		return false;
    	}
    }
    //更改错误的app日志数据
    public function updateapplog(){
    	$list = ACL::where(['order_type'=>1,'card_no'=>''])->select();
    	foreach ($list as $k => $v) {
    		$cashier = Cashier::get($v['cashier_id']);
    		$text = json_decode($v['text'],true);
    		$text['card_no'] = $cashier['member_no'];
    		$i = 0;
    		if(!$cashier['member_no']){
    			$i++;
    			continue;
    		}
    		$a = ACL::where(['cashier_id'=>$v['cashier_id']])->setField('card_no',$cashier['member_no']);
    		$a = ACL::where(['cashier_id'=>$v['cashier_id']])->setField('text',json_encode($text));
    	}
    	echo $i;
    }
}
