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
use app\index\model\Cashier as C;
use app\index\model\Cashiers as CS;
use app\index\model\AppConsumptionLog as ACL;
use app\index\model\Company;
use app\index\model\ConsumptionLog;
use app\index\model\CashierPayWay as CPW;
use app\index\model\Expenditure;
use think\Request;

class Statistical extends Base
{
	public $params;
	public $star = [3=>53,4=>54,1=>55,2=>56,0=>0];
    public $teachnician = [1=>27,2=>28,3=>29];

	public function _initialize()
    {
        
    }
    public function returnJoin(){

    }
    //统计类接口入口
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
    //门店营业汇总
    protected function businessSummary(){
    	$data = input();
    	$data['company_id'] = isset($data['company_id'])?$data['company_id']:5;
    	$id = $this->getChildCompany($data['company_id']);
    	if($data['company_id']==6||$data['company_id']==14){
    		$id.=','.$data['company_id'];
    	}
    	$clist = Company::where('status=1 and id in ('.$id.')')->order('level')->select();
    	$todaystr = date('Y-m-d',time()-6*3600);
    	$data['date'] = isset($data['date'])?$data['date']:'today';

    	switch ($data['date']) {
    		case 'today':
    			$data['start_time'] = strtotime($todaystr)+(6*3600);
    			$data['end_time'] = time();
    			break;
    		case 'yesterday':
    			$data['start_time'] = strtotime($todaystr)+(6*3600)-24*3600;
    			$data['end_time'] = strtotime($todaystr)+(6*3600);
    			break;
    		case 'this_month':
    			$data['start_time'] = strtotime(date('Y-m-01', time()))+(6*3600);
    			$data['end_time'] = strtotime($todaystr)+(6*3600);
    			break;
    		case 'last_month':
    			$data['start_time'] = strtotime(date('Y-m-01', strtotime('-1 month')))+(6*3600);
    			$data['end_time'] = strtotime('-1 day',strtotime(date('Y-m-01',time()))+(6*3600));
    			break;
    		default:
    			return ['resp'=>1,'msg'=>'参数传入错误'];
    			break;
    	}
    	//管理员账号可以查看最近半年的数据，普通账号只能查看最近两个月的数据
    	/*
    	if(Session::get('role')==1){
    		$last_half_year = strtotime(date('Y-m-01', strtotime('-6 month')))+(6*3600);
    		$data['start_time'] = $last_half_year>$data['start_time']?$last_half_year:$data['start_time'];
    	}else{
    		$last_month = strtotime(date('Y-m-01', strtotime('-1 month')))+(6*3600);
    		$data['start_time'] = $last_month>$data['start_time']?$last_month:$data['start_time'];
    	}
    	
    	$data['end_time'] = isset($data['end_time'])&&$data['end_time']?strtotime($data['end_time']):strtotime(date('Y/m/d'));
    	$data['end_time'] += (30*3600);
    	*/

    	
    	//合计
    	$total = [];

    	foreach ($clist as $k => $v) {
    		$clist[$k] = array_merge($this->dataAggregation(['id'=>$v['id'],'start_time'=>$data['start_time'],'end_time'=>$data['end_time']]),['id'=>$clist[$k]['id'],'full_name'=>$clist[$k]['full_name']]);
    		
    		//合计
    		/*@$total["cash_consumption"] += $clist[$k]["cash_consumption"];
			@$total["cash_top_up"] += $clist[$k]["cash_top_up"];
			@$total["cash"] += $clist[$k]["cash"];
			@$total["tuangou_consumption"] += $clist[$k]["tuangou_consumption"];
			@$total["ycard_consumption"] += $clist[$k]["ycard_consumption"];
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
			*/
    	}
		/*$total["id"] = 0;
		$total["full_name"] = '合计';
		$clist[] = $total;*/
		return ['resp'=>0,'list'=>$clist];
    }
    //获取营业统计(当日日记小单)
    public function dataAggregation($data){
    	//$data = input();
    	$id = isset($data['id'])?$data['id']:5;
    	$id = $this->getChildCompany($id);
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
    	/*
    	$where_vouchers_consumption = 'cps.deductible_pay=17 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$vouchers_consumption = CS::alias('cps')->field('FLOOR(sum(cps.deductible)) as cpsmoney')->join('cashier c','cps.parent_id=c.id','left')->where($where_vouchers_consumption)->find();
    	$vouchers_consumption['cpsmoney'] = $vouchers_consumption['cpsmoney']?$vouchers_consumption['cpsmoney']:0;
    	//$this->assign('vouchers_consumption',$vouchers_consumption);
    	$re['vouchers_consumption'] = $vouchers_consumption['cpsmoney'];
    	*/

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
    	/*
    	$where_ewallet_consumption = 'cs.pay=8 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$ewallet_consumption = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_ewallet_consumption)->find();
    	$ewallet_consumption['csdiscount'] = $ewallet_consumption['csdiscount']?$ewallet_consumption['csdiscount']:0;
    	//$this->assign('ewallet_consumption',$ewallet_consumption);
    	$re['ewallet_consumption'] = $ewallet_consumption['csdiscount'];
    	*/
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
    	/*
    	$where_arrears_consumption = 'cs.deductible_pay=12 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$arrears_consumption = CS::alias('cs')->field('FLOOR(sum(cs.deductible)) as csdeductible')->join('cashier c','cs.parent_id=c.id','left')->where($where_arrears_consumption)->find();
    	$arrears_consumption['csdeductible'] = $arrears_consumption['csdeductible']?$arrears_consumption['csdeductible']:0;
    	$re['arrears_consumption'] = $arrears_consumption['csdeductible'];
    	*/
    	//充值经理签单
    	/*
    	$where_arrears_top_up = 'cps.pay_type=12 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$arrears_top_up = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_arrears_top_up)->find();
    	$arrears_top_up['cpsmoney'] = $arrears_top_up['cpsmoney']?$arrears_top_up['cpsmoney']:0;
    	$arrears = $arrears_consumption['csdeductible']+$arrears_top_up['cpsmoney'];
    	//$this->assign('arrears',$arrears);
    	$re['arrears'] = $arrears['csdeductible'];
    	*/
    	//销卡总额
    	$total_consumption = $old_wallet_consumption['csdiscount']+$wallet_consumption['csdiscount'];
    	//$this->assign("total_consumption",$total_consumption);
    	$re['total_consumption'] = $total_consumption;
    	//劳动业绩
    	/*
    	$where_labor_performance = 'cs.pay!=8 and c.status!=2 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$wallet_labor_performance = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_labor_performance)->find();
    	$wallet_labor_performance = $wallet_labor_performance['csdiscount']?$wallet_labor_performance['csdiscount']:0;
    	//$this->assign('wallet_labor_performance',$wallet_labor_performance);
    	$re['wallet_labor_performance'] = $wallet_labor_performance;
    	*/
    	//散客消费(除开储值账户，电子钱包，疗程账户的支付方式)
    	$where_sanke_consumption = ' c.status!=2 and c.member_id=0 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$sanke_consumption = CS::alias('cs')->field('FLOOR(sum(cs.discount)) as csdiscount')->join('cashier c','cs.parent_id=c.id','left')->where($where_sanke_consumption)->find();
    	$sanke_consumption['csdiscount'] = $sanke_consumption['csdiscount']?$sanke_consumption['csdiscount']:0;
    	//$this->assign('wallet_consumption',$wallet_consumption);
    	$re['sanke_consumption'] = $sanke_consumption['csdiscount'];
    	//客单 客流
    	$where_count_consumption = 'status!=2 and time>='.$data['start_time'].' and time<'.$data['end_time'].' and company_id in ('.$id.') and is_confirm=1';
    	$count_consumption = C::where($where_count_consumption)->count();
    	$re['cashier_count'] = $count_consumption;
    	//充值卡金
    	$where_chongzhi = 'cps.pay_type not in(17,12,14) and c.status!=2 and order_type in(2,4,5) and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$chongzhi = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_chongzhi)->find();
    	$chongzhi['cpsmoney'] = $chongzhi['cpsmoney']?$chongzhi['cpsmoney']:0;
    	$re['chongzhi'] = $chongzhi['cpsmoney'];
    	//新开卡金
    	$where_open = 'cps.pay_type not in(17,12,14) and c.status!=2 and order_type=3 and c.time>='.$data['start_time'].' and c.time<'.$data['end_time'].$where;
    	$open = CPW::alias('cps')->field('FLOOR(sum(cps.money)) as cpsmoney')->join('cashier c','cps.cashier_id=c.id','left')->where($where_open)->find();
    	$open['cpsmoney'] = $open['cpsmoney']?$open['cpsmoney']:0;
    	$re['open'] = $open['cpsmoney'];

    	return $re;
    }
}
