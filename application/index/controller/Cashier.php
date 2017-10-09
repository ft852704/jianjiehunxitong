<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use think\Db;
use app\index\model\MemberCardType as MCT;
use app\index\model\MemberCard as MC;
use app\index\model\Company;
use app\index\model\Service;
use app\index\model\Services;
use app\index\model\Family;
use app\index\model\CardTypeChildren as CTC;
use app\index\model\Wallet;
use app\index\model\Cashier as C;
use app\index\model\Cashiers as CS;
use app\index\model\CashierPayWay as CPW;
use app\index\model\CardTypeCompany;
use app\index\model\ConsumptionLog;
use app\index\model\Coupons as Co;
use app\index\model\Couponss as Cos;
use app\index\model\CouponsCompany as CC;
use app\index\model\AppConsumptionLog as ACL;

use app\index\model\Expenditure;
use \think\Session;

class Cashier extends Base
{
	public $rule = [
	            //常用资料添加字段
	            'number|编号'   => 'require',
				'name|名称' => 'require',
				'status|状态'     => 'require',
	        ];
	public $pageSize = 10;
	public $statusList = [
			1 => '已开卡',
			2 => '已换卡',
			3 => '已转卡',
			4 => '已到期',
	];
	//收银首页
    public function index()
    {
    	if(Request::instance()->isPost()){
    		$data = input();
    		if(isset($data['is_sanke'])){
    			//散客买单
    			$total = 0;
    			$deductible = 0;
    			foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	$total+=$data['dis-price'][$k];
		        	$deductible+=$data['deductible'][$k];
		        }
		        //如有优惠券，判断优惠券能否可用，使用金额是否超额
		        //将所有优惠券归集
		        $codelist = [];
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	if($data['deductible_type'][$k]==17){
		        		@$codelist[$data['coupons_code'][$k]] += $data['deductible'][$k];
		        	}
		        }
		        //验证优惠券
		        foreach ($codelist as $k => $v) {
		        	$company_id = Session::get('company_id');
		    		$where = [
		    			'cc.company_id' => $company_id,
		    			'cs.code' => $k,
		    			'cs.status' => 0,
		    		];
		        	$code = Cos::alias('cs')->join('coupons c','cs.parent_id=c.id','left')->join('coupons_company cc','c.id=cc.coupons_id','left')->field('c.name,c.price,cs.code')->where($where)->find();
		        	if(empty($code)){
		        		$this->error('抵扣码'.$k.'不存在、已使用或者不能在本店消费');
		        	}
		        	if($code['price']<$v){
		        		$this->error('抵扣码:'.$k.',使用金额为:'.$v.'，超出本身金额:'.$code['price']);
		        	}
		        }

    			//订单数据
		        $cashierdata = [
		        	'company_id' => Session::get('company_id'),
		        	'type' => 2,
		        	'number' => $data['work_order'],
		        	'member_id' => 0,
		        	'member_no' => '',
		        	'sex' => isset($data['man'])?1:2,
		        	'count' => $data['mansum'],
		        	'girl_count' => $data['womensum'],
		        	'remark' => '散客项目消费',
		        	'real_money' => round($total),
		        	'time' => time(),
		        	'active_id' => Session::get('login_id'),
		        	'order_type' => 1,
		        	'should_money' => $deductible+$total,
		        	'status' => 1,
		        ];
		        $cashier = new C;
		        if(!$cashier->save($cashierdata)){
		        	$this->error($cashier->getError());
		        }
		        //订单子表
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	//订单数据
		        	$fworker = Family::where(['number'=>$data['fworkrer'][$k]])->find();
		        	$sworker = Family::where(['number'=>$data['sworkrer'][$k]])->find();
		        	$services = Services::alias('ss')->field('ss.name as ssname,s.name as sname,s.count as count,ss.*')->join('service s','ss.parent_id=s.id','left')->where(['ss.id'=>$data['services'][$k]])->find();
		        	$cashiersdata[$k] = [
		        		'parent_id' => $cashier['id'],
		        		'services_name' => $services['ssname'],
		        		'service_name' => $services['sname'],
		        		'services_id' => $services['id'],
		        		'service_id' => $services['parent_id'],
		        		'star' => $data['star'][$k],
		        		'time_long' => $data['time_long'][$k],
		        		'deductible_pay' => $data['deductible_type'][$k],
		        		'deductible' => $data['deductible'][$k],
		        		'pay' => $v,
		        		'standard_price' => $data['price'][$k],
		        		'discount' => round($data['dis-price'][$k]),
		        		'count' => $data['counts'][$k],
		        		'fworker_id' => $fworker['id'],
		        		'ftype' => $data['stype1'][$k],
		        		'ftype_name' => '',
		        		'fworker' => $fworker['number'],
		        		'sworker_id' => isset($sworker['id'])?$sworker['id']:0,
		        		'stype' =>  $data['stype2'][$k],
		        		'stype_name' => '',
		        		'sworker' => isset($sworker['number'])?$sworker['number']:'',
		        		'service_price' => $data['price'][$k],
		        		'total' => $data['price'][$k]*$data['counts'][$k],
		        	];
		        	if($cashiersdata[$k]['deductible_pay']==17){
		        		$cashiersdata[$k]['coupons_code'] = $data['coupons_code'][$k];
		        		Cos::where(array('code'=>$cashiersdata[$k]['coupons_code']))->setField('status',1);
		        	}
		        }
		        $cashiers = new CS;
		        if(!$cashiers->saveAll($cashiersdata)){
		        	$this->error($cashiers->getError());
		        }
    		}else{
    			//会员买单

    			// 订单数据验证
		        $validate1 = new Validate();
		        $result1 = $validate1->check(['number'=>$data['work_order']]);
		        if(!$result1){
		            return  $validate1->getError();
		        }
		        //可消费地控制
		        $member = MC::where(['card_no'=>trim($data['card_no'])])->find();
		        $wallet = Wallet::where(['member_id'=>$member['id']])->find();

		        //有效期控制
		        $time = time();
		        if($member->start_time>$time){
		        	return $this->error('该卡不在有效期内，不能消费！');
		        }
		        if($member->end_time&&($member->end_time+24*3600-1)<$time){
		        	return $this->error('该卡不在有效期内，不能消费！');
		        }

		        $last_balance = [
		        	'cash' => $wallet['cash'],
		        	'ewallet' => $wallet['ewallet'],
		        	'old_cash' => $wallet['old_cash'],
		        ];
		        $companylist = $this->getChildCompany($member->company_area);
		        $companylist = explode(',', $companylist);
		        if(!in_array(Session::get('company_id'), $companylist)){
		        	return $this->error('该卡不能再本店消费！');
		        }
		        //对应账户是否有足额的钱
		        $cash_consumption = 0;
		        $ewallet_consumption = 0;
		        $old_cash_consumption = 0;
		        $total = 0;
		        $deductible = 0;
		        $cashiersdata = [];
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	$total+=$data['dis-price'][$k];
		        	$deductible+=$data['deductible'][$k];
		        	if($v==7){
		        		$cash_consumption+=$data['dis-price'][$k];
		        	}elseif($v==8){
		        		$ewallet_consumption+=$data['dis-price'][$k];
		        	}elseif($v==9){
		        		$old_cash_consumption+=$data['dis-price'][$k];
		        	}
		        }
		        if($cash_consumption>$wallet['cash']){
		        	return $this->error('储值账户余额不足，请提醒他充值！');
		        }
		        if($ewallet_consumption>$wallet['ewallet']){
		        	return $this->error('电子钱包余额不足，请提醒他充值！');
		        }
		        if($old_cash_consumption>$wallet['old_cash']){
		        	return $this->error('老疗程系统余额不足，请提醒他充值！');
		        }
		        //判断会员卡是否有欠款未还
		        if($wallet['arrears_c']==1){
		        	$use = Db::table('used_data')->where('id=80')->find();
			        if($use['status']==1){
			        	//上次充值金额
			        	$last_cashier = C::where(['member_no'=>$data['card_no'],'order_type'=>['in','2,3,4']])->order('time DESC')->limit(1)->find();
			        	//$card = json_encode($this->getCTbyRecharge($last_cashier['real_money'],0));
			        	$card = json_decode($this->getCTbyRecharge_transfercard($last_cashier['real_money'],0,$data['card_no']),true);
				        if($card['status']==1){
				        	if(count($card['data'])==1){
				        		db('member')->where('id',$member['id'])->setField('last_card_type',$card['data'][0]['id']);
					        	db('member')->where('id',$member['id'])->setField('card_type',$card['data'][0]['id']);
					        	@db('member')->where('id',$member['id'])->setField('from',1);
					        	db('wallet')->where('member_id',$member['id'])->setField('cash_arrears',0);
					        	db('wallet')->where('member_id',$member['id'])->setField('arrears_c',0);
	    						return $this->success('更改卡类型成功，请重新买单','/Index/Cashier');
				        	}elseif(count($card['data'])>1){
				        		$this->redirect('Cashier/changeCardType',['card_no' => trim($data['card_no']),'card_type_list'=>base64_encode(json_encode($card['data']))]);
				        	}else{
				        		return $this->error('没有对应的会员卡类型，更改卡类型失败');
				        	}
				        }else{
				        	return $this->error('没有对应的会员卡类型，更改卡类型失败');
				    	}
			        }

		        	$member = MC::where(['card_no'=>trim($data['card_no'])])->find();
		        	$wallet = Wallet::where(['member_id'=>$member['id']])->find();
		        }
		        //如有优惠券，判断优惠券能否可用，使用金额是否超额
		        //将所有优惠券归集
		        $codelist = [];  
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	if($data['deductible_type'][$k]==17){
		        		@$codelist[$data['coupons_code'][$k]] += $data['deductible'][$k];
		        	}
		        }
		        //验证优惠券
		        foreach ($codelist as $k => $v) {
		        	$company_id = Session::get('company_id');
		    		$where = [
		    			'cc.company_id' => $company_id,
		    			'cs.code' => $k,
		    		];
		        	$code = Cos::alias('cs')->join('coupons c','cs.parent_id=c.id','left')->join('coupons_company cc','c.id=cc.coupons_id','left')->field('c.name,c.price,cs.code')->where($where)->find();
		        	if(empty($code)){
		        		$this->error('抵扣码'.$k.'不存在或者不能在本店消费');
		        	}
		        	if($code['price']<$v){
		        		$this->error('抵扣码:'.$k.',使用金额为:'.$v.'，超出本身金额:'.$code['price']);
		        	}
		        }

		        //钱包扣款
		        $newwallet = $wallet;
		        $newwallet->cash = $newwallet->cash-$cash_consumption;
		        $newwallet->ewallet = $newwallet->ewallet-$ewallet_consumption;
		        $newwallet->old_cash = $newwallet->old_cash-$old_cash_consumption;
		        $newwallet->arrears_c = $newwallet->arrears_c==2?1:$newwallet->arrears_c;
		        $newwallet->save();
		        /*if(!$newwallet->save()){
		        	$this->error($newwallet->getError());
		        }*/
		        //订单数据
		        $cashierdata = [
		        	'company_id' => Session::get('company_id'),
		        	'type' => 1,
		        	'number' => $data['work_order'],
		        	'member_id' => $member['id'],
		        	'member_no' => $member['card_no'],
		        	'sex' => isset($data['man'])?1:2,
		        	'count' => $data['mansum'],
		        	'girl_count' => $data['womensum'],
		        	'remark' => '项目消费',
		        	'verification' => $data['phone_code'],
		        	'real_money' => round($total),
		        	'time' => time(),
		        	'active_id' => Session::get('login_id'),
		        	'order_type' => 1,
		        	'should_money' => round($deductible)+round($total),
		        	'status' => 1,
		        ];
		        $cashier = new C;
		        if(!$cashier->save($cashierdata)){
		        	$this->error($cashier->getError());
		        }
		        //
		        $cashchangedatatemp = [
		        	7 => 0,
		        	8 => 0,
		        	9 => 0,
		        ];
		        //订单子表
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	//订单数据
		        	$fworker = Family::where(['number'=>$data['fworkrer'][$k]])->find();
		        	$sworker = Family::where(['number'=>$data['sworkrer'][$k]])->find();
		        	$services = Services::alias('ss')->field('ss.name as ssname,s.name as sname,s.count as count,ss.*')->join('service s','ss.parent_id=s.id','left')->where(['ss.id'=>$data['services'][$k]])->find();
		        	$cashiersdata[$k] = [
		        		'parent_id' => $cashier['id'],
		        		'services_name' => $services['ssname'],
		        		'service_name' => $services['sname'],
		        		'services_id' => $services['id'],
		        		'service_id' => $services['parent_id'],
		        		'star' => $data['star'][$k],
		        		'time_long' => $data['time_long'][$k],
		        		'deductible_pay' => $data['deductible_type'][$k],
		        		'deductible' => $data['deductible'][$k],
		        		'pay' => $v,
		        		'standard_price' => $data['price'][$k],
		        		'discount' => $data['dis-price'][$k],
		        		'count' => $data['counts'][$k],
		        		'fworker_id' => $fworker['id'],
		        		'ftype' => $data['stype1'][$k],
		        		'ftype_name' => '',
		        		'fworker' => $fworker['number'],
		        		'sworker_id' => isset($sworker['id'])?$sworker['id']:0,
		        		'stype' =>  $data['stype2'][$k],
		        		'stype_name' => '',
		        		'sworker' => isset($sworker['number'])?$sworker['number']:'',
		        		'service_price' => $data['price'][$k],
		        		'total' => $data['price'][$k]*$data['counts'][$k],
		        		'discount_value' => $data['discount'][$k],
		        	];
		        	if($cashiersdata[$k]['deductible_pay']==17){
		        		$cashiersdata[$k]['coupons_code'] = $data['coupons_code'][$k];
		        		Cos::where(array('code'=>$cashiersdata[$k]['coupons_code']))->setField('status',1);
		        	}
		        	if($v==7){
		        		$cashchangedatatemp[7]+=$data['dis-price'][$k];
		        	}elseif($v==8){
		        		$cashchangedatatemp[8]+=$data['dis-price'][$k];
		        	}elseif($v==9){
		        		$cashchangedatatemp[9]=$data['dis-price'][$k];
		        	}
		        }
		        $cashiers = new CS;
		        if(!$cashiers->saveAll($cashiersdata)){
		        	$this->error($cashiers->getError());
		        }
		        //节点
		        //钱包金额变动表数据
		        foreach ($cashchangedatatemp as $k => $v) {
		        	$cashchangedata = [];
		        	if($k==7&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 7,
			    			'active_type' => 1,
			    			'order_no' => $data['work_order'],
			    			'last_balance' => $last_balance['cash'],
			    			'this_balance' => $newwallet['cash'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}elseif($k==8&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 8,
			    			'active_type' => 1,
			    			'order_no' => $data['work_order'],
			    			'ewallet' => $v,
			    			'last_balance' => $last_balance['ewallet'],
			    			'this_balance' => $newwallet['ewallet'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}elseif($k==9&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 9,
			    			'active_type' => 1,
			    			'order_no' => $data['work_order'],
			    			'ewallet' => $v,
			    			'last_balance' => $last_balance['old_cash'],
			    			'this_balance' => $newwallet['old_cash'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}
		        	if($cashchangedata){
		        		$this->setConsumptionLog($cashchangedata);
		        	}
		        }
    		}
    		if($cashier->id){
    			//消费日志
    			if($data['card_no']&&isset($member)){
    				$this->sendConsumptionMsg($member['mobile_phone'],$member['name'],$member['card_no'],$total,$wallet);
    				$member_card = MCT::alias('mct')->field('ctc.*')->join('card_type_children ctc','mct.id=ctc.parent_id','left')->where(['mct.id'=>$member['card_type']])->find();
    			}else{
    				$member_card['discount'] = 1;
    				$member['card_no'] = '';
    			}
    			//app需要的消费日志
    			$company = Company::get(Session::get('company_id'));
    			
    			$aclogdata = [
    				'cashier_id' => $cashier->id,
    				'time' => time(),//下单时间
    				'discount_money' =>  round($total), //消费金额
    				'company_id' => Session::get('company_id'),//消费门店
    				'company_name' => $company['full_name'],//消费门店名字
    				'discount' => $member_card['discount'],//折扣
    				'coupon' => '',//优惠券
    				'order_no' => $data['work_order'],//单号
    				'payway' => '会员卡', //支付方式
    				'payway_id' => $data['paywaylist'][0],//支付方式
    				'pay_time' => time(),//支付时间
    				'pay_status' => 1,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
    				'services' => $cashiersdata,
    				'app_order_id' => '',
	    			'app_order_name' => $cashiersdata[0]['service_name'],//订单名称
    				'card_no' => $member['card_no'],//会员卡号
    				'order_type' => 1,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    			'cashier_type' => 1,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
    			];
    			$this->setAppConsumptionLog($aclogdata);
    			if(isset($data['is_sanke'])){
    				return $this->success('收银成功','/Index/Cashier');
    			}else{
    				//打印小票
    				$this->redirect('Cashier/print_cashier', ['cashier_id' => $cashier->id]);
    			}
    			
    		}else{                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  
    			return $this->success('收银失败','/Index/Cashier');
    		}
    	}else{
    		$data = input();
    		if(empty($data)){
    			$data['card_no'] = '';
    		}
    		$this->assign('data',$data);
	    	//会员卡类型
	    	$ctlist = MCT::select();
	    	$this->assign('ctlist',$ctlist);
	    	//卡可消费地区
	    	$company = Company::select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData([6,10]);
	    	//抵扣方式
	    	$payway = [12,14,17];
	    	$paywaylist2 = [];//抵扣方式
	    	$paywaylist1 = [];//支付方式
	    	foreach ($paywaylist as $k => $v) {
	    		if(in_array($v['id'], $payway)){
	    			if($v['id']==14){
	    				continue;
	    			}
	    			$paywaylist2[] =  $v;
	    		}else{
	    			$paywaylist1[] =  $v;
	    		}
	    	}
	    	$paywaylist1 = array_merge([['id'=>0,'name'=>'无']],$paywaylist1);
	    	$paywaylist2 = array_merge([['id'=>0,'name'=>'无']],$paywaylist2);
	    	$this->assign('paywaylist1',$paywaylist1);
	    	$this->assign('paywaylist2',$paywaylist2);
	    	//门店项目
	    	$service = Service::where(array('company_id'=>Session::get('company_id'),'status'=>1))->select();
	    	$service = array_merge([['id'=>0,'name'=>'无']],$service);
	    	$this->assign('service',$service);
	    	//服务方式
	    	$servicetype = $this->getUsedData(64);
	    	$this->assign('stype',$servicetype);
	    	return $this->fetch(); 
	    }
    }
    /*
    public function index()
    {
    	if(Request::instance()->isPost()){
    		$data = input();
    		if(isset($data['is_sanke'])){
    			//散客买单
    			$total = 0;
    			$deductible = 0;
    			foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	$total+=$data['dis-price'][$k];
		        	$deductible+=$data['deductible'][$k];
		        }
		        //如有优惠券，判断优惠券能否可用，使用金额是否超额
		        //将所有优惠券归集
		        $codelist = [];
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	if($data['deductible_type'][$k]==17){
		        		@$codelist[$data['coupons_code'][$k]] += $data['deductible'][$k];
		        	}
		        }
		        //验证优惠券
		        foreach ($codelist as $k => $v) {
		        	$company_id = Session::get('company_id');
		    		$where = [
		    			'cc.company_id' => $company_id,
		    			'cs.code' => $k,
		    			'cs.status' => 0,
		    		];
		        	$code = Cos::alias('cs')->join('coupons c','cs.parent_id=c.id','left')->join('coupons_company cc','c.id=cc.coupons_id','left')->field('c.name,c.price,cs.code')->where($where)->find();
		        	if(empty($code)){
		        		$this->error('抵扣码'.$k.'不存在、已使用或者不能在本店消费');
		        	}
		        	if($code['price']<$v){
		        		$this->error('抵扣码:'.$k.',使用金额为:'.$v.'，超出本身金额:'.$code['price']);
		        	}
		        }

    			//订单数据
		        $cashierdata = [
		        	'company_id' => Session::get('company_id'),
		        	'type' => 2,
		        	'number' => $data['work_order'],
		        	'member_id' => 0,
		        	'member_no' => '',
		        	'sex' => isset($data['man'])?1:2,
		        	'count' => $data['mansum'],
		        	'girl_count' => $data['womensum'],
		        	'remark' => '散客项目消费',
		        	'real_money' => round($total),
		        	'time' => time(),
		        	'active_id' => Session::get('login_id'),
		        	'order_type' => 1,
		        	'should_money' => $deductible+$total,
		        	'status' => 1,
		        ];
		        $cashier = new C;
		        if(!$cashier->save($cashierdata)){
		        	$this->error($cashier->getError());
		        }
		        //订单子表
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	//订单数据
		        	$fworker = Family::where(['number'=>$data['fworkrer'][$k]])->find();
		        	$sworker = Family::where(['number'=>$data['sworkrer'][$k]])->find();
		        	$services = Services::alias('ss')->field('ss.name as ssname,s.name as sname,s.count as count,ss.*')->join('service s','ss.parent_id=s.id','left')->where(['ss.id'=>$data['services'][$k]])->find();
		        	$cashiersdata[$k] = [
		        		'parent_id' => $cashier['id'],
		        		'services_name' => $services['ssname'],
		        		'service_name' => $services['sname'],
		        		'services_id' => $services['id'],
		        		'service_id' => $services['parent_id'],
		        		'star' => $data['star'][$k],
		        		'time_long' => $data['time_long'][$k],
		        		'deductible_pay' => $data['deductible_type'][$k],
		        		'deductible' => $data['deductible'][$k],
		        		'pay' => $v,
		        		'standard_price' => $data['price'][$k],
		        		'discount' => round($data['dis-price'][$k]),
		        		'count' => $data['counts'][$k],
		        		'fworker_id' => $fworker['id'],
		        		'ftype' => $data['stype1'][$k],
		        		'ftype_name' => '',
		        		'fworker' => $fworker['number'],
		        		'sworker_id' => isset($sworker['id'])?$sworker['id']:0,
		        		'stype' =>  $data['stype2'][$k],
		        		'stype_name' => '',
		        		'sworker' => isset($sworker['number'])?$sworker['number']:'',
		        		'service_price' => $data['price'][$k],
		        		'total' => $data['price'][$k]*$data['counts'][$k],
		        	];
		        	if($cashiersdata[$k]['deductible_pay']==17){
		        		$cashiersdata[$k]['coupons_code'] = $data['coupons_code'][$k];
		        		Cos::where(array('code'=>$cashiersdata[$k]['coupons_code']))->setField('status',1);
		        	}
		        }
		        $cashiers = new CS;
		        if(!$cashiers->saveAll($cashiersdata)){
		        	$this->error($cashiers->getError());
		        }
    		}else{
    			//会员买单
    			// 订单数据验证
		        $validate1 = new Validate();
		        $result1 = $validate1->check(['number'=>$data['work_order']]);
		        if(!$result1){
		            return  $validate1->getError();
		        }
		        //可消费地控制
		        $member = MC::where(['card_no'=>trim($data['card_no'])])->find();
		        $wallet = Wallet::where(['member_id'=>$member['id']])->find();
		        
		        $last_balance = [
		        	'cash' => $wallet['cash'],
		        	'ewallet' => $wallet['ewallet'],
		        	'old_cash' => $wallet['old_cash'],
		        ];
		        $companylist = $this->getChildCompany($member->company_area);
		        $companylist = explode(',', $companylist);
		        if(!in_array(Session::get('company_id'), $companylist)){
		        	return $this->error('该卡不能再本店消费！');
		        }
		        //对应账户是否有足额的钱
		        $cash_consumption = 0;
		        $ewallet_consumption = 0;
		        $old_cash_consumption = 0;
		        $total = 0;
		        $deductible = 0;
		        $cashiersdata = [];
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	$total+=$data['dis-price'][$k];
		        	$deductible+=$data['deductible'][$k];
		        	if($v==7){
		        		$cash_consumption+=$data['dis-price'][$k];
		        	}elseif($v==8){
		        		$ewallet_consumption+=$data['dis-price'][$k];
		        	}elseif($v==9){
		        		$old_cash_consumption+=$data['dis-price'][$k];
		        	}
		        }
		        if($cash_consumption>$wallet['cash']){
		        	return $this->error('储值账户余额不足，请提醒他充值！');
		        }
		        if($ewallet_consumption>$wallet['ewallet']){
		        	return $this->error('电子钱包余额不足，请提醒他充值！');
		        }
		        if($old_cash_consumption>$wallet['old_cash']){
		        	return $this->error('老疗程系统余额不足，请提醒他充值！');
		        }
		        //判断会员卡是否有欠款未还
		        if($wallet['arrears_c']==1){
		        	$use = Db::table('used_data')->where('id=80')->find();
			        if($use['status']){
			        	//上次充值金额
			        	//$last_cashier = Cashier::where(['member_no'=>$data['card_no'],'order_type'=>2])->order('time DESC')->limit(1)->find();
			        	//$card = json_encode($this->getCTbyRecharge($last_cashier['real_money'],0));
			        	$card = json_decode($this->getCTbyRecharge_transfercard(0,0,$data['card_no']),true);
				        if($card['status']){
				        	db('member')->where('id',$member['id'])->setField('last_card_type',$card['id']);
				        	db('member')->where('id',$member['id'])->setField('card_type',$card['id']);
				        	db('wallet')->where('member_id',$member['id'])->setField('cash_arrears',0);
				        	db('wallet')->where('member_id',$member['id'])->setField('arrears_c',0);
    						return $this->success('更改卡类型成功，请重新买单','/Index/Cashier');
				        }else{
				        	return $this->error('没有对应的会员卡类型，更改卡类型失败');
				    	}
			        }

		        	$member = MC::where(['card_no'=>trim($data['card_no'])])->find();
		        	$wallet = Wallet::where(['member_id'=>$member['id']])->find();
		        }
		        //如有优惠券，判断优惠券能否可用，使用金额是否超额
		        //将所有优惠券归集
		        $codelist = [];
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	if($data['deductible_type'][$k]==17){
		        		@$codelist[$data['coupons_code'][$k]] += $data['deductible'][$k];
		        	}
		        }
		        //验证优惠券
		        foreach ($codelist as $k => $v) {
		        	$company_id = Session::get('company_id');
		    		$where = [
		    			'cc.company_id' => $company_id,
		    			'cs.code' => $k,
		    		];
		        	$code = Cos::alias('cs')->join('coupons c','cs.parent_id=c.id','left')->join('coupons_company cc','c.id=cc.coupons_id','left')->field('c.name,c.price,cs.code')->where($where)->find();
		        	if(empty($code)){
		        		$this->error('抵扣码'.$k.'不存在或者不能在本店消费');
		        	}
		        	if($code['price']<$v){
		        		$this->error('抵扣码:'.$k.',使用金额为:'.$v.'，超出本身金额:'.$code['price']);
		        	}
		        }

		        //钱包扣款
		        $newwallet = $wallet;
		        $newwallet->cash = $newwallet->cash-$cash_consumption;
		        $newwallet->ewallet = $newwallet->ewallet-$ewallet_consumption;
		        $newwallet->old_cash = $newwallet->old_cash-$old_cash_consumption;
		        $newwallet->arrears_c = $newwallet->arrears_c==2?1:$newwallet->arrears_c;
		        $newwallet->save();
		        //if(!$newwallet->save()){
		        	//$this->error($newwallet->getError());
		        //}
		        //订单数据
		        $cashierdata = [
		        	'company_id' => Session::get('company_id'),
		        	'type' => 1,
		        	'number' => $data['work_order'],
		        	'member_id' => $member['id'],
		        	'member_no' => $member['card_no'],
		        	'sex' => isset($data['man'])?1:2,
		        	'count' => $data['mansum'],
		        	'girl_count' => $data['womensum'],
		        	'remark' => '项目消费',
		        	'verification' => $data['phone_code'],
		        	'real_money' => round($total),
		        	'time' => time(),
		        	'active_id' => Session::get('login_id'),
		        	'order_type' => 1,
		        	'should_money' => round($deductible)+round($total),
		        	'status' => 1,
		        ];
		        $cashier = new C;
		        if(!$cashier->save($cashierdata)){
		        	$this->error($cashier->getError());
		        }
		        //
		        $cashchangedatatemp = [
		        	7 => 0,
		        	8 => 0,
		        	9 => 0,
		        ];
		        //订单子表
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	//订单数据
		        	$fworker = Family::where(['number'=>$data['fworkrer'][$k]])->find();
		        	$sworker = Family::where(['number'=>$data['sworkrer'][$k]])->find();
		        	$services = Services::alias('ss')->field('ss.name as ssname,s.name as sname,s.count as count,ss.*')->join('service s','ss.parent_id=s.id','left')->where(['ss.id'=>$data['services'][$k]])->find();
		        	$cashiersdata[$k] = [
		        		'parent_id' => $cashier['id'],
		        		'services_name' => $services['ssname'],
		        		'service_name' => $services['sname'],
		        		'services_id' => $services['id'],
		        		'service_id' => $services['parent_id'],
		        		'star' => $data['star'][$k],
		        		'time_long' => $data['time_long'][$k],
		        		'deductible_pay' => $data['deductible_type'][$k],
		        		'deductible' => $data['deductible'][$k],
		        		'pay' => $v,
		        		'standard_price' => $data['price'][$k],
		        		'discount' => $data['dis-price'][$k],
		        		'count' => $data['counts'][$k],
		        		'fworker_id' => $fworker['id'],
		        		'ftype' => $data['stype1'][$k],
		        		'ftype_name' => '',
		        		'fworker' => $fworker['number'],
		        		'sworker_id' => isset($sworker['id'])?$sworker['id']:0,
		        		'stype' =>  $data['stype2'][$k],
		        		'stype_name' => '',
		        		'sworker' => isset($sworker['number'])?$sworker['number']:'',
		        		'service_price' => $data['price'][$k],
		        		'total' => $data['price'][$k]*$data['counts'][$k],
		        	];
		        	if($cashiersdata[$k]['deductible_pay']==17){
		        		$cashiersdata[$k]['coupons_code'] = $data['coupons_code'][$k];
		        		Cos::where(array('code'=>$cashiersdata[$k]['coupons_code']))->setField('status',1);
		        	}
		        	if($v==7){
		        		$cashchangedatatemp[7]+=$data['dis-price'][$k];
		        	}elseif($v==8){
		        		$cashchangedatatemp[8]+=$data['dis-price'][$k];
		        	}elseif($v==9){
		        		$cashchangedatatemp[9]=$data['dis-price'][$k];
		        	}
		        }
		        $cashiers = new CS;
		        if(!$cashiers->saveAll($cashiersdata)){
		        	$this->error($cashiers->getError());
		        }
		        //节点
		        //钱包金额变动表数据
		        foreach ($cashchangedatatemp as $k => $v) {
		        	$cashchangedata = [];
		        	if($k==7&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 7,
			    			'active_type' => 1,
			    			'order_no' => $data['work_order'],
			    			'last_balance' => $last_balance['cash'],
			    			'this_balance' => $newwallet['cash'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}elseif($k==8&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 8,
			    			'active_type' => 1,
			    			'order_no' => $data['work_order'],
			    			'ewallet' => $v,
			    			'last_balance' => $last_balance['ewallet'],
			    			'this_balance' => $newwallet['ewallet'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}elseif($k==9&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 9,
			    			'active_type' => 1,
			    			'order_no' => $data['work_order'],
			    			'ewallet' => $v,
			    			'last_balance' => $last_balance['old_cash'],
			    			'this_balance' => $newwallet['old_cash'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}
		        	if($cashchangedata){
		        		$this->setConsumptionLog($cashchangedata);
		        	}
		        }
    		}
    		if($cashier->id){
    			if($data['card_no']&&isset($member)){
    				$this->sendConsumptionMsg($member['mobile_phone'],$member['name'],$member['card_no'],$total,$wallet);
    			}
    			//$this->redirect('Cashier/print_cashier', ['cashier_id' => $cashier->id]);
    			return $this->success('收银成功','/Index/Cashier');
    		}else{
    			return $this->success('收银失败','/Index/Cashier');
    		}
    	}else{
    		$data = input();
    		if(empty($data)){
    			$data['card_no'] = '';
    		}
    		$this->assign('data',$data);
	    	//会员卡类型
	    	$ctlist = MCT::select();
	    	$this->assign('ctlist',$ctlist);
	    	//卡可消费地区
	    	$company = Company::select();
	    	$companylist = [];
	    	foreach ($company as $k => $v) {
	    		$companylist[$v['id']] = $v['full_name'];
	    	}
	    	$this->assign('companylist',$companylist);
	    	//支付方式
	    	$paywaylist = $this->getUsedData([6,10]);
	    	//抵扣方式
	    	$payway = [12,14,17];
	    	$paywaylist2 = [];//抵扣方式
	    	$paywaylist1 = [];//支付方式
	    	foreach ($paywaylist as $k => $v) {
	    		if(in_array($v['id'], $payway)){
	    			if($v['id']==14){
	    				continue;
	    			}
	    			$paywaylist2[] =  $v;
	    		}else{
	    			$paywaylist1[] =  $v;
	    		}
	    	}
	    	$paywaylist1 = array_merge([['id'=>0,'name'=>'无']],$paywaylist1);
	    	$paywaylist2 = array_merge([['id'=>0,'name'=>'无']],$paywaylist2);
	    	$this->assign('paywaylist1',$paywaylist1);
	    	$this->assign('paywaylist2',$paywaylist2);
	    	//门店项目
	    	$service = Service::where(array('company_id'=>Session::get('company_id'),'status'=>1))->select();
	    	$service = array_merge([['id'=>0,'name'=>'无']],$service);
	    	$this->assign('service',$service);
	    	//服务方式
	    	$servicetype = $this->getUsedData(64);
	    	$this->assign('stype',$servicetype);
	    	return $this->fetch(); 
	    }
    }
    */
    //新会员卡类型变更
    public function changeCardType(){
    	if(Request::instance()->isPost()){
    		$data = input();
    		$member = MC::where(['card_no'=>$data['card_no']])->find();
    		//db('member')->where('id',$member['id'])->setField('last_card_type',$card['data'][0]['id']);
			if(db('member')->where('id',$member['id'])->setField('card_type',$data['card_type'])){
				@db('member')->where('id',$member['id'])->setField('from',1);
				db('wallet')->where('member_id',$member['id'])->setField('cash_arrears',0);
				db('wallet')->where('member_id',$member['id'])->setField('arrears_c',0);
				return $this->success('会员卡类型变更成功，请重新收银','/Index/Cashier/',['card_no'=>$data['card_no']]);
			}else{
    			return $this->success('会员卡类型变更失败','/Index/Cashier');
    		}
    	}else{
    		$data = input();
	    	$member = MC::alias('mc')->join('wallet w','mc.id=w.member_id','left')->where(['mc.card_no'=>$data['card_no']])->find();
	    	$this->assign('member',$member);
	    	$this->assign('ctlist',json_decode(base64_decode($data['card_type_list']),true));
	    	return $this->fetch();
    	}
    }
    //当日日记小单
    public function todayChar(){
    	$data = input();
    	if(Session::get('role')==1){
    		//$id = isset($data['company'])&&$data['company']?$data['company']:5;
    		if(isset($data['company'])){
    			$clist = $this->getChildCompany($data['company']);
    			$where = ' and c.company_id in('.$clist.')  and c.app_order_id=0';
    			$where1 = ' and c.company_id in('.$clist.')';//支出
    			$id = $data['company'];
    		}else{
    			$id = 5;
	    		$where = ' and c.app_order_id=0';
	    		$where1 = '';
    		}

    		//$where = ' and c.company_id ='.$id.' and c.app_order_id=0';
    		//$where1 = ' and c.company_id ='.$id;//支出
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		/*if(isset($data['company'])){
    			$clist = $this->getChildCompany($id);
    			$where = ' and c.company_id in('.$clist.')';
    		}else{
	    		$where = ' and c.company_id in('.$id.')';
    		}*/
    		$where = ' and c.company_id ='.$id.' and c.app_order_id=0';
    		$where1 = ' and c.company_id ='.$id;//支出
    		$companylist = $this->getCompanyList($id,1);
    	}
    	$this->assign('companylist',$companylist);
    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d')));
    	$data['start_time'] += 6*3600;
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):strtotime(date('Y/m/d'));
    	$data['end_time'] += (30*3600);

    	$this->assign('data',['start_time'=>date('Y-m-d',$data['start_time']),'end_time'=>date('Y-m-d',$data['end_time']-30*3600),'company_id'=>$id]);
    	//收银系统订单数据统计
    	//营业额类
    	//充值现金
    	$where_cash_consumption = 'cps.pay_type=11 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$cash_consumption = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_cash_consumption)->find();
    	$cash_consumption['cpsmoney'] = $cash_consumption['cpsmoney']?$cash_consumption['cpsmoney']:0;
    	$this->assign('cash_consumption',$cash_consumption);
    	//消费现金
    	$where_cash_top_up = 'cs.pay=11 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$cash_top_up = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_cash_top_up)->find();
    	$cash_top_up['csdiscount'] = $cash_top_up['csdiscount']?$cash_top_up['csdiscount']:0;
    	$this->assign('cash_top_up',$cash_top_up);
    	//现金
    	$this->assign('cash',$cash_top_up['csdiscount']+$cash_consumption['cpsmoney']);
    	//欠款金额


    	//消费团购
    	$where_tuangou_consumption = 'cps.pay=13 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$tuangou_consumption = CS::alias('cps')->field('FLOOR(sum(cps.discount)) as cpsmoney')->join('cashier c','cps.parent_id=c.id','left')->where($where_tuangou_consumption)->find();
    	$tuangou_consumption['cpsmoney'] = $tuangou_consumption['cpsmoney']?$tuangou_consumption['cpsmoney']:0;
    	$this->assign('tuangou_consumption',$tuangou_consumption);
    	//消费银行卡
    	$where_ycard_consumption = 'cps.pay=15 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$ycard_consumption = CS::alias('cps')->field('FLOOR(sum(cps.discount)) as cpsmoney')->join('cashier c','cps.parent_id=c.id','left')->where($where_ycard_consumption)->find();
    	$ycard_consumption['cpsmoney'] = $ycard_consumption['cpsmoney']?$ycard_consumption['cpsmoney']:0;
    	$this->assign('ycard_consumption',$ycard_consumption);
    	//银行卡卡异动（充值）
    	$where_ycard_top_up = 'cps.pay_type=15 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$ycard_top_up = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_ycard_top_up)->find();
    	$ycard_top_up['cpsmoney'] = $ycard_top_up['cpsmoney']?$ycard_top_up['cpsmoney']:0;
    	$this->assign('ycard_top_up',$ycard_top_up);
    	//银行卡合计
    	$this->assign('ycard',$ycard_top_up['cpsmoney']+$ycard_consumption['cpsmoney']);

    	//消费抵用券
    	$where_vouchers_consumption = 'cps.deductible_pay=17 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$vouchers_consumption = CS::alias('cps')->field('FLOOR(sum(cps.deductible)) as cpsmoney')->join('cashier c','cps.parent_id=c.id','left')->where($where_vouchers_consumption)->find();
    	$vouchers_consumption['cpsmoney'] = $vouchers_consumption['cpsmoney']?$vouchers_consumption['cpsmoney']:0;
    	$this->assign('vouchers_consumption',$vouchers_consumption);

    	//门店支出
    	$where_spending = '';
    	$spending = Expenditure::alias('c')->field('FLOOR(sum(c.total)) as stotal')->where('c.time>='.$data['start_time'].' and c.time<='.$data['end_time'].$where1)->find();
    	$spending['stotal'] = $spending['stotal']?$spending['stotal']:0;
    	$this->assign('spending',$spending);
    	//总营业额
    	$total_money = $cash_top_up['csdiscount']+$cash_consumption['cpsmoney']+$tuangou_consumption['cpsmoney']+$ycard_consumption['cpsmoney']+$ycard_top_up['cpsmoney'];
    	$this->assign('total_money',$total_money);
    	//实际收入
    	$this->assign('real_income',$total_money-$spending['stotal']);
    	//现存现金
    	$cash_now = $cash_top_up['csdiscount']+$cash_consumption['cpsmoney']-$spending['stotal'];
    	$this->assign('cash_now',$cash_now);

    	//销卡类
    	//储值账户
    	$where_wallet_consumption = 'cs.pay=7 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$wallet_consumption = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_wallet_consumption)->find();
    	$wallet_consumption['csdiscount'] = $wallet_consumption['csdiscount']?$wallet_consumption['csdiscount']:0;
    	$this->assign('wallet_consumption',$wallet_consumption);
    	//电子钱包
    	$where_ewallet_consumption = 'cs.pay=8 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$ewallet_consumption = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_ewallet_consumption)->find();
    	$ewallet_consumption['csdiscount'] = $ewallet_consumption['csdiscount']?$ewallet_consumption['csdiscount']:0;
    	$this->assign('ewallet_consumption',$ewallet_consumption);
    	//老疗程系统
    	$where_old_wallet_consumption = 'cs.pay=9 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$old_wallet_consumption = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_old_wallet_consumption)->find();
    	$old_wallet_consumption['csdiscount'] = $old_wallet_consumption['csdiscount']?$old_wallet_consumption['csdiscount']:0;
    	$this->assign('old_wallet_consumption',$old_wallet_consumption);
    	//卡异动合计
    	$where_all_top_up = 'cps.pay_type not in(17,12,14) and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$all_consumption = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_all_top_up)->find();
    	$all_consumption['cpsmoney'] = $all_consumption['cpsmoney']?$all_consumption['cpsmoney']:0;
    	$this->assign('all_consumption',$all_consumption);
    	//经理签单
    	//消费经理签单
    	
    	$where_arrears_consumption = 'cs.deductible_pay=12 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$arrears_consumption = CS::alias('cs')->field('FLOOR(sum(cs.deductible)) as csdeductible')->join('cashier c','cs.parent_id=c.id','left')->where($where_arrears_consumption)->find();
    	$arrears_consumption['csdeductible'] = $arrears_consumption['csdeductible']?$arrears_consumption['csdeductible']:0;
    	//充值经理签单
    	$where_arrears_top_up = 'cps.pay_type=12 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$arrears_top_up = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_arrears_top_up)->find();
    	$arrears_top_up['cpsmoney'] = $arrears_top_up['cpsmoney']?$arrears_top_up['cpsmoney']:0;
    	$arrears = $arrears_consumption['csdeductible']+$arrears_top_up['cpsmoney'];
    	$this->assign('arrears',$arrears);
    	//销卡总额
    	$total_consumption = $old_wallet_consumption['csdiscount']+$wallet_consumption['csdiscount'];
    	$this->assign("total_consumption",$total_consumption);
    	//劳动业绩
    	$where_labor_performance = 'cs.pay!=8 and c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$wallet_labor_performance = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_labor_performance)->find();
    	$wallet_labor_performance = $wallet_labor_performance['csdiscount']?$wallet_labor_performance['csdiscount']:0;
    	$this->assign('wallet_labor_performance',$wallet_labor_performance);

    	//APP系统订单数据统计
    	if(Session::get('role')==1){
    		$id = isset($data['company'])&&$data['company']?$data['company']:5;
    		$where = ' and c.company_id ='.$id.' and c.app_order_id>0';
    	}else{
	    	$id = Session::get('company_id');
    		/*if(isset($data['company'])){
    			$clist = $this->getChildCompany($id);
    			$where = ' and c.company_id in('.$clist.')';
    		}else{
	    		$where = ' and c.company_id in('.$id.')';
    		}*/
    		$where = ' and c.company_id ='.$id.' and c.app_order_id>0';
    	}
    	//会员卡消费（未确认）
    	$where_app_wc = 'c.member_no>0 and c.status!=2 and c.is_confirm=1 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$app_wc = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_app_wc)->find();
    	$app_wc['csdiscount'] = $app_wc['csdiscount']?$app_wc['csdiscount']:0;
    	$this->assign('app_wc',$app_wc);

    	//会员卡消费（已确认）
    	$where_app_yc = 'c.member_no>0 and c.status!=2 and c.is_confirm=0 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$app_yc = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_app_yc)->find();
    	$app_yc['csdiscount'] = $app_yc['csdiscount']?$app_yc['csdiscount']:0;
    	$this->assign('app_yc',$app_yc);

    	//散客消费（未确认）
    	$where_app_sw = 'c.member_no=0 and c.status!=2 and c.is_confirm=0 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$app_sw = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_app_sw)->find();
    	$app_sw['csdiscount'] = $app_sw['csdiscount']?$app_sw['csdiscount']:0;
    	$this->assign('app_sw',$app_sw);

    	//散客消费（已确认）
    	$where_app_sc = 'c.member_no=0 and c.status!=2 and c.is_confirm=1 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$app_sc = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_app_sc)->find();
    	$app_sc['csdiscount'] = $app_sc['csdiscount']?$app_sc['csdiscount']:0;
    	$this->assign('app_sc',$app_sc);

    	return $this->fetch();
    }
    //日记小单-项目销卡详情(销卡合计)
    public function serviceConsumption(){
    	$data = input();
    	if(Session::get('role')==1){
    		$id = isset($data['company'])&&$data['company']?$data['company']:5;
    		$where = ' and c.company_id ='.$id;
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		/*if(isset($data['company'])){
    			$clist = $this->getChildCompany($id);
    			$where = ' and c.company_id in('.$clist.')';
    		}else{
	    		$where = ' and c.company_id in('.$id.')';
    		}*/
    		$where = ' and c.company_id ='.$id;
    		$companylist = $this->getCompanyList($id,1);
    	}

    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time'])+6*3600:strtotime(date('Y-m-d'))+6*3600;
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time'])+30*3600:strtotime(date('Y-m-d'))+30*3600;
    	if(($data['end_time']-$data['start_time'])>(32*24*3600)){
    		$data['start_time'] = strtotime(date('Y-m-d'))+6*3600;
    		$data['end_time'] = $data['start_time']+24*3600;
    	}
    	$this->assign('data',['company_id'=>$id,'start_time'=>date('Y-m-d',$data['start_time']-6*3600),'end_time'=>date('Y-m-d',$data['end_time']-30*3600)]);

    	$where = 'c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$service = CS::alias('cs')->field('cs.service_name,cs.services_id,FLOOR(sum(cs.count)) as scount,FLOOR(sum(cs.discount)) as sdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where)->group('cs.service_id')->select();
    	$this->assign('service',$service);

    	$this->assign('companylist',$companylist);
    	return $this->fetch();
    }
    //日记小单-项目销卡详情(销卡合计)详细
    public function servicesConsumption(){
    	$data = input();
    	if(Session::get('role')==1){
    		$id = isset($data['company'])&&$data['company']?$data['company']:5;
    		$where = ' and c.company_id ='.$id;
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		/*if(isset($data['company'])){
    			$clist = $this->getChildCompany($id);
    			$where = ' and c.company_id in('.$clist.')';
    		}else{
	    		$where = ' and c.company_id in('.$id.')';
    		}*/
    		$where = ' and c.company_id ='.$id;
    		$companylist = $this->getCompanyList($id,1);
    	}

    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time'])+6*3600:strtotime(date('Y-m-d'))+6*3600;
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time'])+30*3600:strtotime(date('Y-m-d'))+30*3600;
    	if(($data['end_time']-$data['start_time'])>(32*24*3600)){
    		$data['start_time'] = strtotime(date('Y-m-d'))+6*3600;
    		$data['end_time'] = $data['start_time']+24*3600;
    	}
    	$this->assign('data',['company_id'=>$id,'start_time'=>date('Y-m-d',$data['start_time']-6*3600),'end_time'=>date('Y-m-d',$data['end_time']-30*3600)]);

    	$where = 'c.status!=2 and c.time>'.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$services = CS::alias('cs')->field('cs.services_name,cs.services_id,FLOOR(sum(cs.count)) as scount,FLOOR(sum(cs.discount)) as sdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where)->group('cs.services_id')->select();
    	$this->assign('services',$services);

    	$this->assign('companylist',$companylist);
    	return $this->fetch();
    }
    //获取项目星级
    public function getStar($snum=0){
    	if($snum){
    		$wheres = [
    			'number' => $snum,
    			'company_id' => Session::get('company_id'),
    			'status' => 1,
    		];
    		$service = Service::where($wheres)->find();
    		if(empty($service)){
    			$data['sta'] = 0;
    			echo json_encode($data);exit;
    		}
    		$where = array('parent_id'=>$service['id'],'status'=>1);
    		$starlist = Services::where($where)->group('star')->select();
    		if(empty($starlist)){
    			$data['sta'] = 0;
    			echo json_encode($data);exit;
    		}
	    	$star = $this->getUsedData(52);
	    	$star = array_column($star, 'name', 'id');
	    	foreach ($starlist as $key => &$value) {
	    		$value['star_str'] = $star[$value['star']];
	    	}
	    	if(empty($starlist)){
	    		$data['sta'] = 0;
	    	}else{
	    		$starlist = array_merge([['star'=>0,'star_str'=>'无']],$starlist);
	    		$data['sta'] = 1;
	    		$data['list'] = $starlist;
	    		$data['sname'] = $service['name'];
	    		$data['sid'] = $service['id'];
	    	}
    		echo json_encode($data);exit;
    	}else{
    		echo json_encode(['sta'=>0]);exit;
    	}
    }
    //获取对应星级项目时长
    public function getTimeLong($service_id=0,$star=0){
    	$where = array('parent_id'=>$service_id,'star'=>$star,'status'=>1);
    	$time_long = Services::where($where)->group('time_long')->select();
    	if(empty($time_long)){
    		echo json_encode(['sta'=>0]);exit;
    	}else{
    		$time_long = array_merge([['time_long'=>'无']],$time_long);
    		echo json_encode(array_merge(['list'=>$time_long],['sta'=>1]));exit;
    	}
    }
    //获取对应星级项目价格
    public function getPrice($service_id=0,$star=0,$time_long=0){
    	$where = array('parent_id'=>$service_id,'star'=>$star,'time_long'=>$time_long,'status'=>1);
    	$price = Services::where($where)->find();
    	if(empty($price)){
    		echo json_encode(['sta'=>0]);exit;
    	}else{
    		$price1 = $price['old_price']?[$price['price'],$price['old_price']]:[$price['price']];
    		$price1 = $price['t1_price']?array_merge($price1,[$price['t1_price']]):$price1;
    		$price1 = $price['t2_price']?array_merge($price1,[$price['t2_price']]):$price1;

    		echo json_encode(array_merge(['list'=>$price1],['sta'=>1],['services_id'=>$price['id']]));exit;
    	}
    }
    //获取折扣率
    public function getRate($card_no=0,$payway=0,$services=0,$price=0){
    	//$card_type = MC::alias('mc')->join('member_card_type mct','mc.card_type = mct.id','left')->where(['mc.card_no'=>$card_no,'mc.status'=>1,'mct.status'=>1])->find();
    	$card_type = MC::alias('mc')->join('member_card_type mct','mc.card_type = mct.id','left')->where(['mc.card_no'=>$card_no,'mc.status'=>1])->find();
    	if(empty($card_type)){
    		echo json_encode(['sta'=>0,'msg'=>'该卡不存在或者被限制不能使用！']);exit;
    	}else{
    		//项目是否能打折
    		$services = Services::get($services);
    		if($services['price']==$price){//新价格
    			if(!$services['is_discount']){
    				echo json_encode(['sta'=>1,'rate'=>1]);exit;
    			}
    		}elseif($services['old_price']==$price){//老价格
    			if(!$services['old_is_discount']){
    				echo json_encode(['sta'=>1,'rate'=>1]);exit;
    			}
    		}elseif($services['t1_price']==$price){//特殊价格1
    			if(!$services['t1_is_discount']){
    				echo json_encode(['sta'=>1,'rate'=>1]);exit;
    			}
    		}elseif($services['t2_price']==$price){//特殊价格2
    			if(!$services['t2_is_discount']){
    				echo json_encode(['sta'=>1,'rate'=>1]);exit;
    			}
    		}
    		//会员卡是否能打折
    		if($card_type['is_discount']!=1){
    			echo json_encode(['sta'=>1,'rate'=>1]);exit;
    		}else{
	    		$rate = CTC::where(['parent_id'=>$card_type['card_type'],'pay_id'=>$payway])->find();
	    		echo json_encode(['sta'=>1,'rate'=>$rate['discount']]);exit;
    		}
    	}
    }

    //验证员工编号是否存在
    public function familyisset(){
    	$data = input();
    	if($data['number']){
	    	$where = [
	    		'number' => $data['number'],
	    		'status' => 1,
	    	];
    		$family = Family::where($where)->find();
    		if(!empty($family)){
    			if($family['company']==Session::get('company_id')){
    				$re = [
    					'status' => 1,
    					'name' => $family['name'],
    				];
    			}else{
    				$re = [
    					'status' => 2,
    					'name' => $family['name'],
    				];
    			}
    		}else{
    			$re = [
    					'status' => 0,
    				];
    		}
    	}
    	echo json_encode($re);exit;
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
    	$member['birthday'] = date('Y-m-d',$member['birthday']);
    	$member['start_time'] = date('Y-m-d',$member['start_time']);
    	$member['end_time'] = date('Y-m-d',$member['end_time']);
    	$member['last_recharge_time'] = date('Y-m-d',$member['last_recharge_time']);
    	$member['code'] = 1;//数据存在
    	//$member = MC::get($data['mid']);
    	echo json_encode($member);
    }
    //根据IC会员卡号获取会员信息
    public function memberEncodeInfoByCardno(){
    	$data = input();
    	$status = isset($data['status'])?$data['status']:1;
    	$data['code'] = strtolower($data['code']);
    	if(md5($data['card_no'].$this->ic_key)!=$data['code']){
    		echo json_encode(array('code'=>2));
    		exit;
    	}
    	$where = [
    		'mc.card_no' => $data['card_no'],
    	];
    	if($status){
    		$where['mc.status'] = $status;
    	}

    	$member = MC::alias('mc')->field('*,name as member_name,mc.id as mcid')->join('wallet w','mc.id = w.member_id','left')->where($where)->find();

    	if(empty($member)){
    		$member['code'] = 0;//数据不存在
    		echo json_encode($member);
    		exit;
    	}
    	$member['birthday'] = date('Y-m-d',$member['birthday']);
    	$member['start_time'] = date('Y-m-d',$member['start_time']);
    	$member['end_time'] = date('Y-m-d',$member['end_time']);
    	$member['last_recharge_time'] = date('Y-m-d',$member['last_recharge_time']);
    	$member['code'] = 1;//数据存在
    	//$member = MC::get($data['mid']);
    	echo json_encode($member);
    }
    //验证抵扣券
    public function checkcode($ajax=1){
    	$data = input();
    	if(isset($data['code'])&&$data['code']){
    		$company_id = Session::get('company_id');
    		$where = [
    			'cc.company_id' => $company_id,
    			'cs.code' => $data['code'],
    			'cs.status' => 0,
    		];
    		$code = Cos::alias('cs')->join('coupons c','cs.parent_id=c.id','left')->join('coupons_company cc','c.id=cc.coupons_id','left')->field('c.name,c.price,cs.code')->where($where)->find();
    		if($code){
    			echo json_encode(['status'=>1,'data'=>['price'=>$code['price'],'name'=>$code['name'],'code'=>$code['code']],'msg'=>'抵用券不存在或者在本店不能使用！']);
	    		exit;
    		}else{
	    		echo json_encode(['status'=>0,'msg'=>'抵用券不存在、已使用或者在本店不能使用！']);
	    		exit;
    		}
    	}else{
    		echo json_encode(['status'=>0,'msg'=>'请输入抵用券']);
    		exit;
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
    	$total = $real;

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
    		$name = MCT::get($min);
    		if($ajax){
    			echo json_encode(['status'=>1,'id'=>$min,'name'=>$name['name']]);
    			exit;
    		}else{
    			return json_encode(['status'=>1,'id'=>$min,'name'=>$name['name']]);
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
    	/*if($re['from']==2){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'老会员卡']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'老会员卡']);
    		}
    	}*/
    	if($re['arrears_c']!=1){
    		if($ajax){
    			echo json_encode(['status'=>0,'msg'=>'会员卡欠款后第一次消费，无需更换卡类型']);
    			exit;
    		}else{
    			return json_encode(['status'=>0,'msg'=>'会员卡欠款后第一次消费，无需更换卡类型']);
    		}
    	}
    	//上次充值金额
		$last_cashier = C::where(['member_no'=>$data['card_no'],'order_type'=>['in','2,3']])->order('time DESC')->limit(1)->find();
    	$total = $data['real'] + $last_cashier['real_money'];
    	//$member_type = MCT::alias('mct')->join('member as m','mct.id=m.card_type','left')->where(['m.card_no'=>$data['card_no']])->find();
    	return $this->getCTbyOpen($data['real'],$last_cashier['real_money'],$ajax);
    }
    /*public function getCTbyRecharge_transfercard($real = 0,$ajax = 1,$card_no=null){
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
    	$re = MC::alias('mc')->field('w.*')->join('wallet as w','mc.id=w.member_id','left')->where(['mc.card_no'=>$data['card_no']])->find();
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
    	//上次会员卡类型不存在，按照开卡标准计算
    	if(!$member_type['last_card_type']){
    		return $this->getCTbyOpen($data['real'],$last_cashier['real_money'],$ajax);
    	}
    	//判定是否满足上一次会员卡本身卡类型的充值标准
    	//如不满足，按照开卡标准计算
    	//如满足，往下走
    	$last_member_type = MCT::alias('mct')->join('member as m','mct.id=m.last_card_type','left')->where(['m.card_no'=>$data['card_no']])->find();

    	if($last_member_type['recharge_standard']>$total){
    		return $this->getCTbyOpen($data['real'],$last_cashier['real_money'],$ajax);
    	}
    	//获取该门店所有会员卡类型
    	$ctlist = CardTypeCompany::alias('ctc')->field("mct.id,mct.recharge_standard,ctcd.discount,mct.open_standard")->join('member_card_type as mct','ctc.card_type_id=mct.id','left')->join('card_type_children ctcd','ctc.card_type_id = ctcd.parent_id','left')->where(['mct.status'=>1,'mct.is_open'=>1,'ctc.company_id'=>Session::get("company_id"),'ctcd.pay_id'=>7])->select();
    	if(empty($ctlist)){
    		if($ajax){
    			echo json_encode(['status'=>1,'id'=>$last_member_type['last_card_type'],'cname'=>$last_member_type['name']]);
    			exit;
    		}else{
    			return json_encode(['status'=>1,'id'=>$last_member_type['last_card_type'],'cname'=>$last_member_type['name']]);
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
    			echo json_encode(['status'=>1,'id'=>$last_member_type['last_card_type'],'cname'=>$last_member_type['name']]);
    			exit;
    		}else{
    			return json_encode(['status'=>1,'id'=>$last_member_type['last_card_type'],'cname'=>$last_member_type['name']]);
    		}
    	}
    	//判定新会员卡类型和以前会员卡类型的折扣
    	//如新会员卡类型折扣低，会员卡升级到新的会员卡类型
    	//如以前会员卡类型折扣低，保持上次会员卡类型
    	$min = array_search(min($list),$list);
    	if($min){
    		$card_rate = CTC::where(['parent_id'=>$last_member_type['last_card_type'],'pay_id'=>7])->find();
    		if($ratelist[$min]<$card_rate['discount']){
    			$newcardt = MCT::where(['id'=>$min])->find();
    			if($ajax){
	    			echo json_encode(['status'=>1,'id'=>$min,'name'=>$newcardt['name']]);
	    			exit;
	    		}else{
	    			return json_encode(['status'=>1,'id'=>$min,'name'=>$newcardt['name']]);
	    		}
    		}else{
    			if($ajax){
	    			echo json_encode(['status'=>1,'id'=>$last_member_type['last_card_type'],'cname'=>$last_member_type['name']]);
	    			exit;
	    		}else{
	    			return json_encode(['status'=>1,'id'=>$last_member_type['last_card_type'],'cname'=>$last_member_type['name']]);
	    		}
    		}
    	}else{
    		if($ajax){
    			echo json_encode(['status'=>1,'id'=>$last_member_type['last_card_type'],'cname'=>$last_member_type['name']]);
    			exit;
    		}else{
    			return json_encode(['status'=>1,'id'=>$last_member_type['last_card_type'],'cname'=>$last_member_type['name']]);
    		}
    	}
    }*/
    //发送验证码
    public function sendMessage(){
    	$data = input();
    	$code = rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9).rand(0,9);
    	Session::set('code'.$data['phone'],$code);
    	$data['msg'] = '尊敬的会员，您本次消费的验证码是: '.$code;
    	//$r = $re = '20170927155021,0
//17092715502128240';
    	$r = $re = self::msnSend($data['phone'],$data['msg']);
$im = '
';
    	$re = explode(',', $re);
    	$re = explode($im, $re[1]);
    	if(!$re[0]){
    		self::msgLog(['type'=>1,'content'=>$data['msg'],'status'=>1,'phone'=>$data['phone'],'re_code'=>$r]);
    		echo json_encode(['status'=>1,'code'=>$code]);
    	}else{
    		self::msgLog(['type'=>1,'content'=>$data['msg'],'status'=>1,'phone'=>$data['phone'],'re_code'=>$r]);
    		echo json_encode(['status'=>0,'msg'=>$re[0]]);
    	}
    }
    //发送消费短信
    public function sendConsumptionMsg($mobile_phone,$name,$card_no,$total,$wallet){
		$msg = '尊敬的会员'.$name.',会员卡卡号【'.$card_no.'】本次消费金额为'.$total.'元，储值账户余额'.$wallet['cash'].'元，电子钱包余额'.$wallet['ewallet'].'元，老疗程系统余额'.$wallet['old_cash'].'元，登录手机APP【鼎族】可查看消费明细';
		//$r = $re = self::msnSend($mobile_phone,$msg);
		$im = '
';
		$r = $re = '20170518171853,0
 17051817185325227';
        //$r = $re = self::msnSend($mobile_phone,$msg);
$im = '
';
        $re = explode(',', $re);
        $re = explode($im, $re[1]);
    	$re = $re[0]?0:1;
		self::msgLog(['type'=>2,'content'=>$msg,'phone'=>$mobile_phone,'status'=>$re,'re_code'=>$r]);
    }
    //业务单据类型
    public function business_documents(){
		$data = input();
    	if(Session::get('role')==1){
    		$id = isset($data['company'])&&$data['company']?$data['company']:Session::get('company_id');
    		$where = ' and company_id ='.$id;
    		$data['company'] = $id;
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		$where = ' and company_id ='.$id.' and app_order_id=0';
    		$data['company'] = $id;
    		$companylist = $this->getCompanyList($id,1);
    	}
    	$this->assign('companylist',$companylist);
    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time'])+6*3600:(strtotime(date('Y-m-d',time()))+6*3600);
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time'])+30*3600:time()+30*3600;
    	$this->assign('data',['company'=>$data['company'],'start_time'=>date('Y-m-d',$data['start_time']-6*3600),'end_time'=>date('Y-m-d',$data['end_time']-30*3600)]);
    	//$this->assign('data',['company'=>$data['company'],'start_time'=>$data['start_time'],'end_time'=>]);
    	//收银（消费/划卡等）
    	$cashier['total'] = model('cashier')->where('order_type=1 and time>='.$data['start_time'].' and time<='.$data['end_time'].$where)->count();
    	$cashier['normal'] = model('cashier')->where('order_type=1 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status!=2'.$where)->count();
    	$cashier['invalid'] = model('cashier')->where('order_type=1 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=2'.$where)->count();
    	$cashier['supplement'] = model('cashier')->where('order_type=1 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=3'.$where)->count();
    	$cashier['edited'] = model('cashier')->where('order_type=1 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=4'.$where)->count();
    	$this->assign('cashier',$cashier);
    	//充值单据
    	$recharge['total'] = model('cashier')->where('order_type=2 and time>='.$data['start_time'].' and time<='.$data['end_time'].$where)->count();
    	$recharge['normal'] = model('cashier')->where('order_type=2 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=1'.$where)->count();
    	$recharge['invalid'] = model('cashier')->where('order_type=2 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=2'.$where)->count();
    	$recharge['supplement'] = model('cashier')->where('order_type=2 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=3'.$where)->count();
    	$recharge['edited'] = model('cashier')->where('order_type=2 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=4'.$where)->count();
    	$this->assign('recharge',$recharge);
    	//开卡
    	$open['total'] = model('cashier')->where('order_type=3 and time>='.$data['start_time'].' and time<='.$data['end_time'].$where)->count();
    	$open['normal'] = model('cashier')->where('order_type=3 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status!=2'.$where)->count();
    	$open['invalid'] = model('cashier')->where('order_type=3 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=2'.$where)->count();
    	$open['supplement'] = model('cashier')->where('order_type=3 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=3'.$where)->count();
    	$open['edited'] = model('cashier')->where('order_type=3 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=4'.$where)->count();
    	$this->assign('open',$open);
    	//转卡
    	$transfer['total'] = model('cashier')->where('order_type=4 and time>='.$data['start_time'].' and time<='.$data['end_time'].$where)->count();
    	$transfer['normal'] = model('cashier')->where('order_type=4 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status!=2'.$where)->count();
    	$transfer['invalid'] = model('cashier')->where('order_type=4 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=2'.$where)->count();
    	$transfer['supplement'] = model('cashier')->where('order_type=4 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=3'.$where)->count();
    	$transfer['edited'] = model('cashier')->where('order_type=4 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=4'.$where)->count();
    	$this->assign('transfer',$transfer);
    	//还款
    	$refund['total'] = model('cashier')->where('order_type=5 and time>='.$data['start_time'].' and time<='.$data['end_time'].$where)->count();
    	$refund['normal'] = model('cashier')->where('order_type=5 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status!=2'.$where)->count();
    	$refund['invalid'] = model('cashier')->where('order_type=5 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=2'.$where)->count();
    	$refund['supplement'] = model('cashier')->where('order_type=5 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=3'.$where)->count();
    	$refund['edited'] = model('cashier')->where('order_type=5 and time>='.$data['start_time'].' and time<='.$data['end_time'].' and status=4'.$where)->count();
    	$this->assign('refund',$refund);
    	//抵用券

    	return $this->fetch();
    }
    //业务单据列表
    public function business_documents_list(){
    	$ordertype = [
    		1 => '收银（消费/划卡等）',
    		2 => '充值单据',
    		3 => '开卡单据',
    		4 => '转卡单据',
    		5 => '还款单据',
    	];
    	$data = input();
    	$start_time = $data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time'])+6*3600:(strtotime(date('Y-m-d'))+6*3600);
    	$end_time = $data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time'])+30*3600:time()+30*3600;
    	$data['start_time'] = date('Y-m-d',$data['start_time']);
    	$data['end_time'] = date('Y-m-d',$data['end_time']-30*3600);
    	$data['number'] = isset($data['number'])?$data['number']:'';
    	$data['company'] = isset($data['company'])?$data['company']:'';
    	$data['order_type'] = $ordertype[$data['order']];

    	if(Session::get('role')==1){
    		$id = isset($data['company'])&&$data['company']?$data['company']:Session::get('company_id');
    		$where = ' and company_id ='.$id.' and app_order_id=0';
    		$data['company'] = $id;
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		$where = ' and company_id ='.$id.' and app_order_id=0';
    		$data['company'] = $id;
    		$companylist = $this->getCompanyList($id,1);
    	}
    	$this->assign('data',$data);
    	if($data['number']){
    		$where = ' and number like "%'.$data['number'].'%"';
    	}
    	$this->assign('companylist',$companylist);

    	$list = model('cashier')->where('order_type='.$data['order'].' and time>='.$start_time.' and time<='.$end_time.$where)->order('id', 'DESC')->paginate(15,false,array('query'=>$data));
    	$this->assign('list',$list);
    	return $this->fetch();
    }
    //业务单据修改
    public function edit_business_documents($id = 0){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $log = ACL::where(['cashier_id'=>$id])->find();
    		$data['time'] = isset($data['time'])?strtotime($data['time']):0;
    		//$data['status'] = 4;
    		$id = $data['id'];
    		$cashier = new C;
    		$cashier->save(['time'=>$data['time'],'status'=>4],array('id'=>$id));
	        if(1){
	        	$cashier = C::get($id);
	        	if($cashier['order_type']==1){
		        	if(isset($data['pay'])){
		        		foreach ($data['pay'] as $k => $v) {
		        			$cs = CS::update(['pay'=>$v],['id'=>$k]);
		        		}
		        	}
	        	}else{
	        		if(isset($data['pay'])){
		        		foreach ($data['pay'] as $k => $v) {
		        			$cpw = CPW::update(['pay_type'=>$v],['id'=>$k]);
		        		}
		        	}
	        	}
	        	//更改支付方式之后需要对应更改日志数据
	        	if(!empty($log)){
	        		//修改app消费日志
	    			$datalog = json_decode($log['text'],true);
	    			$datalog['time'] = $data['time'];
	    			$datalog['pay_time'] = $data['time'];
	    			$log->time = $data['time'];
	    			$log->text = json_encode($datalog);
	    			$log->save();
	        	}

		        return $this->success('单据修改成功','/Index/Cashier/business_documents_list/order/'.$cashier['order_type']);
		    } else {
		        return $this->error($cashier->getError());
		    }
    	}else{
    		if(!empty($id)&&is_numeric($id)){
    			$cashier = C::get($id);
    			$member = MC::get($cashier['member_id']);
    			$cashier['member_name'] = $member['name'];
	    		$this->assign('cashier',$cashier);
    			$data = $this->getUsedData('6,10,64');
	    			$used_list = [0=>'无'];
	    			foreach ($data as $k => $v) {
	    				$used_list[$v['id']] = $v['name'];
	    			}
    			if($cashier['order_type']==1){
    				$cashiers = Cs::where('parent_id='.$id)->select();
	    			
	    			foreach ($cashiers as $k => &$v) {
	    				$v['pay_name'] = $used_list[$v['pay']];
	    				$v['deductible_pay'] = $used_list[$v['deductible_pay']];
	    				$v['ftype'] = $used_list[$v['ftype']];
	    				$v['stype'] = $used_list[$v['stype']];
	    			}
	    			$this->assign('cashiers',$cashiers);

	    			return $this->fetch();
    			}else{
    				$cpw = CPW::where('cashier_id='.$id)->select();
    				foreach ($cpw as $k => &$v) {
    					$v['pay_name'] = $used_list[$v['pay_type']];
	    			}
	    			$this->assign('cpw',$cpw);

	    			return $this->fetch('no_consumption');
    			}
    			
    		}
    	}
    }
    //单据作废
    public function del_business_documents($id = 0,$return_bool = false){
    	if($id&&is_numeric($id)){
    		$cashier = new C;
    		$order = C::get($id);
    		$log = new ConsumptionLog;
			$company = Db::table('company')->find(Session::get('company_id'));
    		//软删除app消费日志
    		ACL::where(['cashier_id'=>$id])->setField('status',0);
    		
    		if($order['order_type']!=1){
    			if($order['order_type']==3){//开卡
    				MC::destroy(['id'=>$order['member_id']]);
    				Wallet::destroy(['member_id'=>$order['member_id']]);
    				if(!$cashier->save(['status'=>2],array('id'=>$id))){
    					$this->error('作废失败');
    				}else{
    					if($return_bool){
    						return true;
    					}else{
    						return $this->success('单据作废','/Index/Cashier/business_documents_list/order/'.$order['order_type']);
    					}
    				}
    			}elseif($order['order_type']==4){//转卡
    				$member = MC::get($order['member_id']);
    				if($member['last_card_id']){
    					$member = MC::get($member['last_card_id']);
    					MC::destroy(['id'=>$order['member_id']]);
    					Wallet::destroy(['member_id'=>$order['member_id']]);
    					$member->status = 1;
	    				$member->save();
	    				if(!$cashier->save(['status'=>2],array('id'=>$id))){
	    					$this->error('作废失败');
	    				}else{
	    					if($return_bool){
	    						return true;
	    					}else{
	    						return $this->success('单据作废','/Index/Cashier/business_documents_list/order/'.$order['order_type']);
	    					}
	    				}
    				}
    			}
	    		$member = MC::get($order['member_id']);
    			//卡类型回滚
    			if($member['last_card_type']){
    				$member->card_type = $member['last_card_type'];
	    			$member->save();
    			}
    			$wallet_old = $wallet = Wallet::where(['member_id'=>$order['member_id']])->find();
    			$cpw = CPW::where(['cashier_id'=>$order['id']])->select();
    			$total = 0;
    			$arrears = 0;
    			foreach ($cpw as $k => $v) {
    				if(!in_array($v['pay_type'], [12,17,14])){
    					$total+= $v['money'];
    				}else{
    					$arrears+= $v['money'];
    				}
    			}
    			if($cpw[0]['wallet_type']==7){
    				if($wallet['cash']<$total){
    					return $this->error('账户充值后已产生消费，请作废消费单据后方可作废充值单据');
    				}else{
    					$wallet->cash = $wallet->cash-$total;
    					if($order['order_type']==5){
    						$wallet->cash_arrears = $total;
    					}else{
    						$wallet->cash_arrears = $wallet->cash_arrears-$arrears;
    					}
    					if($wallet->save()){
    						if(!$cashier->save(['status'=>2],array('id'=>$id))){
    							$this->error('作废失败');
    						}else{
			    					$logdata = [
						    			'company_id' => Session::get('company_id'),
						    			'company_name' => $company['full_name'],
						    			'activer_id' => Session::get('family_id'),
						    			'activer_name' => Session::get('family_name'),
						    			'wallet_type' => 7,
						    			'active_type' => 4,
						    			'order_no' => $order['number'],
						    			'last_balance' => $wallet_old['cash'],
						    			'this_balance' => $wallet['cash'],
						    			'services_id' => 0,
						    			'services_count' => 0,
						    			'services_name' => '',
						    			'pay_type' => $cpw[0]['pay_type'],
						    			'member_id' => $member['id'],
						    			'member_name' => $member['name'],
						    			'member_no' => $member['card_no'],
						    			'time' => time(),
						    			'cashier_id' => $order['id'],
						    			'cash' => $total,
					    			];
						    		$log->insert($logdata);
    						}
    					}
    				}
    			}elseif($cpw[0]['wallet_type']==8){
    				if($wallet['ewallet']<$total){
    					return $this->error('账户充值后已产生消费，请作废消费单据后方可作废充值单据');
    				}else{
    					$wallet->ewallet = $wallet->ewallet-$total;
    					if($wallet->save()){
    						if(!$cashier->save(['status'=>2],array('id'=>$id))){
    							$this->error('作废失败');
    						}else{
			    					$logdata = [
						    			'company_id' => Session::get('company_id'),
						    			'company_name' => $company['full_name'],
						    			'activer_id' => Session::get('family_id'),
						    			'activer_name' => Session::get('family_name'),
						    			'wallet_type' => 7,
						    			'active_type' => 4,
						    			'order_no' => $order['number'],
						    			'last_balance' => $wallet_old['ewallet'],
						    			'this_balance' => $wallet['ewallet'],
						    			'services_id' => 0,
						    			'services_count' => 0,
						    			'services_name' => '',
						    			'pay_type' => $cpw[0]['pay_type'],
						    			'member_id' => $member['id'],
						    			'member_name' => $member['name'],
						    			'member_no' => $member['card_no'],
						    			'time' => time(),
						    			'cashier_id' => $order['id'],
						    			'cash' => $total,
					    			];
						    		$log->insert($logdata);
    						}
    					}
    				}
    			}elseif($cpw[0]['wallet_type']==9){
    				if($wallet['old_cash']<$total){
    					return $this->error('账户充值后已产生消费，请作废消费单据后方可作废充值单据');
    				}else{
    					$wallet->old_cash = $wallet->old_cash-$total;
    					if($order['order_type']==5){
    						$wallet->old_cash_arrears = $total;
    					}else{
    						$wallet->old_cash_arrears = $wallet->old_cash_arrears-$arrears;
    					}
    					if($wallet->save()){
    						if(!$cashier->save(['status'=>2],array('id'=>$id))){
    							$this->error('作废失败');
    						}else{
			    					$logdata = [
						    			'company_id' => Session::get('company_id'),
						    			'company_name' => $company['full_name'],
						    			'activer_id' => Session::get('family_id'),
						    			'activer_name' => Session::get('family_name'),
						    			'wallet_type' => 7,
						    			'active_type' => 4,
						    			'order_no' => $order['number'],
						    			'last_balance' => $wallet_old['old_cash'],
						    			'this_balance' => $wallet['old_cash'],
						    			'services_id' => 0,
						    			'services_count' => 0,
						    			'services_name' => '',
						    			'pay_type' => $cpw[0]['pay_type'],
						    			'member_id' => $member['id'],
						    			'member_name' => $member['name'],
						    			'member_no' => $member['card_no'],
						    			'time' => time(),
						    			'cashier_id' => $order['id'],
						    			'cash' => $total,
					    			];
						    		$log->insert($logdata);
    						}
    					}
    				}
    			}
    			if($return_bool){
    				return true;
    			}else{
    				return $this->success('单据作废','/Index/Cashier/business_documents_list/order/'.$order['order_type']);
    			}
    		}
    		if($cashier->save(['status'=>2],array('id'=>$id))){
    			$cashier = C::get($id);
    			if($cashier->order_type==1){
    				//消费单据作废钱包回滚
	    			if($order['member_id']){
	    				$member = MC::get($order['member_id']);
		    			//退回钱包金额
		    			$cashierdata = Cs::alias('cs')->join('cashier c','cs.parent_id = c.id','left')->where(['c.id'=>$id,'c.status'=>2])->select();
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
		    				if($v['deductible_pay']==17){
		    					Cos::where(['code'=>$v['coupons_code']])->setField('status',0);
		    				}
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
			    					$company = Db::table('company')->find(Session::get('company_id'));
			    					$logdata = [
						    			'company_id' => Session::get('company_id'),
						    			'company_name' => $company['full_name'],
						    			'activer_id' => Session::get('family_id'),
						    			'activer_name' => Session::get('family_name'),
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
				        	return $this->error($wallet->getError());
				     	}
				     	if($return_bool){
		    				return true;
		    			}else{
		    				return $this->success('单据作废','/Index/Cashier/business_documents_list/order/'.$order['order_type']);
		    			}
	    			}else{
	    				if($return_bool){
		    				return true;
		    			}else{
		    				return $this->success('单据作废','/Index/Cashier/business_documents_list/order/'.$order['order_type']);
		    			}
	    			}
    			}
		    } else {
		         return $this->error($cashier->getError());
		    }
    	}
    }
    //返还充值类订单的作废数据
    public function cashier_refund(){
    	//写回滚日志
    	$log = new ConsumptionLog;
			    					$company = Db::table('company')->find(Session::get('company_id'));
			    					$logdata = [
						    			'company_id' => Session::get('company_id'),
						    			'company_name' => $company['full_name'],
						    			'activer_id' => Session::get('family_id'),
						    			'activer_name' => Session::get('family_name'),
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
    //app订单完成
    public function falishCashier(){
    	$data = input();
    	$cashier = C::get($data['id']);
    	$cashier->is_confirm = 1;
    	if($cashier->save()){
    		return $this->success('订单完成');
    	}else{
    		return $this->error($cashier->getError());
    	}
    }
    //打印销卡小票功能
    public function print_cashier($cashier_id=0){
    	if($cashier_id){
    		$cashier = C::get($cashier_id);
    		$cashiers = CS::where(['parent_id'=>$cashier_id])->select();
    		$is_sanke = 1;
    		if($cashier['member_id']){
    			$is_sanke = 0;
    			$member = MC::get($cashier['member_id']);
    			$wallet = Wallet::where(['member_id'=>$cashier['member_id']])->find();
    			$this->assign('member',$member);
    			$this->assign('wallet',$wallet);
    		}
    		$this->assign('is_sanke',$is_sanke);
    		$consumption_list = [];
    		$used_data = $this->getUsedData('6,10');
    		$used_list = [];
    		foreach ($used_data as $k => $v) {
	    		$used_list[$v['id']] = $v['name'];
	    	}
	    	$cashiers_list [] = [];
	    	$yuanjia = 0;
	    	$payway = [];
    		foreach ($cashiers as $k => $v) {
    			$cashiers_list[$k] = [
    				'pay_id' => $v['pay'],
    				'pay_name' => $used_list[$v['pay']],
    				'discount' => $v['discount'], 
    				'service_name' => $v['service_name'],
    				'service_price' => $v['service_price'],
    				'count' => number_format($v['count'], 1, '.', ''),
    				'fworker' => $v['fworker'],
    				'discount_value' => $v['discount_value'],
    			];
    			//原价
    			$yuanjia+= $v['count']*$v['service_price'];

    			$f = Family::where(['number'=>$v['fworker']])->find();
    			$cashiers_list[$k]['fname'] = $f['name'];
    			$cashiers_list[$k]['sworker'] = '';
    			$cashiers_list[$k]['sname'] = '';
    			if($v['sworker']){
    				$s = Family::where(['number'=>$v['sworker']])->find();
    				$cashiers_list[$k]['sworker'] = $v['sworker'];
    				$cashiers_list[$k]['sname'] = $s['name'];
    			}
    			@$payway[$v['pay']]['discount']+=$v['discount'];
    			@$payway[$v['pay']]['name'] = $used_list[$v['pay']];
    		}
    		$this->assign('payway',$payway);
    		$this->assign('yuanjia',$yuanjia);
    		//省了多少钱

    		$cashier['dis_money'] = $yuanjia-$cashier['real_money'];
    		$this->assign('cashier',$cashier);
    		$this->assign('cashiers_list',$cashiers_list);
    		$this->assign('company',Company::get(Session::get('company_id')));

    		$this->assign('msg','收银成功');
    		$this->assign('code',1);
    		$this->assign('url','/Index/Cashier/index');
    		$this->assign('wait',3);
    		return $this->fetch();
    	}
    }
    //检查单号是否重复
    public function checkOrderNo(){
    	$data = input();
    	$cashier = C::where(['status'=>1,'number'=>$data['order_no']])->find();
    	if(!empty($cashier)){
    		echo json_encode(['sta'=>0,'msg'=>'单号已存在，请注意']);
    		exit;
    	}else{
    		echo json_encode(['sta'=>1]);
    		exit;
    	}
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
    //APP订单列表
    public function app_cashier_list(){
    	$data = input();
    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d'))+6*3600);
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):time();
    	if(Session::get('role')==1){
    		$id = isset($data['company'])?$data['company']:Session::get('company_id');
    		$where = 'c.company_id ='.$id;
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		/*if(isset($data['company'])){
    			$clist = $this->getChildCompany($id);
    			$where = ' and c.company_id in('.$clist.')';
    		}else{
	    		$where = ' and c.company_id in('.$id.')';
    		}*/
    		$where = 'c.company_id ='.$id;
    		$companylist = $this->getCompanyList($id,1);
    	}

    	$where .= ' and c.time>='.$data['start_time'].' and c.time<='.$data['end_time'].' and c.app_order_id>0';

    	$this->assign('companylist',$companylist);

    	$data['start_time'] = date('Y-m-d H:i:s',$data['start_time']);
    	$data['end_time'] = date('Y-m-d H:i:s',$data['end_time']);
    	$data['company_id'] = $id;
    	$this->assign('data',$data);

    	//获取app订单
    	$applist = C::alias('c')->field('*,c.id as cid,c.time as ctime,c.status as cstatus')->join('member m','c.member_id=m.id','left')->where($where)->order('c.id DESC')->paginate(15,false,array('query'=>$data));  
    	foreach ($applist as $k => $v) {
    		$card_type = MCT::get($v['card_type']);
    		$cashiers = CS::where(['parent_id'=>$v['cid']])->select();
    		$applist[$k]['family_str'] = '';
    		foreach ($cashiers as $key => $value) {
    			$applist[$k]['family_str'] .= $value['fworker'].' '.$value['sworker'].'/';
    		}
    		$applist[$k]['card_type_name'] = isset($card_type['name'])?$card_type['name']:'';
    	}
    	$this->assign('applist',$applist);
    	return $this->fetch();
    }
    //修改app产生的订单
    public function editAppCashier(){
    	if(Request::instance()->isPost()){
    		$data = input();
    		//重新买单
    		$old_cashier = C::get($data['id']);

    		//废单
    		$this->del_business_documents($data['id'],true);
    		
    		if(!$old_cashier['member_no']){
    			//散客买单
    			$total = 0;
    			$deductible = 0;
    			foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	$total+=$data['dis-price'][$k];
		        	$deductible+=$data['deductible'][$k];
		        }
		        //如有优惠券，判断优惠券能否可用，使用金额是否超额
		        //将所有优惠券归集
		        $codelist = [];
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	if($data['deductible_type'][$k]==17){
		        		@$codelist[$data['coupons_code'][$k]] += $data['deductible'][$k];
		        	}
		        }
		        //验证优惠券
		        foreach ($codelist as $k => $v) {
		        	$company_id = Session::get('company_id');
		    		$where = [
		    			'cc.company_id' => $company_id,
		    			'cs.code' => $k,
		    			'cs.status' => 0,
		    		];
		        	$code = Cos::alias('cs')->join('coupons c','cs.parent_id=c.id','left')->join('coupons_company cc','c.id=cc.coupons_id','left')->field('c.name,c.price,cs.code')->where($where)->find();
		        	if(empty($code)){
		        		$this->error('抵扣码'.$k.'不存在、已使用或者不能在本店消费');
		        	}
		        	if($code['price']<$v){
		        		$this->error('抵扣码:'.$k.',使用金额为:'.$v.'，超出本身金额:'.$code['price']);
		        	}
		        }

    			//订单数据
		        $cashierdata = [
		        	'company_id' => $old_cashier['company_id'],
		        	'type' => 2,
		        	'number' => $old_cashier['number'],
		        	'member_id' => 0,
		        	'member_no' => '',
		        	'sex' => $old_cashier['sex'],
		        	'count' => $old_cashier['count'],
		        	'girl_count' => $old_cashier['girl_count'],
		        	'remark' => $old_cashier['remark'],
		        	'real_money' => round($total),
		        	'time' => time(),
		        	'active_id' => Session::get('login_id'),
		        	'order_type' => 1,
		        	'should_money' => $deductible + $total,
		        	'status' => 1,
		        	'app_order_id' => $old_cashier['app_order_id'],
		        ];
		        $cashier = new C;
		        if(!$cashier->save($cashierdata)){
		        	$this->error($cashier->getError());
		        }
		        //订单子表
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	//订单数据
		        	$fworker = Family::where(['number'=>$data['fworkrer'][$k]])->find();
		        	$sworker = Family::where(['number'=>$data['sworkrer'][$k]])->find();
		        	$services = Services::alias('ss')->field('ss.name as ssname,s.name as sname,s.count as count,ss.*')->join('service s','ss.parent_id=s.id','left')->where(['ss.id'=>$data['services'][$k]])->find();
		        	$cashiersdata[$k] = [
		        		'parent_id' => $cashier['id'],
		        		'services_name' => $services['ssname'],
		        		'service_name' => $services['sname'],
		        		'services_id' => $services['id'],
		        		'service_id' => $services['parent_id'],
		        		'star' => $data['star'][$k],
		        		'time_long' => $data['time_long'][$k],
		        		'deductible_pay' => $data['deductible_type'][$k],
		        		'deductible' => $data['deductible'][$k],
		        		'pay' => $v,
		        		'standard_price' => $data['price'][$k],
		        		'discount' => round($data['dis-price'][$k]),
		        		'count' => $data['counts'][$k],
		        		'fworker_id' => $fworker['id'],
		        		'ftype' => $data['stype1'][$k],
		        		'ftype_name' => '',
		        		'fworker' => $fworker['number'],
		        		'sworker_id' => isset($sworker['id'])?$sworker['id']:0,
		        		'stype' =>  $data['stype2'][$k],
		        		'stype_name' => '',
		        		'sworker' => isset($sworker['number'])?$sworker['number']:'',
		        		'service_price' => $data['price'][$k],
		        		'total' => $data['price'][$k]*$data['counts'][$k],
		        	];
		        	if($cashiersdata[$k]['deductible_pay']==17){
		        		$cashiersdata[$k]['coupons_code'] = $data['coupons_code'][$k];
		        		Cos::where(array('code'=>$cashiersdata[$k]['coupons_code']))->setField('status',1);
		        	}
		        }
		        $cashiers = new CS;
		        if(!$cashiers->saveAll($cashiersdata)){
		        	$this->error($cashiers->getError());
		        }
    		}else{
    			//会员买单
    			// 订单数据验证
		        //$validate1 = new Validate();
		        //$result1 = $validate1->check(['number'=>$data['work_order']]);
		        //if(!$result1){
		        //    return  $validate1->getError();
		        //}
		        //可消费地控制
		        $member = MC::where(['card_no'=>trim($data['card_no'])])->find();
		        $wallet = Wallet::where(['member_id'=>$member['id']])->find();
		        $old_cashier = C::get($data['id']);

		        $last_balance = [
		        	'cash' => $wallet['cash'],
		        	'ewallet' => $wallet['ewallet'],
		        	'old_cash' => $wallet['old_cash'],
		        ];
		        $companylist = $this->getChildCompany($member->company_area);
		        $companylist = explode(',', $companylist);
		        if(!in_array(Session::get('company_id'), $companylist)){
		        	return $this->error('该卡不能再本店消费！');
		        }
		        //对应账户是否有足额的钱
		        $cash_consumption = 0;
		        $ewallet_consumption = 0;
		        $old_cash_consumption = 0;
		        $total = 0;
		        $deductible = 0;
		        $cashiersdata = [];
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	$total+=$data['dis-price'][$k];
		        	$deductible+=$data['deductible'][$k];
		        	if($v==7){
		        		$cash_consumption+=$data['dis-price'][$k];
		        	}elseif($v==8){
		        		$ewallet_consumption+=$data['dis-price'][$k];
		        	}elseif($v==9){
		        		$old_cash_consumption+=$data['dis-price'][$k];
		        	}
		        }
		        if($cash_consumption>$wallet['cash']){
		        	return $this->error('储值账户余额不足，请提醒他充值！');
		        }
		        if($ewallet_consumption>$wallet['ewallet']){
		        	return $this->error('电子钱包余额不足，请提醒他充值！');
		        }
		        if($old_cash_consumption>$wallet['old_cash']){
		        	return $this->error('老疗程系统余额不足，请提醒他充值！');
		        }
		        //判断会员卡是否有欠款未还
		        if($wallet['arrears_c']==1){
		        	$use = Db::table('used_data')->where('id=80')->find();
			        if($use['status']==1){
			        	//上次充值金额
			        	$last_cashier = C::where(['member_no'=>$data['card_no'],'order_type'=>['in','2,3,4']])->order('time DESC')->limit(1)->find();
			        	//$card = json_encode($this->getCTbyRecharge($last_cashier['real_money'],0));
			        	$card = json_decode($this->getCTbyRecharge_transfercard($last_cashier['real_money'],0,$data['card_no']),true);
				        if($card['status']==1){
				        	if(count($card['data'])==1){
				        		db('member')->where('id',$member['id'])->setField('last_card_type',$card['data'][0]['id']);
					        	db('member')->where('id',$member['id'])->setField('card_type',$card['data'][0]['id']);
					        	@db('member')->where('id',$member['id'])->setField('from',1);
					        	db('wallet')->where('member_id',$member['id'])->setField('cash_arrears',0);
					        	db('wallet')->where('member_id',$member['id'])->setField('arrears_c',0);
	    						return $this->success('更改卡类型成功，请重新买单','/Index/Cashier');
				        	}elseif(count($card['data'])>1){
				        		$this->redirect('Cashier/changeCardType',['card_no' => trim($data['card_no']),'card_type_list'=>base64_encode(json_encode($card['data']))]);
				        	}else{
				        		return $this->error('没有对应的会员卡类型，更改卡类型失败');
				        	}
				        }else{
				        	return $this->error('没有对应的会员卡类型，更改卡类型失败');
				    	}
			        }

		        	$member = MC::where(['card_no'=>trim($data['card_no'])])->find();
		        	$wallet = Wallet::where(['member_id'=>$member['id']])->find();
		        }
		        //如有优惠券，判断优惠券能否可用，使用金额是否超额
		        //将所有优惠券归集
		        $codelist = [];
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	if($data['deductible_type'][$k]==17){
		        		@$codelist[$data['coupons_code'][$k]] += $data['deductible'][$k];
		        	}
		        }
		        //验证优惠券
		        foreach ($codelist as $k => $v) {
		        	$company_id = Session::get('company_id');
		    		$where = [
		    			'cc.company_id' => $company_id,
		    			'cs.code' => $k,
		    		];
		        	$code = Cos::alias('cs')->join('coupons c','cs.parent_id=c.id','left')->join('coupons_company cc','c.id=cc.coupons_id','left')->field('c.name,c.price,cs.code')->where($where)->find();
		        	if(empty($code)){
		        		$this->error('抵扣码'.$k.'不存在或者不能在本店消费');
		        	}
		        	if($code['price']<$v){
		        		$this->error('抵扣码:'.$k.',使用金额为:'.$v.'，超出本身金额:'.$code['price']);
		        	}
		        }

		        //钱包扣款
		        $newwallet = $wallet;
		        $newwallet->cash = $newwallet->cash-$cash_consumption;
		        $newwallet->ewallet = $newwallet->ewallet-$ewallet_consumption;
		        $newwallet->old_cash = $newwallet->old_cash-$old_cash_consumption;
		        $newwallet->arrears_c = $newwallet->arrears_c==2?1:$newwallet->arrears_c;
		        $newwallet->save();
		        /*if(!$newwallet->save()){
		        	$this->error($newwallet->getError());
		        }*/
		        //订单数据
		        $cashierdata = [
		        	'company_id' => Session::get('company_id'),
		        	'type' => 1,
		        	'number' => $old_cashier['number'],
		        	'member_id' => $old_cashier['member_id'],
		        	'member_no' => $old_cashier['member_no'],
		        	'sex' => $old_cashier['sex'],
		        	'count' => $old_cashier['count'],
		        	'girl_count' => $old_cashier['girl_count'],
		        	'remark' => $old_cashier['remark'],
		        	'verification' => $old_cashier['verification'],
		        	'real_money' => round($total),
		        	'time' => time(),
		        	'active_id' => Session::get('login_id'),
		        	'order_type' => 1,
		        	'should_money' => round($deductible)+round($total),
		        	'status' => 1,
		        ];
		        $cashier = new C;
		        if(!$cashier->save($cashierdata)){
		        	$this->error($cashier->getError());
		        }
		        //
		        $cashchangedatatemp = [
		        	7 => 0,
		        	8 => 0,
		        	9 => 0,
		        ];
		        //订单子表
		        foreach ($data['paywaylist'] as $k => $v) {
    				if(!$v){
    					continue;
    				}
		        	//订单数据
		        	$fworker = Family::where(['number'=>$data['fworkrer'][$k]])->find();
		        	$sworker = Family::where(['number'=>$data['sworkrer'][$k]])->find();
		        	$services = Services::alias('ss')->field('ss.name as ssname,s.name as sname,s.count as count,ss.*')->join('service s','ss.parent_id=s.id','left')->where(['ss.id'=>$data['services'][$k]])->find();
		        	$cashiersdata[$k] = [
		        		'parent_id' => $cashier['id'],
		        		'services_name' => $services['ssname'],
		        		'service_name' => $services['sname'],
		        		'services_id' => $services['id'],
		        		'service_id' => $services['parent_id'],
		        		'star' => $data['star'][$k],
		        		'time_long' => $data['time_long'][$k],
		        		'deductible_pay' => $data['deductible_type'][$k],
		        		'deductible' => $data['deductible'][$k],
		        		'pay' => $v,
		        		'standard_price' => $data['price'][$k],
		        		'discount' => $data['dis-price'][$k],
		        		'count' => $data['counts'][$k],
		        		'fworker_id' => $fworker['id'],
		        		'ftype' => $data['stype1'][$k],
		        		'ftype_name' => '',
		        		'fworker' => $fworker['number'],
		        		'sworker_id' => isset($sworker['id'])?$sworker['id']:0,
		        		'stype' =>  $data['stype2'][$k],
		        		'stype_name' => '',
		        		'sworker' => isset($sworker['number'])?$sworker['number']:'',
		        		'service_price' => $data['price'][$k],
		        		'total' => $data['price'][$k]*$data['counts'][$k],
		        	];
		        	if($cashiersdata[$k]['deductible_pay']==17){
		        		$cashiersdata[$k]['coupons_code'] = $data['coupons_code'][$k];
		        		Cos::where(array('code'=>$cashiersdata[$k]['coupons_code']))->setField('status',1);
		        	}
		        	if($v==7){
		        		$cashchangedatatemp[7]+=$data['dis-price'][$k];
		        	}elseif($v==8){
		        		$cashchangedatatemp[8]+=$data['dis-price'][$k];
		        	}elseif($v==9){
		        		$cashchangedatatemp[9]=$data['dis-price'][$k];
		        	}
		        }
		        $cashiers = new CS;
		        if(!$cashiers->saveAll($cashiersdata)){
		        	$this->error($cashiers->getError());
		        }
		        //节点
		        //钱包金额变动表数据
		        foreach ($cashchangedatatemp as $k => $v) {
		        	$cashchangedata = [];
		        	if($k==7&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 7,
			    			'active_type' => 1,
			    			'order_no' => $old_cashier['number'],
			    			'last_balance' => $last_balance['cash'],
			    			'this_balance' => $newwallet['cash'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}elseif($k==8&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 8,
			    			'active_type' => 1,
			    			'order_no' => $old_cashier['work_order'],
			    			'ewallet' => $v,
			    			'last_balance' => $last_balance['ewallet'],
			    			'this_balance' => $newwallet['ewallet'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}elseif($k==9&&$v){
		        		$cashchangedata = [
		        			'wallet_type' => 9,
			    			'active_type' => 1,
			    			'order_no' => $old_cashier['work_order'],
			    			'ewallet' => $v,
			    			'last_balance' => $last_balance['old_cash'],
			    			'this_balance' => $newwallet['old_cash'],
			    			'services_id' => $services['id'],
			    			'services_count' => $services['count'],
			    			'services_name' => $services['sname'],
			    			'pay_type' => $data['paywaylist'][0],
			    			'pay_name' => 1,
			    			'member_id' => $member->id,
			    			'member_name' => $member->name,
			    			'member_no' => $member->card_no,
			    			'cashier_id' => $cashier->id,
			    			'cash' => $v,
		        		];
		        	}
		        	if($cashchangedata){
		        		$this->setConsumptionLog($cashchangedata);
		        	}
		        }
    		}
    		if($cashier->id){
    			//消费日志
    			if($data['card_no']&&isset($member)){
    				$this->sendConsumptionMsg($member['mobile_phone'],$member['name'],$member['card_no'],$total,$wallet);
    				$member_card = MCT::alias('mct')->field('ctc.*')->join('card_type_children ctc','mct.id=ctc.parent_id','left')->where(['mct.id'=>$member['card_type']])->find();
    			}else{
    				$member_card['discount'] = 1;
    				$member['card_no'] = '';
    			}
    			//app需要的消费日志
    			$company = Company::get(Session::get('company_id'));
    			
    			$aclogdata = [
    				'cashier_id' => $cashier->id,
    				'time' => time(),//下单时间
    				'discount_money' =>  round($total), //消费金额
    				'company_id' => Session::get('company_id'),//消费门店
    				'company_name' => $company['full_name'],//消费门店名字
    				'discount' => $member_card['discount'],//折扣
    				'coupon' => '',//优惠券
    				'order_no' => $old_cashier['number'],//单号
    				'payway' => '会员卡', //支付方式
    				'payway_id' => $data['paywaylist'][0],//支付方式
    				'pay_time' => time(),//支付时间
    				'pay_status' => 1,//支付状态  0:进行中1:已完成2:已取消3:删除(app订单状态)
    				'services' => $cashiersdata,
    				'app_order_id' => '',
	    			'app_order_name' => $cashiersdata[0]['service_name'],//订单名称
    				'card_no' => $member['card_no'],//会员卡号
    				'order_type' => 1,//订单类型（1:服务预约2:酒店预约3:汽车预约4:土特产品5:上门预约订单详情6:上门服务集团预约详情7：充值）
	    			'cashier_type' => 1,//收银系统订单类型（1：消费，2：充值，3：开卡，4：转卡，5：还款）
    			];
    			$this->setAppConsumptionLog($aclogdata);

    			return $this->success('收银成功','/Index/Cashier');
    			/*if(isset($data['is_sanke'])){
    				return $this->success('收银成功','/Index/Cashier');
    			}else{
    				//打印小票
    				$this->redirect('Cashier/print_cashier', ['cashier_id' => $cashier->id]);
    			}*/
    			
    		}else{                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  
    			return $this->success('收银失败','/Index/Cashier');
    		}

    		return $this->success('订单修改成功','/Index/Cashier/app_cashier_list');
    	}else{
    		$data = input();

	    	$cashier = C::get($data['id']);
	    	if($cashier['status']==2){
	    		$this->error('该单已经作废');
	    	}

		    $cashiers = CS::where(['parent_id'=>$data['id']])->select();
		    //常用资料
		    $usedata = $this->getUsedData('10,6');
		    $use_data = [];
		    foreach ($usedata as $k => $v) {
		    	$use_data[$v['id']] = $v['name'];
		    }
		    foreach ($cashiers as $k => $v) {
		    	$cashiers[$k]['pay_name'] = $use_data[$v['pay']];
		    	$cashiers[$k]['deductible_pay_name'] = $v['deductible_pay']?$use_data[$v['deductible_pay']]:'无';
		    }
		    $this->assign('cashier',$cashier);
		    $this->assign('cashiers',$cashiers);

		    $this->assign('member_no',$cashier['member_no']);

	    	$this->assign('data',$data);
		    //会员卡类型
		    $ctlist = MCT::select();
		    $this->assign('ctlist',$ctlist);
		    //卡可消费地区
		    $company = Company::select();
		    $companylist = [];
		    foreach ($company as $k => $v) {
		    	$companylist[$v['id']] = $v['full_name'];
		    }
		    $this->assign('companylist',$companylist);
		    //支付方式
		    $paywaylist = $this->getUsedData([6,10]);
		    //抵扣方式
		    $payway = [12,14,17];
		    $paywaylist2 = [];//抵扣方式
		    $paywaylist1 = [];//支付方式
		    foreach ($paywaylist as $k => $v) {
		    	if(in_array($v['id'], $payway)){
		    		if($v['id']==14){
		    			continue;
		    		}
		    		$paywaylist2[] =  $v;
		    	}else{
		    		$paywaylist1[] =  $v;
		    	}
		    }
		    $paywaylist1 = array_merge([['id'=>0,'name'=>'无']],$paywaylist1);
		    $paywaylist2 = array_merge([['id'=>0,'name'=>'无']],$paywaylist2);
		    $this->assign('paywaylist1',$paywaylist1);
		    $this->assign('paywaylist2',$paywaylist2);
		    //门店项目
		    $service = Service::where(array('company_id'=>Session::get('company_id'),'status'=>1))->select();
		    $service = array_merge([['id'=>0,'name'=>'无']],$service);
		    $this->assign('service',$service);
		    //服务方式
		    $servicetype = $this->getUsedData(64);
		    $this->assign('stype',$servicetype);

	    	return $this->fetch();
    	}
    }
    //重置订单修改次数
    public function resetAppOrderTimes(){
    	$data = input();
    	if(C::where(['id'=>$data['id']])->setField('is_confirm',0)){
    		$this->success('重置成功');
    	}else{
    		$this->error('重置失败');
    	}
    }
    //合并打印
    public function all_print(){
    	if(Request::instance()->isPost()){

    	}else{
    		$data = input();
	    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d'))+6*3600);
	    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):time();

	    	if(Session::get('role')==1){
	    		$id = isset($data['company'])?$data['company']:Session::get('company_id');
	    		$where = 'c.company_id ='.$id;
	    		$companylist = $this->getCompanyList(5,1);
	    	}else{
		    	$id = Session::get('company_id');
	    		$where = 'c.company_id ='.$id;
	    		$companylist = $this->getCompanyList(Session::get('company_id'),1);
	    	}
	    	if(isset($data['number'])&&$data['number']){
	    		$where .= ' and c.number like "%'.$data['number'].'%"';
	    	}else{
	    		$data['number'] = '';
	    	}

	    	$this->assign('data',['number'=>$data['number'],'start_time'=>date('Y-m-d H:i:s',$data['start_time']),'end_time'=>date('Y-m-d H:i:s',$data['end_time']),'company_id'=>$id]);

	    	$where .= ' and c.time>='.$data['start_time'].' and c.time<='.$data['end_time'].' and c.status<>2 and c.app_order_id=0 and order_type=1';

	    	$this->assign('companylist',$companylist);

	    	$list = C::field('c.*,m.name as ctname')->alias('c')->join('member m','c.member_id=m.id','left')->where($where)->order('c.time DESC')->limit(50)->select();

	    	$this->assign('list',$list);

	    	return $this->fetch();
    	}
    }
    //获取消费子订单
    public function getCashiers(){
    	$data = input();
    	$id = $data['id'];
    	$cashiers = CS::where(['parent_id'=>$id])->select();
    	if(empty($cashiers)){
			echo json_encode(['sta'=>0,'msg'=>'订单获取失败']);
    	}else{
    		echo json_encode(['sta'=>1,'msg'=>'订单获取成功','list'=>$cashiers]);
    	}
    }
    //获取充值子订单
    public function getCPWCashiers(){
    	$data = input();
    	$pay_typelist = $this->getUsedData(10);
    	$pay_type = [];
    	foreach ($pay_typelist as $k => $v) {
    		$pay_type[$v['id']] = $v['name'];
    	}
    	$id = $data['id'];
    	$cashiers = CPW::where(['cashier_id'=>$id])->select();
    	if(empty($cashiers)){
			echo json_encode(['sta'=>0,'msg'=>'订单获取失败']);
    	}else{
    		foreach ($cashiers as $k => &$v) {
    			$v['pay_type'] = $pay_type[$v['pay_type']]; 
    		}
    		echo json_encode(['sta'=>1,'msg'=>'订单获取成功','list'=>$cashiers]);
    	}
    }
    public function dayin(){
    	$data = input();
    	$ids = $data['cashier_id'];
    	$list = C::where('id','in',$ids)->select();
    	$cashier = [
    		'real_money' => 0,
    		'time' => 0,
    		'number' => '',
    		'member_id' => 0,
    	];
    	foreach ($list as $k => $v) {
    		$cashier['real_money'] += $v['real_money'];
    		$cashier['time'] = $v['time'];
    		$cashier['number'] .= $v['number'].'/';
    	}
    	$cashier['member_id'] = $list[0]['member_id'];
    	$this->assign('is_sanke',$list[0]['member_id']?0:1);
    	//会员卡
    	$member = MC::get($cashier['member_id']);
    	$this->assign('member',$member);
    	//钱包
    	$wallet = Wallet::where(['member_id'=>$cashier['member_id']])->find();
    	$this->assign('wallet',$wallet);
    	//订单
    	$cashiers = CS::where('parent_id','in',$ids)->select();
    	$this->assign('cashiers',$cashiers);

    	$this->assign('company',Company::get(Session::get('company_id')));

    	$used_data = $this->getUsedData('6,10');
    		$used_list = [];
    		foreach ($used_data as $k => $v) {
	    		$used_list[$v['id']] = $v['name'];
	    	}
	    	$cashiers_list = [];
	    	$yuanjia = 0;
	    	$payway = [];
    		foreach ($cashiers as $k => $v) {
    			$cashiers_list[$k] = [
    				'pay_id' => $v['pay'],
    				'pay_name' => $used_list[$v['pay']],
    				'discount' => $v['discount'], 
    				'service_name' => $v['service_name'],
    				'service_price' => $v['service_price'],
    				'count' => number_format($v['count'], 1, '.', ''),
    				'fworker' => $v['fworker'],
    				'discount_value' => $v['discount_value'],
    			];
    			//原价
    			$yuanjia+= $v['count']*$v['service_price'];

    			$f = Family::where(['number'=>$v['fworker']])->find();
    			$cashiers_list[$k]['fname'] = $f['name'];
    			$cashiers_list[$k]['sworker'] = '';
    			$cashiers_list[$k]['sname'] = '';
    			if($v['sworker']){
    				$s = Family::where(['number'=>$v['sworker']])->find();
    				$cashiers_list[$k]['sworker'] = $v['sworker'];
    				$cashiers_list[$k]['sname'] = $s['name'];
    			}
    			@$payway[$v['pay']]['discount']+=$v['discount'];
    			@$payway[$v['pay']]['name'] = $used_list[$v['pay']];
    		}
    		$this->assign('payway',$payway);
    		$this->assign('yuanjia',$yuanjia);
    		//省了多少钱

    		$cashier['dis_money'] = $yuanjia-$cashier['real_money'];
    		$this->assign('cashiers_list',$cashiers_list);
    		$this->assign('company',Company::get(Session::get('company_id')));

    		$this->assign('cashier',$cashier);
    		$this->assign('code',1);
    		$this->assign('msg','打印成功');
    		$this->assign('wait',3);
    		$this->assign('url','all_print');

    		return $this->fetch();
    }
    //充值单据合并打印
    public function print_rechange(){
    	$data = input();
	    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d'))+6*3600);
	    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):time();

	    	if(Session::get('role')==1){
	    		$id = isset($data['company'])?$data['company']:Session::get('company_id');
	    		$where = 'c.company_id ='.$id;
	    		$companylist = $this->getCompanyList(5,1);
	    	}else{
		    	$id = Session::get('company_id');
	    		$where = 'c.company_id ='.$id;
	    		$companylist = $this->getCompanyList(Session::get('company_id'),1);
	    	}
	    	if(isset($data['number'])&&$data['number']){
	    		$where .= ' and c.number like "%'.$data['number'].'%"';
	    	}else{
	    		$data['number'] = '';
	    	}

	    	$this->assign('data',['number'=>$data['number'],'start_time'=>date('Y-m-d H:i:s',$data['start_time']),'end_time'=>date('Y-m-d H:i:s',$data['end_time']),'company_id'=>$id]);

	    	$where .= ' and c.time>='.$data['start_time'].' and c.time<='.$data['end_time'].' and c.status<>2 and order_type!=1';

	    	$this->assign('companylist',$companylist);

	    	$list = C::field('c.*,m.name as ctname')->alias('c')->join('member m','c.member_id=m.id','left')->where($where)->order('c.time DESC')->limit(50)->select();
	    	$order_type = [
	    		2 => '充值',
	    		3 => '开卡',
	    		4 => '转卡',
	    		5 => '还款',
	    	];
	    	foreach ($list as $k => &$v) {
	    		$v['order_type'] = $order_type[$v['order_type']];
	    	}

	    	$this->assign('list',$list);

	    	return $this->fetch();
    }
    //合并打印充值小票
    public function rechange_dayin(){
    	$data = input();
    	$cashier_id = $data['cashier_id'];
    	if($cashier_id){
    		$cashier = C::get($cashier_id);
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
    		$this->assign('msg','打印成功');
    		$this->assign('code',1);
    		$this->assign('url','/Index/Cashier/print_rechange');
    		$this->assign('wait',3000);
    		return $this->fetch();
    	}
    }
    //支付方式明细表
    public function payway_statistics(){
    	$data = input();
    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time'])+6*3600:(strtotime(date('Y-m-d'))+6*3600);
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time'])+30*3600:time()+30*3600;
    	$data['type'] = isset($data['type'])?$data['type']:1;
    	$data['payway'] = isset($data['payway'])?$data['payway']:7;
    	$data['company'] = isset($data['company'])?$data['company']:1;

    	$this->assign('data',['start_time'=>date('Y-m-d',$data['start_time']),'end_time'=>date('Y-m-d',$data['end_time']-30*3600),'type'=>$data['type'],'payway'=>$data['payway'],'company'=>$data['company']]);

    	if(Session::get('role')==2){
    		$id = Session::get('company_id');
    		$companylist = $this->getCompanyList($id,1);
    	}else{
	    	$companylist = $this->getCompanyList(5,1); 
    	}
    	if($data['type']==1){
    		$where = '(c.time> '.$data['start_time'].' and c.time <'.$data['end_time'].') and cs.pay='.$data['payway'].' and c.status!=2 and c.is_confirm=1';
    		$data['start_time']=date('Y-m-d',$data['start_time']);
    		$data['end_time']=date('Y-m-d',$data['end_time']);
    		$list = CS::field('cs.*,c.time,c.number,c.member_no')->alias('cs')->join('cashier c','cs.parent_id=c.id','left')->where($where)->order('cs.id desc')->paginate(15,false,array('query'=>$data));
    	}else{
    		$where = '(c.time> '.$data['start_time'].' and c.time <'.$data['end_time'].') and cpw.pay_type='.$data['payway'].' and c.status!=2';
    		$data['start_time']=date('Y-m-d',$data['start_time']);
    		$data['end_time']=date('Y-m-d',$data['end_time']);
    		$list = CPW::field('cpw.*,c.time,c.number,c.member_no')->alias('cpw')->join('cashier c','cpw.cashier_id=c.id','left')->where($where)->order('cpw.id desc')->paginate(15,false,array('query'=>$data));
    		foreach ($list as $k => &$v) {
    			@$v['discount'] = $v['money'];
    			@$v['ftype'] = 65;
    			@$v['stype'] = 65;
    			@$v['fworker'] = '';
    			@$v['sworker'] = '';
    		}
    	}
    	//服务类型
    	$servicetype = $this->getUsedData(64);
    	foreach ($servicetype as $k => $v) {
    		$stlist[$v['id']] = $v['name'];
    	}
    	foreach ($list as $k => &$v) {
    		$v['ftype'] = $stlist[$v['ftype']];
    		$v['stype'] = $stlist[$v['stype']];
    	}
    	$this->assign('list',$list);
    	//支付方式
    	$paywaylist = $this->getUsedData('6,10');
    	$this->assign('paywaylist',$paywaylist);
    	//门店
    	$this->assign('companylist',$companylist); 
    	return $this->fetch();
    }
}
