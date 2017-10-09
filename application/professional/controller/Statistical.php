<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
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
use app\index\model\Expenditure;
use \think\Session;

use think\Db;
//数据统计类
class Statistical extends Base
{
	public $rule = [
	        ];

	//客户消费明细主页
    public function index()
    {
    	$data = input();
    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time'])+6*3600:(strtotime(date('Y-m-d'))+6*3600);
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time'])+30*3600:time()+30*3600;
    	$data['number'] = isset($data['number'])&&$data['number']?trim($data['number']):'';
    	$data['fnumber'] = isset($data['fnumber'])&&$data['fnumber']?trim($data['fnumber']):'';
    	$data['card_no'] = isset($data['card_no'])&&$data['card_no']?trim($data['card_no']):'';
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

    	$where .= ' and c.time>='.$data['start_time'].' and c.time<='.$data['end_time'].' and c.status<>2';
    	if($data['number']){
    		$where .= ' and c.number like "%'.$data['number'].'%"';
    	}
    	if($data['fnumber']){
    		$where .= ' and cs.fworker like "%'.$data['fnumber'].'%"';
    	}
    	if($data['card_no']){
    		$where .= ' and c.member_no like "%'.$data['card_no'].'%"';
    	}
    	$this->assign('data',['start_time'=>date('Y-m-d',$data['start_time']),'end_time'=>date('Y-m-d',$data['end_time']-30*3600),'company_id'=>$id,'number'=>$data['number'],'card_no'=>$data['card_no'],'fnumber'=>$data['fnumber']]);

    	$this->assign('companylist',$companylist);

    	    	//
    	$field = [
    		'c.*',
    		'cs.*',
    		'm.name',
    		'mct.name as mctname',
    		'm.mobile_phone',
    		'ud.name as pay_name',
    		'f.name as fname',
    	];
    	$list = CS::field($field)->alias('cs')->join('cashier as c','cs.parent_id=c.id','left')->join('member m','c.member_id=m.id','left')->join('member_card_type mct','m.card_type=mct.id','left')->join('used_data ud','cs.pay=ud.id','left')->join('family f','cs.fworker=f.number','left')->where($where)->order('c.time desc')->limit(100)->select();
    	$this->assign('list',$list);

    	//服务类型
    	$ftype = $this->getUsedData(64);
    	$typelist = [];
    	foreach ($ftype as $k => $v) {
    		$typelist[$v['id']] = $v['name'];
    	}
    	$this->assign('typelist',$typelist);
    	return $this->fetch();
    }
    //门店营业汇总主页
    public function business_summary_index(){
    	$data = input();
    	if(Session::get('role')==1){
    		$id = isset($data['company'])?$data['company']:Session::get('company_id');
    		$id = $this->getChildCompany($id);
    		$companylist = $this->getCompanyList(5,1);
    		$clist = Company::where(['status'=>1,'level'=>2])->select();
    	}else{
	    	$id = Session::get('company_id');
    		$companylist = $this->getCompanyList($id,1);
    		$clist = Company::where(['id'=>$id])->select();
    	}
    	$this->assign('companylist',$companylist);

    	$data['start_time'] = (isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time'])+6*3600:(strtotime(date('Y-m-d')))+(6*3600));
    	//管理员账号可以查看最近半年的数据，普通账号只能查看最近两个月的数据
    	if(Session::get('role')==1){
    		$last_half_year = strtotime(date('Y-m-01', strtotime('-6 month')))+(6*3600);
    		$data['start_time'] = $last_half_year>$data['start_time']?$last_half_year:$data['start_time'];
    	}else{
    		$last_month = strtotime(date('Y-m-01', strtotime('-1 month')))+(6*3600);
    		$data['start_time'] = $last_month>$data['start_time']?$last_month:$data['start_time'];
    	}
    	
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):strtotime(date('Y/m/d'));
    	$data['end_time'] += (30*3600);

    	$this->assign('data',['start_time'=>date('Y/m/d',$data['start_time']),'end_time'=>date('Y/m/d',$data['end_time']-24*3600),'company_id'=>$id]);
    	
    	//合计
    	$total = [];

    	foreach ($clist as $k => $v) {
    		$clist[$k] = array_merge($this->dataAggregation(['id'=>$v['id'],'start_time'=>$data['start_time'],'end_time'=>$data['end_time']]),['id'=>$clist[$k]['id'],'full_name'=>$clist[$k]['full_name']]);
    		
    		//合计
    		@$total["cash_consumption"] += $clist[$k]["cash_consumption"];
			@$total["cash_top_up"] += $clist[$k]["cash_top_up"];
			@$total["cash"] += $clist[$k]["cash"];
			@$total["tuangou_consumption"] += $clist[$k]["tuangou_consumption"];
			@$total["ycard_consumption"] += $clist[$k]["ycard_consumption"];
			@$total["ycard_top_up"] += $clist[$k]["ycard_top_up"];
			@$total["ycard"] += $clist[$k]["ycard"];
			@$total["vouchers_consumption"] += $clist[$k]["vouchers_consumption"];
			@$total["spending"] += $clist[$k]["spending"];
			@$total["total_money"] += $clist[$k]["total_money"];
			@$total["real_income"] += $clist[$k]["real_income"];
			@$total["cash_now"] += $clist[$k]["cash_now"];
			@$total["wallet_consumption"] += $clist[$k]["wallet_consumption"];
			@$total["ewallet_consumption"] += $clist[$k]["ewallet_consumption"];
			@$total["old_wallet_consumption"] += $clist[$k]["old_wallet_consumption"];
			@$total["all_consumption"] += $clist[$k]["all_consumption"];
			@$total["arrears_consumption"] += $clist[$k]["arrears_consumption"];
			@$total["arrears"] += $clist[$k]["arrears"];
			@$total["total_consumption"] += $clist[$k]["total_consumption"];
			@$total["wallet_labor_performance"] += $clist[$k]["wallet_labor_performance"];
    	}
		$total["id"] = 0;
		$total["full_name"] = '合计';
		$clist[] = $total;

    	$this->assign('list',$clist);

    	return $this->fetch();
    }
    //门店营业汇总详细页
    public function business_summary(){
    	$data = input();
    	if(Session::get('role')==1){
    		$id = isset($data['company'])?$data['company']:Session::get('company_id');
    		$id = $this->getChildCompany($id);
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		$companylist = $this->getCompanyList($id,1);
    	}
    	$this->assign('companylist',$companylist);

    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time'])+6*3600:(strtotime(date('Y-m-d')))+6*3600;
    	//管理员账号可以查看最近半年的数据，普通账号只能查看最近两个月的数据
    	if(Session::get('role')==1){
    		$last_half_year = strtotime(date('Y-m-01', strtotime('-6 month')));
    		$data['start_time'] = $last_half_year>$data['start_time']?$last_half_year:$data['start_time'];
    	}else{
    		$last_month = strtotime(date('Y-m-01', strtotime('-1 month')));
    		$data['start_time'] = $last_month>$data['start_time']?$last_month:$data['start_time'];
    	}

    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):strtotime(date('Y/m/d'));
    	$data['end_time'] += (30*3600);

