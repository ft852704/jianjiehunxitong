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
use app\index\model\CashierPayWay as CPW;
use app\index\model\CardTypeCompany;

use \think\Session;

use think\Db;
class ChuangDu extends Base
{
	public $member_rule = [
	            //会员卡字段
	            'card_no|会员卡编号'   => 'require|unique:member',
				'card_type|会员卡类型' => 'require',
				'name|会员名字' => 'require|chs',
				'mobile_phone|会员手机号' => 'number|length:11',
	        ];
	public $cashier_rule = [
	            'number|单号' => 'require'
	        ];
	public $pageSize = 10;
	public $statusList = [
			1 => '已开卡',
			2 => '已换卡',
			3 => '已转卡',
			4 => '已到期',
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
    		$company = Company::where('status=1')->select();
    		$companylist = [];
    		foreach ($company as $k => $v) {
    			$companylist[$v['id']] = $v['full_name'];
    		}
    		$this->assign('companylist',$companylist);

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
	    	if(isset($data['company'])){
		    	$companystr = $this->getChildCompany($data['company']);
		    	$where['company_id'] = array('in',$companystr);
	    	}
    	}
    	//注意，分页的where方法中有使用到别名时，必须在前面查总记录数的时候把别名一起带上，否则会报错
    	$list['pageCount'] = ceil(MC::where($where)->alias('m')->count('*')/$this->pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $this->pageSize;
    	$cardList = MC::field('m.*,t.name as tname')->alias('m')->join('member_card_type t','m.card_type=t.id','left')->where($where)->limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->select();
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

    	$list['pageCount'] = ceil(ConsumptionLog::count('*')/$this->pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $this->pageSize; 
    	$where = [
    		'member_id' => $data['mid'],
    	];

    	$logList = ConsumptionLog::limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->where($where)->select();

    	$list['sta']=empty($logList)?0:1;

    	foreach ($logList as $k => $v) {
    		$list['list'][$k] = [
    			'company_name' => $v['company_name'],
    			'activer_name' => $v['activer_name'],
    			'date' => date('y-m-d h:i:s',$v['time']),
    			'wallet_type' => $v['wallet_type'],
    			'active_type' => $v['active_type'],
    			'order_no' => $v['order_no'],
    			'last_balance' => $v['last_balance'],
    			'cash' => $v['cash'],
    			'this_balance' => $v['this_balance'],
    		];
    	}
    	echo json_encode($list);
    }
    //会员卡消费历史
    public function consumptionLog(){
    	$data = input();
    	$currentPage = isset($data['page'])?$data['page']:1;

    	$list['pageCount'] = ceil(ConsumptionLog::count('*')/$this->pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $this->pageSize;
    	$where = [
    		'member_id' => $data['mid'],
    		'active_type' => 1
    	];
    	$logList = ConsumptionLog::limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->where($where)->select();
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

    //会员卡操作历史
    public function operationLog(){
    	$data = input();
    	$currentPage = isset($data['page'])?$data['page']:1;

    	$list['pageCount'] = ceil(CardLog::count('*')/$this->pageSize);
    	$list['CurrentPage'] = 1;
    	$list['pageSize'] = $this->pageSize;
    	$operationLog = CardLog::limit(($currentPage-1)*$this->pageSize.','.$currentPage*$this->pageSize)->where('member_id='.$data['mid'])->select();
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
    public function memberInfoByCardno($status = 0){
    	$data = input();
    	$where = [
    		'mc.card_no' => $data['card_no'],
    	];
    	if($status){
    		$where['mc.status'] = ['in'=>$status];
    	}
    	$member = MC::alias('mc')->field('*,name as member_name,mc.id as mcid')->join('wallet w','mc.id = w.member_id','left')->where($where)->find();
    	if(empty($member)){
    		$member['code'] = 0;//数据不存在
    		echo json_encode($member);
    		exit;
    	}
    	$wallet = Wallet::where(['member_id'=>$member['mcid']])->find();
    	$member['arrears_c'] = $wallet['arrears_c'];

    	$member['birthday'] = date('Y-m-d',$member['birthday']);
    	$member['start_time'] = date('Y-m-d',$member['start_time']);
    	$member['end_time'] = date('Y-m-d',$member['end_time']);
    	$member['last_recharge_time'] = date('Y-m-d',$member['last_recharge_time']);
    	$member['code'] = 1;//数据存在
    	//$member = MC::get($data['mid']);
    	echo json_encode($member);
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
    	$wallet = Wallet::where(['member_id'=>$member['mcid']])->find();
    	$member['arrears_c'] = $wallet['arrears_c'];

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
    		echo '';
    	}else{
    		echo md5($data['card_no'].$this->ic_key);
    	}
    	//$member = MC::get($data['mid']);
    }

    //会员卡开卡
    public function openCard(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$walletdata = [];

	        $member = new MC;
	        if($data['wallet']==7){
	        	$use = Db::table('used_data')->where('id=78')->find();
		        if($use['status']){
		        	$card = json_encode($this->getCTbyOpen($data['real'],$data['arrears']));
			        if($card['status']){
			        	$data['card_type'] = $card['id'];
			        }else{
			        	return $this->error('没有对应的会员卡类型，开卡失败');
			    	}
		        }
	        }
	        
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
    			'birthday' => $data['birthday'],
    			'company_id' => Session::get('company_id'),
    			'status' => 1,
    			'name' => $data['name'],
    			'is_msn' => $data['is_msn'],
    			'last_recharge' => $data['real'],
    			'last_recharge_time' => time(),
    			'last_recharge_addr' => Session::get('company_id'),
    			'from' => 1,
    			'recharge' => $data['wallet']==7||$data['wallet']==9?$data['real']:0,
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
		    			$walletdata['arrears_c'] = 2;
		    		}elseif($data['wallet']==8){
		    			$walletdata['ewallet'] = $data['real'];
		    		}elseif($data['wallet']==9){
		    			$walletdata['old_cash'] = $data['real'];
		    			$walletdata['old_cash_arrears'] = $data['arrears'];
		    		}
		    		if($data['arrears']){
		    			$walletdata['arrears'] = 2;
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

		    		return $this->success('会员卡添加成功','/Index/member_card/opencard');
	        	}else{
	        		return $this->error($member->getError());
	        	}
	        }
    	}else{
    		//会员卡类型
	    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.*")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	if(Session::get('role')==2){
	    		unset($paylist[1]);//消除电子钱包
	    	}
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1 and level=1')->select();
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
    
    //会员卡充值
    public function rechargeCard($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$data['total'] = $data['arrears']+$data['real'];
    		$member = new MC;

	        if($data['wallet']==7){
	        	$use = Db::table('used_data')->where('id=78')->find();
		        if($use['status']){
		        	$card = json_encode($this->getCTbyRecharge($data['real'],$data['arrears']));
			        if($card['status']){
			        	$data['card_type'] = $card['id'];
			        }else{
			        	return $this->error('没有对应的会员卡类型，充值失败');
			    	}
		        }
	        }
    		
    		$member_old = $member->where(array('card_no'=>$data['card_no']))->find();
    		if(empty($member_old)){
    			return $this->error('会员卡不存在');
    		}
    		if($member_old->status!=1){
    			return $this->error('会员卡不是已开卡状态，不能充值');
    		}
    		if(!$data['card_type']){
    			return $this->error('该会员的会员卡类型不能充值');
    		}
    		//会员卡表
    		$memberdata = [
    			'card_type' => $data['card_type'],
    			'company_area' => $data['company_area'],
    			'company_id' => Session::get('company_id'),
    			'last_recharge' => $data['real'],
    			'last_recharge_time' => time(),
    			'last_recharge_addr' => Session::get('company_id'),
    			'recharge' => $data['wallet']==7||$data['wallet']==9?$member_old->recharge+$data['real']:$member_old->recharge+0,
    		];
    		// 会员卡数据验证
	        //$validate = new Validate($this->member_rule);
	       // $result   = $validate->check($memberdata);
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
	        		$wallet = new Wallet;
	        		$wallet_old = $wallet->where(array('member_id'=>$member_old['id']))->find();
		    		if($data['wallet']==7){
		    			$walletdata['cash_arrears'] = $wallet_old['cash_arrears']+$data['arrears'];
		    			$walletdata['cash'] = $wallet_old['cash']+$data['real'];
		    			$walletdata['arrears_c'] = 2;
		    		}elseif($data['wallet']==8){
		    			$walletdata['ewallet'] = $wallet_old['ewallet']+$data['real'];
		    		}elseif($data['wallet']==9){
		    			$walletdata['old_cash'] = $wallet_old['old_cash']+$data['real'];
		    			$walletdata['old_cash_arrears'] = $wallet_old['old_cash_arrears']+$data['arrears'];
		    		}
		    		if($data['arrears']){
		    			$walletdata['arrears'] = 2;
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

		    		return $this->success('会员卡充值成功','/Index/member_card/rechargeCard');
		    	}
		    }
    	}else{
    		//会员卡类型
	    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.*")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	unset($paylist[1]);//消除电子钱包
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1 and level=1')->select();
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
    		$member = new MC;
    		$member_old = $member->where(array('card_no'=>$data['card_no']))->find();
    		if(empty($member_old)){
    			return $this->error('会员卡不存在');
    		}
    		if($member_old->status!=1){
    			return $this->error('会员卡不是已开卡状态，不能转卡');
    		}
    		// 订单数据验证
	        $validate1 = new Validate($this->cashier_rule);
	        $result1 = $validate1->check(['number'=>$data['work_order']]);
	        if(!$result1){
	            return  $validate1->getError();
	        }

    		if(isset($data['is_change'])){
    			$use = Db::table('used_data')->where('id=78')->find();
		        if($use['status']){
		        	$card = json_encode($this->getCTbyRecharge($data['real'],$data['arrears']));
			        if($card['status']){
			        	$data['newcardid'] = $card['id'];
			        }else{
			        	return $this->error('没有对应的会员卡类型，充值失败');
			    	}
		        }

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
	    			'last_recharge' => $member_old['last_recharge'],
	    			'last_recharge_time' => $member_old['last_recharge_time'],
	    			'last_recharge_addr' => $member_old['last_recharge_addr'],
	    			'from' => 1,
	    			'recharge' => $member_old['recharge']+$data['real'],
	    			'last_card_id' => $member_old['id'],
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
		        $wallet = new Wallet;
	        		$wallet_old = $wallet->where(array('member_id'=>$member_old['id']))->find();
		    		if($data['wallet']==7){
		    			$walletdata['cash_arrears'] = $wallet_old['cash_arrears']+$data['arrears'];
		    			$walletdata['cash'] = $wallet_old['cash']+$data['real'];
		    			$walletdata['arrears_c'] = 2;
		    		}elseif($data['wallet']==8){
		    			$walletdata['ewallet'] = $wallet_old['ewallet']+$data['real'];
		    		}elseif($data['wallet']==9){
		    			$walletdata['old_cash'] = $wallet_old['old_cash']+$data['real'];
		    			$walletdata['old_cash_arrears'] = $wallet_old['old_cash_arrears']+$data['arrears'];
		    		}
		    		if($data['arrears']){
		    			$walletdata['arrears'] = 2;
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
		    			'order_type' => 2,
		    			'should_money' => $data['total'],
		    			'status' => 1
		    		];
		    		$cashier->save($cashierdata);

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
	        		
    		}else{
		        if($data['wallet']==7){
		        	$use = Db::table('used_data')->where('id=78')->find();
			        if($use['status']){
			        	$card = json_encode($this->getCTbyRecharge($data['real'],$data['arrears']));
				        if($card['status']){
				        	$data['card_type'] = $card['id'];
				        }else{
				        	return $this->error('没有对应的会员卡类型，充值失败');
				    	}
			        }
		        }
    			
    			//会员卡表
	    		$member_old->card_type = $data['card_type'];
	    		$member_old->recharge = $member_old['recharge']+$data['real'];
	    		if(!$member_old->save()){
		        	return $this->error($member_old->getError());
		        }
		        //钱包
		        $wallet = new Wallet;
	        		$wallet_old = $wallet->where(array('member_id'=>$member_old['id']))->find();
		    		if($data['wallet']==7){
		    			$walletdata['cash_arrears'] = $wallet_old['cash_arrears']+$data['arrears'];
		    			$walletdata['cash'] = $wallet_old['cash']+$data['total'];
		    		}elseif($data['wallet']==8){
		    			$walletdata['ewallet'] = $wallet_old['ewallet']+$data['total'];
		    		}elseif($data['wallet']==9){
		    			$walletdata['old_cash'] = $wallet_old['old_cash']+$data['total'];
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
		    			'order_type' => 2,
		    			'should_money' => $data['total'],
		    			'status' => 1
		    		];
		    		$cashier->save($cashierdata);

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
    		}
    			//会员卡日志表
		    		$cardlogdata = [
		    			'company' => Session::get('company_id'),
		    			'member_id' => $member_old['id'],
		    			'active_type' => 3,
		    		];
		    		$this->setCardLog($cardlogdata);

		    		return $this->success('会员卡转卡成功','/Index/member_card/opencard');
    	}else{
    		//会员卡类型
	    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.*")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
	    	$this->assign('ctlist',$ctlist);
	    	//开户账号
	    	$paylist = $this->getUsedData(6);
	    	unset($paylist[1]);//消除电子钱包
	    	$this->assign('paylist',$paylist);
	    	//卡可消费地区
	    	$company = Company::where('status=1 and level=1')->select();
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
    			'from' => 1,
    			'recharge' => $member_old['recharge'],
    			'last_card_id' => $member_old['id'],
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
		    		
		    		return $this->success('会员卡换卡成功','/Index/member_card/opencard');
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
    		if($data['wallet']==7){
    			$use = Db::table('used_data')->where('id=78')->find();
		        if($use['status']){
		        	//上次充值金额
		        	$last_cashier = Cashier::where(['member_no'=>$data['card_no'],'order_type'=>2])->order('time DESC')->limit(1)->find();
		        	$card = json_encode($this->getCTbyRecharge($data['real'],$last_cashier['real_money']));
			        if($card['status']){
			        	/*原卡类型（原卡类型为欠款改卡类型前一次的卡类型）为A，欠款转折扣类型为B，
						步骤1：判定上次充值金额加本次还款金额总额是否达到原卡类型充值标准，若未达到，则自动将会员卡类型转为该金额对应开卡标准的会员卡类型X
						   步骤2：若达到原卡类型充值标准，则判定该总额对应哪种类型的开卡标准
						   若只达到高于原卡类型折扣的会员卡类型，则还原的卡类型即为原卡类型A
						   步骤3：若达到低于原卡类型折扣的会员金卡类型的开卡标准，则还原卡类型为该金额对应的开卡标准的会员卡类型C
						   */
			        	$data['card_type'] = $card['id'];
			        	$open_card = json_encode($this->getCTbyOpen($data['real'],$last_cashier['real_money']));

			        	$old_card_type = MCT::allias('mct')->join('card_type_children ctc','mct.id=ctc.parent_id','left')->where(['mct.id'=>$member_old['card_type'],'ctc.pay_id'=>7])->find();
			        	$new_card_type = MCT::allias('mct')->join('card_type_children ctc','mct.id=ctc.parent_id','left')->where(['mct.id'=>$card['id'],'ctc.pay_id'=>7])->find();
			        	if($old_card_type['discount']<$new_card_type['discount']){
			        		if(!empty($open_card)){
			        			$open_card_type = MCT::allias('mct')->join('card_type_children ctc','mct.id=ctc.parent_id','left')->where(['mct.id'=>$open_card['id'],'ctc.pay_id'=>7])->find();
			        			if($new_card_type['discount']>$open_card_type['discount']){
			        				$data['card_type'] = $open_card_type['id'];
			        			}
			        		}
			        	}
			        }else{
			        	return $this->error('没有对应的会员卡类型，还款失败');
			    	}
		        }
    		}

    		//会员卡表
    		$memberdata = [
    			'recharge' => $data['wallet']==7||$data['wallet']==9?$member_old->recharge+$data['real']:$member_old->recharge+0,
    		];
    		if(isset($data['card_type'])){
    			$memberdata['card_type'] = $data['card_type'];
    		}
    		// 会员卡数据验证
	        //$validate = new Validate($this->member_rule);
	       // $result   = $validate->check($memberdata);
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
	        		$wallet = new Wallet;
	        		$wallet_old = $wallet->where(array('member_id'=>$member_old['id']))->find();
		    		if($data['wallet']==7){
		    			$walletdata['cash_arrears'] = $wallet_old['cash_arrears']-$data['real'];
		    			$walletdata['cash'] = $walletdata['cash'] + $data['real'];
		    			$walletdata['cash_arrears'] = 0;
		    			$walletdata['arrears_c'] = 0;
		    		}elseif($data['wallet']==9){
		    			if(($wallet_old['old_cash_arrears']-$data['real'])>=0){
		    				$walletdata['old_cash_arrears'] = $wallet_old['old_cash_arrears']-$data['real'];
		    				$walletdata['old_cash'] = $walletdata['old_cash'] + $data['real'];
		    			}else{
		    				//$walletdata['old_cash'] += $data['real']-$wallet_old['old_cash_arrears'];
		    				$walletdata['old_cash_arrears'] = 0;
		    			}
		    		}
	        		$wallet->save($walletdata,array('member_id'=>$member_old['id']));
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
		    			'order_type' => 4,
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

		    		return $this->success('会员卡还款成功成功','/Index/member_card/opencard');
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
    	//会员卡类型
    	$ctlist = MCT::where('status=1 and is_open=1')->select();
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

    	return $this->fetch(); 
    }
    //修改备注
    public function editRemark(){
    	$data = input();
    	if(!$data['card_no']){
    		echo json_encode(['status'=>0]);
    	}else{
    		$member = new MC;
    		if($member->where(['card_no'=>$data['card_no']])->setField('remark',$data['remark'])){
    			//会员卡日志表
		    		$cardlogdata = [
		    			'company' => Session::get('company_id'),
		    			'member_id' => $member['id'],
		    			'active_type' => 8,
		    			'remark' => isset($data['remark'])?$data['remark']:'',
		    		];
		    		$this->setCardLog($cardlogdata);
    			echo json_encode(['status'=>1]);
    		}else{
    			echo json_encode(['status'=>0]);
    		}
    	}
    }
    //获取会员卡开卡标准所对应的卡类型
    public function getCTbyOpen($real = 0,$arrears = 0){
    	$data = input();
    	$data['real'] = $real?$real:$data['real'];
    	$data['arrears'] = $arrears?$arrears:$data['arrears'];

    	$total = $data['real'] + $data['arrears'];
    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.id,mct.open_standard")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
    	if(empty($ctlist)){
    		echo json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    		exit;
    	}
    	$list = [];
    	foreach ($ctlist as $k => $v) {
    		if($total - $v['open_standard']>=0){
    			$list[$v['id']] = $total - $v['open_standard'];
    		}
    	}
    	$min = array_search(min($list),$list);
    	if($min){
    		echo json_encode(['status'=>1,'id'=>$min]);
    		exit;
    	}else{
    		echo json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    		exit;
    	}
    }
    //获取会员卡充值标准所对应的卡类型
    public function getCTbyRecharge($real = 0,$arrears = 0){
    	$data = input();
    	$data['real'] = $real?$real:$data['real'];
    	$data['arrears'] = $arrears?$arrears:$data['arrears'];

    	$total = $data['real'] + $data['arrears'];
    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.id,mct.recharge_standard")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id")])->select();
    	if(empty($ctlist)){
    		echo json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    		exit;
    	}
    	$list = [];
    	foreach ($ctlist as $k => $v) {
    		if($total - $v['recharge_standard']>=0){
    			$list[$v['id']] = $total - $v['recharge_standard'];
    		}
    	}
    	$min = array_search(min($list),$list);
    	if($min){
    		echo json_encode(['status'=>1,'id'=>$min]);
    		exit;
    	}else{
    		echo json_encode(['status'=>0,'msg'=>'没有对应卡类型']);
    		exit;
    	}
    }
}