    	$time_difference = ($data['end_time']-$data['start_time']-24*3600)/(3600*24);
    	$this->assign('data',['start_time'=>date('Y/m/d',$data['start_time']),'end_time'=>date('Y/m/d',$data['end_time']-24*3600),'company_id'=>$id]);
    	for ($i=0; $i <= $time_difference; $i++) {
    		$list[$i] = $this->dataAggregation(['id'=>$id,'start_time'=>$data['start_time']+$i*(3600*24),'end_time'=>$data['start_time']+($i+1)*(3600*24)]);
    		$list[$i]['date'] = date('Y/m/d',$data['start_time']+$i*24*3600);
    		//合计
    		@$total["cash_consumption"] += $list[$i]["cash_consumption"];
			@$total["cash_top_up"] += $list[$i]["cash_top_up"];
			@$total["cash"] += $list[$i]["cash"];
			@$total["tuangou_consumption"] += $clist[$i]["tuangou_consumption"];
			@$total["ycard_consumption"] += $list[$i]["ycard_consumption"];
			@$total["ycard_top_up"] += $list[$i]["ycard_top_up"];
			@$total["ycard"] += $list[$i]["ycard"];
			@$total["vouchers_consumption"] += $list[$i]["vouchers_consumption"];
			@$total["spending"] += $list[$i]["spending"];
			@$total["total_money"] += $list[$i]["total_money"];
			@$total["real_income"] += $list[$i]["real_income"];
			@$total["cash_now"] += $list[$i]["cash_now"];
			@$total["wallet_consumption"] += $list[$i]["wallet_consumption"];
			@$total["ewallet_consumption"] += $list[$i]["ewallet_consumption"];
			@$total["old_wallet_consumption"] += $list[$i]["old_wallet_consumption"];
			@$total["all_consumption"] += $list[$i]["all_consumption"];
			@$total["arrears_consumption"] += $list[$i]["arrears_consumption"];
			@$total["arrears"] += $list[$i]["arrears"];
			@$total["total_consumption"] += $list[$i]["total_consumption"];
			@$total["wallet_labor_performance"] += $list[$i]["wallet_labor_performance"];
    	}
    	//$list[] = $this->dataAggregation(['id'=>$id]);
    	$total["id"] = 0;
		$total["date"] = '合计';
		$list[] = $total;

    	$this->assign('list',$list);

    	return $this->fetch();
    }
    //员工业绩统计表
    public function business_results(){
    	$data = input();
    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d'))+6*3600);
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):time();

    	if(Session::get('role')==1){
    		$id = isset($data['company'])?$data['company']:Session::get('company_id');
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		/*if(isset($data['company'])){
    			$clist = $this->getChildCompany($id);
    			$where = ' and c.company_id in('.$clist.')';
    		}else{
	    		$where = ' and c.company_id in('.$id.')';
    		}*/
    		$companylist = $this->getCompanyList($id,1);
    	}
    	$where = ' c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].' and c.app_order_id=0';
    	$this->assign('data',['start_time'=>date('Y-m-d H:i:s',$data['start_time']),'end_time'=>date('Y-m-d H:i:s',$data['end_time']),'company_id'=>$id]);

    	$family = Family::where(['company'=>$id])->select();
    	$list = [];
    	foreach ($family as $k => $v) {
    		$list[$k]['name'] = $v['name'];
    		$list[$k]['number'] = $v['number'];
    		//卡金业绩
    		$where_kazin_performance = 'cps.pay_type not in (12,14,17) and c.status!=2 and f.id='.$v['id'].' and '.$where;
    		$kazin_performance = CPW::alias('cps')->field('FLOOR(sum(cps.share_results)) as total')->join('cashier c','cps.cashier_id=c.id','left')->join('family f','cps.family_no=f.number')->where($where_kazin_performance)->find();
			$list[$k]['kazin_performance'] = $kazin_performance['total'];
    		//劳动业绩
			//只有该员工的项目业绩
    		$where_y = 'cs.pay not in (12,14,17) and c.status!=2 and '.$where.' and (cs.fworker='.$v['number'].' and cs.sworker="") or (cs.sworker='.$v['number'].' and cs.fworker="")';
    		$dz_y = CS::alias('cs')->field('FLOOR(sum(cs.total)) as total')->join('cashier c','cs.parent_id=c.id','left')->where($where_y)->find();
    		//两人一起服务的项目业绩
    		$where_d = 'cs.pay not in (12,14,17) and c.status!=2 and '.$where.' and (cs.fworker='.$v['number'].' and cs.sworker>0) or (cs.sworker='.$v['number'].' and cs.fworker>0)';
    		$dz_d = CS::alias('cs')->field('FLOOR(sum(cs.total)) as total')->join('cashier c','cs.parent_id=c.id','left')->where($where_d)->find();
    		//
    		$list[$k]['labor_performance'] = $dz_y['total']+round($dz_d['total']/2);

    		//点钟
    		$where_dz_f = 'cs.pay not in (12,14,17) and c.status!=2 and cs.ftype=65 and cs.fworker='.$v['number'].' and '.$where;
    		$dz_f = CS::alias('cs')->field('count(*) as count')->join('cashier c','cs.parent_id=c.id','left')->where($where_dz_f)->find();
    		$where_dz_s = 'cs.pay not in (12,14,17) and c.status!=2 and cs.stype=65 and cs.sworker='.$v['number'].' and '.$where;
    		$dz_s = CS::alias('cs')->field('count(*) as count')->join('cashier c','cs.parent_id=c.id','left')->where($where_dz_s)->find();
    		$list[$k]['dz'] = $dz_f['count']+$dz_s['count'];
    		//轮牌
    		$where_lp_f = 'cs.pay not in (12,14,17) and c.status!=2 and cs.ftype=66 and cs.fworker='.$v['number'].' and '.$where;
    		$lp_f = CS::alias('cs')->field('count(*) as count')->join('cashier c','cs.parent_id=c.id','left')->where($where_lp_f)->find();
    		$where_lp_s = 'cs.pay not in (12,14,17) and c.status!=2 and cs.stype=66 and cs.sworker='.$v['number'].' and '.$where;
    		$lp_s = CS::alias('cs')->field('count(*) as count')->join('cashier c','cs.parent_id=c.id','left')->where($where_lp_s)->find();
    		$list[$k]['lp'] = $lp_f['count']+$lp_s['count'];
    	}

    	$this->assign('companylist',$companylist);
    	$this->assign('list',$list);
    	return $this->fetch();
    }
    //某员工劳动业绩
    public function labor_performance($member=0){
    	$order_type = [
    		1 => '消费',
    		2 => '充值',
    		3 => '开卡',
    		4 => '转卡',
    		5 => '还款',
    	];
    	$pay_type = $this->getUsedData('6,10');
    	foreach ($pay_type as $k => $v) {
    		$pay_way[$v['id']] = $v['name'];
    	}
    	if($member){
    		$member_data = Family::where(['number'=>$member])->find();
    		$this->assign('member',$member_data);
    		$data = input();
	    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d'))+6*3600);
	    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):time();
    		$cashier = CS::alias('cs')->field('cs.services_name,cs.pay,cs.discount,c.order_type,cs.fworker,cs.sworker,c.time,c.number,c.member_no')->join('cashier c','cs.parent_id=c.id','left')->where(' c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].' and c.is_confirm=1 and cs.fworker='.$member.' or cs.sworker='.$member)->paginate(15,false,array('query'=>$data)); 
    		foreach ($cashier as $k => &$v) {
    			$cashier[$k]['order_type_name'] = $order_type[$cashier[$k]['order_type']];
    			$cashier[$k]['date'] = date('Y-m-d H:i:s',$cashier[$k]['time']);
    			$cashier[$k]['pay_way'] = $pay_way[$cashier[$k]['pay']];
    			if($cashier[$k]['sworker']){
    				$cashier[$k]['discount_s'] = round($cashier[$k]['discount']/2);
    			}else{
    				$cashier[$k]['discount_s'] = $cashier[$k]['discount'];
    			}
    		}
    		$this->assign('list',$cashier);
    		return $this->fetch();
    	}
    }
    //某员工卡金业绩
    public function kazin_performance($member=0){
    	$order_type = [
    		1 => '消费',
    		2 => '充值',
    		3 => '开卡',
    		4 => '转卡',
    		5 => '还款',
    	];
    	$pay_type = $this->getUsedData('6,10');
    	foreach ($pay_type as $k => $v) {
    		$pay_way[$v['id']] = $v['name'];
    	}
    	if($member){
    		$member_data = Family::where(['number'=>$member])->find();
    		$this->assign('member',$member_data);
    		$data = input();
	    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d'))+6*3600);
	    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):time();
    		//$cashier = CS::alias('cs')->field('cs.services_name,cs.pay,cs.discount,c.order_type,cs.fworker,cs.sworker,c.time,c.number,c.member_no')->join('cashier c','cs.parent_id=c.id','left')->where(' c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].' and cs.fworker='.$member.' or cs.sworker='.$member)->paginate(15,false,array('query'=>$data)); 
    		$cashier = CPW::alias('cpw')->field('cpw.pay_type,cpw.money,cpw.share_results,c.order_type,cpw.family_no,c.time,c.number,c.member_no')->join('cashier c','cpw.cashier_id=c.id','left')->where(' c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].' and c.is_confirm=1 and cpw.family_no='.$member)->paginate(15,false,array('query'=>$data)); 
    		foreach ($cashier as $k => &$v) {
    			$cashier[$k]['order_type_name'] = $order_type[$cashier[$k]['order_type']];
    			$cashier[$k]['date'] = date('Y-m-d H:i:s',$cashier[$k]['time']);
    			$cashier[$k]['pay_way'] = $pay_way[$cashier[$k]['pay_type']];
    		}
    		$this->assign('list',$cashier);
    		return $this->fetch();
    	}
    }
    //会员卡异动统计表
    public function card_move(){
    	$data = input();
    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d'))+6*3600);
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):time();

    	if(Session::get('role')==1){
    		$id = isset($data['company'])?$data['company']:Session::get('company_id');
    		$companylist = $this->getCompanyList(5,1);
    	}else{
	    	$id = Session::get('company_id');
    		/*if(isset($data['company'])){
    			$clist = $this->getChildCompany($id);
    			$where = ' and c.company_id in('.$clist.')';
    		}else{
	    		$where = ' and c.company_id in('.$id.')';
    		}*/
    		$companylist = $this->getCompanyList($id,1);
    	}
    	$this->assign('companylist',$companylist);
    	$this->assign('data',['start_time'=>date('Y-m-d H:i:s',$data['start_time']),'end_time'=>date('Y-m-d H:i:s',$data['end_time']),'company_id'=>$id]);
    	if(Request::instance()->isPost()){
	    	//获取本店的卡类型
	    	$CTC = CardTypeCompany::alias('ctc')->field('mct.*')->join('member_card_type mct','ctc.card_type_id=mct.id')->where(['ctc.company_id'=>$id])->select();
	    	foreach ($CTC as $k => $v) {
	    		//卡类型开卡数，充值应总额，充值实收总额
	    		$where = 'c.order_type=3 and m.card_type='.$v['id'].' and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].' and c.company_id='.$id;
	    		$tmp = C::alias('c')->field('count(*) as cou,sum(real_money) as real_m,sum(should_money) as should')->join('member m','c.member_id=m.id','left')->where($where)->find();
	    		$CTC[$k]['open_cou'] = $tmp['cou'];
	    		$CTC[$k]['open_real_m'] = $tmp['real_m'];
	    		$CTC[$k]['open_should'] = $tmp['should'];

	    		//卡类型充值数（充值、换卡、还款），充值应总额，充值实收总额
	    		$where = 'c.order_type in(2,4,5) and m.card_type='.$v['id'].' and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].' and c.company_id='.$id;
	    		$tmp = C::alias('c')->field('count(*) as cou,sum(real_money) as real_m,sum(should_money) as should')->join('member m','c.member_id=m.id','left')->where($where)->find();
	    		$CTC[$k]['rechange_cou'] = $tmp['cou'];
	    		$CTC[$k]['rechange_real_m'] = $tmp['real_m'];
	    		$CTC[$k]['rechange_should'] = $tmp['should'];

	    		//卡类型余额
	    		$tmp = MC::alias('m')->field('sum(w.cash) as allcash')->join('wallet w','m.id=w.member_id','left')->where(['m.card_type'=>$v['id'],'m.status'=>1,'m.company_id'=>$id])->find();
	    		$CTC[$k]['allcash'] = round($tmp['allcash']);
	    	}
	    }else{
	    	$CTC = [];
	    }
    	$this->assign('ctc',$CTC);

    	return $this->fetch();
    }
    //会员卡异动统计表详细
    public function card_move_detail(){
    	$order_type = [
    		1 => '消费',
    		2 => '充值',
    		3 => '开卡',
    		4 => '转卡',
    		5 => '还款',
    	];
    	$data = input();
    	$data['start_time'] = isset($data['start_time'])&&$data['start_time']?strtotime($data['start_time']):(strtotime(date('Y-m-d'))+6*3600);
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):time();
    	if(!isset($data['card_type'])){
    		return false;
    	}
    	//开卡
    	$where = 'c.order_type!=1 and m.card_type='.$data['card_type'].' and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'];
    	$list = C::alias('c')->field('c.*,m.*')->join('member m','c.member_id=m.id','left')->where($where)->paginate(15,false,array('query'=>$data)); 
    	foreach ($list as $k => $v) {
    		$list[$k]['date'] = date('y/m/d H:i:s',$v['time']);
    		$list[$k]['order_type_name'] = $order_type[$v['order_type']];
    	}
    	$this->assign('list',$list);
    	return $this->fetch();
    }

    //获取营业统计(当日日记小单)
    public function dataAggregation($data){
    	//$data = input();
    	$id = isset($data['id'])?$data['id']:Session::get('company_id');
    	$where = ' and c.company_id in ('.$id.') and c.is_confirm=1';
    	if(!isset($data['start_time'])){
    		return false;
    	}
    	$data['start_time'] = $data['start_time']+6*3600;
    	$data['end_time'] = $data['end_time']+6*3600;
    	//$this->assign('data',['start_time'=>date('Y-m-d H:i:s',$data['start_time']),'end_time'=>date('Y-m-d H:i:s',$data['end_time']),'company_id'=>$id]);
    	//营业额类

    	//充值现金

    	$where_cash_consumption = 'cps.pay_type=11 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;

    	$cash_consumption = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_cash_consumption)->find();
    	
    	$cash_consumption['cpsmoney'] = $cash_consumption['cpsmoney']?$cash_consumption['cpsmoney']:0;
    	$re['cash_consumption'] = $cash_consumption['cpsmoney'];
    	//消费现金
    	$where_cash_top_up = 'cs.pay=11 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$cash_top_up = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_cash_top_up)->find();
    	$cash_top_up['csdiscount'] = $cash_top_up['csdiscount']?$cash_top_up['csdiscount']:0;
    	$re['cash_top_up'] = $cash_top_up['csdiscount'];
    	//现金
    	//$this->assign('cash',$cash_top_up['csdiscount']+$cash_consumption['cpsmoney']);
    	$re['cash'] = $cash_top_up['csdiscount']+$cash_consumption['cpsmoney'];
    	//欠款金额


    	//消费团购
    	$where_tuangou_consumption = 'cps.pay=13 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$tuangou_consumption = CS::alias('cps')->field('FLOOR(sum(cps.discount)) as cpsmoney')->join('cashier c','cps.parent_id=c.id','left')->where($where_tuangou_consumption)->find();
    	$tuangou_consumption['cpsmoney'] = $tuangou_consumption['cpsmoney']?$tuangou_consumption['cpsmoney']:0;
    	//$this->assign('tuangou_consumption',$tuangou_consumption);
    	$re['tuangou_consumption'] = $tuangou_consumption['cpsmoney'];
    	//消费银行卡
    	$where_ycard_consumption = 'cps.pay=15 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$ycard_consumption = CS::alias('cps')->field('FLOOR(sum(cps.discount)) as cpsmoney')->join('cashier c','cps.parent_id=c.id','left')->where($where_ycard_consumption)->find();
    	$ycard_consumption['cpsmoney'] = $ycard_consumption['cpsmoney']?$ycard_consumption['cpsmoney']:0;
    	//$this->assign('ycard_consumption',$ycard_consumption);
    	$re['ycard_consumption'] = $ycard_consumption['cpsmoney'];
    	//银行卡卡异动（充值）
    	$where_ycard_top_up = 'cps.pay_type=15 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$ycard_top_up = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_ycard_top_up)->find();
    	$ycard_top_up['cpsmoney'] = $ycard_top_up['cpsmoney']?$ycard_top_up['cpsmoney']:0;
    	//$this->assign('ycard_top_up',$ycard_top_up);
    	$re['ycard_top_up'] = $ycard_top_up['cpsmoney'];
    	//银行卡合计
    	//$this->assign('ycard',$ycard_top_up['cpsmoney']+$ycard_consumption['cpsmoney']);
    	$re['ycard'] = $ycard_top_up['cpsmoney']+$ycard_consumption['cpsmoney'];

    	//消费抵用券
    	$where_vouchers_consumption = 'cps.deductible_pay=17 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$vouchers_consumption = CS::alias('cps')->field('FLOOR(sum(cps.deductible)) as cpsmoney')->join('cashier c','cps.parent_id=c.id','left')->where($where_vouchers_consumption)->find();
    	$vouchers_consumption['cpsmoney'] = $vouchers_consumption['cpsmoney']?$vouchers_consumption['cpsmoney']:0;
    	//$this->assign('vouchers_consumption',$vouchers_consumption);
    	$re['vouchers_consumption'] = $vouchers_consumption['cpsmoney'];

    	//门店支出
    	$where_spending = '';
    	$spending = Expenditure::alias('c')->field('FLOOR(sum(c.total)) as stotal')->where('c.time>='.$data['start_time'].' and c.time<='.$data['end_time'].' and c.company_id in ('.$id.')')->find();
    	$spending['stotal'] = $spending['stotal']?$spending['stotal']:0;
    	//$this->assign('spending',$spending);
    	$re['spending'] = $spending['stotal'];
    	//总营业额
    	$total_money = $cash_top_up['csdiscount']+$cash_consumption['cpsmoney']+$tuangou_consumption['cpsmoney']+$ycard_consumption['cpsmoney']+$ycard_top_up['cpsmoney'];
    	//$this->assign('total_money',$total_money);
    	$re['total_money'] = $total_money;
    	//实际收入
    	$re['real_income'] = $total_money-$spending['stotal'];
    	//现存现金
    	$cash_now = $cash_top_up['csdiscount']+$cash_consumption['cpsmoney']-$spending['stotal'];
    	//$this->assign('cash_now',$cash_now);
    	$re['cash_now'] = $cash_now;

    	//销卡类
    	//储值账户
    	$where_wallet_consumption = 'cs.pay=7 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$wallet_consumption = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_wallet_consumption)->find();
    	$wallet_consumption['csdiscount'] = $wallet_consumption['csdiscount']?$wallet_consumption['csdiscount']:0;
    	//$this->assign('wallet_consumption',$wallet_consumption);
    	$re['wallet_consumption'] = $wallet_consumption['csdiscount'];
    	//电子钱包
    	$where_ewallet_consumption = 'cs.pay=8 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$ewallet_consumption = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_ewallet_consumption)->find();
    	$ewallet_consumption['csdiscount'] = $ewallet_consumption['csdiscount']?$ewallet_consumption['csdiscount']:0;
    	//$this->assign('ewallet_consumption',$ewallet_consumption);
    	$re['ewallet_consumption'] = $ewallet_consumption['csdiscount'];
    	//老疗程系统
    	$where_old_wallet_consumption = 'cs.pay=9 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$old_wallet_consumption = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_old_wallet_consumption)->find();
    	$old_wallet_consumption['csdiscount'] = $old_wallet_consumption['csdiscount']?$old_wallet_consumption['csdiscount']:0;
    	//$this->assign('old_wallet_consumption',$old_wallet_consumption);
    	$re['old_wallet_consumption'] = $old_wallet_consumption['csdiscount'];
    	//卡异动合计
    	$where_all_top_up = 'cps.pay_type not in(17,12,14) and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$all_consumption = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_all_top_up)->find();
    	$all_consumption['cpsmoney'] = $all_consumption['cpsmoney']?$all_consumption['cpsmoney']:0;
    	//$this->assign('all_consumption',$all_consumption);
    	$re['all_consumption'] = $all_consumption['cpsmoney'];

    	//经理签单
    	//消费经理签单
    	$where_arrears_consumption = 'cs.deductible_pay=12 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$arrears_consumption = CS::alias('cs')->field('FLOOR(sum(cs.deductible)) as csdeductible')->join('cashier c','cs.parent_id=c.id','left')->where($where_arrears_consumption)->find();
    	$arrears_consumption['csdeductible'] = $arrears_consumption['csdeductible']?$arrears_consumption['csdeductible']:0;
    	$re['arrears_consumption'] = $arrears_consumption['csdeductible'];
    	//充值经理签单
    	$where_arrears_top_up = 'cps.pay_type=12 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$arrears_top_up = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_arrears_top_up)->find();
    	$arrears_top_up['cpsmoney'] = $arrears_top_up['cpsmoney']?$arrears_top_up['cpsmoney']:0;
    	$arrears = $arrears_consumption['csdeductible']+$arrears_top_up['cpsmoney'];
    	//$this->assign('arrears',$arrears);
    	$re['arrears'] = $arrears['csdeductible'];
    	//销卡总额
    	$total_consumption = $old_wallet_consumption['csdiscount']+$wallet_consumption['csdiscount'];
    	//$this->assign("total_consumption",$total_consumption);
    	$re['total_consumption'] = $total_consumption;
    	//劳动业绩
    	$where_labor_performance = 'cs.pay!=8 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$wallet_labor_performance = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_labor_performance)->find();
    	$wallet_labor_performance = $wallet_labor_performance['csdiscount']?$wallet_labor_performance['csdiscount']:0;
    	//$this->assign('wallet_labor_performance',$wallet_labor_performance);
    	$re['wallet_labor_performance'] = $wallet_labor_performance;

    	return $re;
    }
}
																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																																						