<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use app\index\model\MemberCardType as MCT;
use app\index\model\CardTypeChildren as CTC;
use app\index\model\CardTypeCompany;
use app\index\model\ActivityCardTypeCompany;
use \think\Session;

use think\Db;

class MemberCardType extends Base
{
	public $rule = [
	            //会员卡类型字段
	            'number|项目编号'   => 'require|unique:member_card_type',
				'name|名称' => 'require',
	        ];
	public $srule = [
				//消费折扣
				'pay_id|支付方式' => 'require',
				'discount|折扣率' => 'require|elt:1|egt:0',
	];

	//会员卡类型主页
    public function index()
    {
    	$data = input();
	    $card = new MCT;
	    $where = [];
		if(isset($data['from'])&&$data['from']){
			$where['mct.from'] = $data['from'];
		}
	    if(Session::get('role')==2){
	   		$where['ctc.company_id'] = Session::get('company_id');
		}
	    $list = $card->alias('mct')->field('mct.*')->join('card_type_company ctc','ctc.card_type_id = mct.id','left')->order('mct.id','DESC')->where($where)->group('mct.id')->paginate(50);
    	//整理列表中文字内容
    	/*foreach ($list as $k => &$v) {
    		$v['company_name'] = $company_arr[$v['company_id']];
    		$v['technician_str'] = isset($used_data_list[$v['technician']])?$used_data_list[$v['technician']]:'无';
    		$v['type_name'] = isset($used_data_list[$v['type']])?$used_data_list[$v['type']]:'无';
    	}*/

    	$this->assign('list',$list);
    	return $this->fetch();
    }
    //添加父项
    public function add(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $cardType = new MCT;
	        $data['validity'] = strtotime($data['validity']);

	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $cardType->save($data)){
		            return $this->success('会员卡类型添加成功','/Index/member_card_type/index');
		        } else {
		            return $this->error($cardType->getError());
		        }
	        }

	        // 数据验证
	        /*$company = new CompanyModel;
		    if ($company->validate(true)->save(input('post.'))) {
		        return '公司[ ' . $company->full_name . ':' . $company->id . ' ]新增成功';
		    } else {
		        return $company->getError();
		    }*/
	        
    	}else{

    		return $this->fetch();
    	}
    	
    }
    //编辑父项
    public function edit($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $cardType = new MCT;
	        $data['validity'] = strtotime($data['validity']);
	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $cardType->save($data,'id='.$id)){
		            return $this->success('会员卡类型修改成功','/Index/member_card_type/index');
		        } else {
		            return $this->error($service->getError());
		        }
	        }

    	}else{
    		if(!empty($id)&&is_numeric($id)){
    			$cardType = model('member_card_type')->get($id);
    			$this->assign('cardType',$cardType);

		    	return $this->fetch();
    		}
    	}
    }
    //更改父项状态
    public function del($id=null){
    	if($id&&is_numeric($id)){
    		$cardType = MCT::get($id);
    		if($cardType->status==1)$cardType->status=0;
    		elseif($cardType->status===0)$cardType->status=1;
    		if($cardType->save()){
    			return $this->success('操作成功','/Index/member_card_type/index');
    		}else{
    			return $this->error($cardType->getError());
    		}
    	}else{
    		return $this->success('错误操作','/Index/member_card_type/index');
    	}
    }
    //消费折扣设置列表
    public function childlist($parent_id = 0){
	    $cardType = MCT::get($parent_id);

    	$cardChildren = new CTC;

	    $where = array('parent_id'=>$parent_id);

    	$list = $cardChildren->where($where)->order('id', 'ASC')->paginate(15,false,array('query'=>array('parent_id'=>$parent_id)));

    	$used_data = $this->getUsedData([6,10,71]);
    	foreach ($used_data as $k => &$v) {
    		$used_data_list[$v['id']] = $v['name'];
    	}
    	//整理列表中文字内容
    	foreach ($list as $k => &$v) {
    		$v['pay_name'] = $used_data_list[$v['pay_id']];
    		$v['parent_name'] = $cardType['name'];
    	}

    	$this->assign('list',$list);
    	$this->assign('parent_id',$parent_id);

    	return $this->fetch();
    }
    //添加消费折扣
    public function childadd($parent_id=0){

    	//会员卡类型
    	$cardType = MCT::get($parent_id);
    	$this->assign('cardType',$cardType);

    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $cardChildren = new CTC;
	        $data['service_type'] = 72;
	        // 数据验证
	        $validate = new Validate($this->srule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $cardChildren->save($data)){
		            return $this->success('消费折扣添加成功','/Index/member_card_type/childlist/parent_id/'.$parent_id);
		        } else {
		            return $this->error($cardChildren->getError());
		        }
	        }

	        // 数据验证
	        /*$company = new CompanyModel;
		    if ($company->validate(true)->save(input('post.'))) {
		        return '公司[ ' . $company->full_name . ':' . $company->id . ' ]新增成功';
		    } else {
		        return $company->getError();
		    }*/
	        
    	}else{
	    	//支付类型 
	    	$type = $this->getUsedData([6,10]);
	    	foreach ($type as $k => $v) {
	    		$pay_type[$v['id']] = $v['name'];
	    	}
	    	$this->assign('pay_type',$pay_type); 
    		return $this->fetch();
    	}
    }
    //编辑子项
    public function childedit($id = 0){
	    $cardChildren = new CTC;
    	$cardChildren = $cardChildren->field('c.*,m.name')->alias('c')->join('member_card_type m','c.parent_id = m.id','left')->where('c.id='.$id)->find();
    	$this->assign('cardChildren',$cardChildren);

    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $cardChildren = new CTc;
	        $data['service_type'] = 72;
	        // 数据验证
	        $validate = new Validate($this->srule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $cardChildren->save($data,array('id'=>$id))){
		            return $this->success('消费折扣修改成功','/Index/member_card_type/childlist/parent_id/'.$cardChildren['parent_id']);
		        } else {
		            return $this->error($cardChildren->getError());
		        }
	        }

	        // 数据验证
	        /*$company = new CompanyModel;
		    if ($company->validate(true)->save(input('post.'))) {
		        return '公司[ ' . $company->full_name . ':' . $company->id . ' ]新增成功';
		    } else {
		        return $company->getError();
		    }*/
	        
    	}else{
	    	//支付类型 
	    	$type = $this->getUsedData([6,10]);
	    	foreach ($type as $k => $v) {
	    		$pay_type[$v['id']] = $v['name'];
	    	}
	    	$this->assign('pay_type',$pay_type);  
    		return $this->fetch();
    	}
    }
    //更改子项状态
    public function childdel($id = null){
    	if($id&&is_numeric($id)){
    		$services = ServicesModel::get($id);
    		if($services->status==1)$services->status=0;
    		elseif($services->status===0)$services->status=1;
    		if($services->save()){
    			return $this->success('操作成功','/Index/Service/childlist/parent_id/'.$services['parent_id']);
    		}else{
    			return $this->error($services->getError());
    		}
    	}else{
    		return $this->success('错误操作','/Index/Service/childlist/parent_id/'.$services['parent_id']);
    	}
    }
    //分发卡类型给门店
    public function distribution($id = null){
    	if($id&&is_numeric($id)){
    		if(Request::instance()->isPost()){
    			$data = input('post.');
	    		$fsdata = [];
	    		$ctype = MCT::get($data['card_type']);
	    		foreach (@$data['companys'] as $k => $v) {
	    			foreach ($v as $key => $val) {
	    				$fsdata[] = array(
	    				'card_type_id' => $data['card_type'],
	    				'company_id' => $key,
	    				'open_standard' => $data['open_standard'][$key],
	    				'open_standard_e' => $data['open_standard_e'][$key],
	    				'recharge_standard' => $data['recharge_standard'][$key],
	    				'recharge_standard_e' => $data['recharge_standard_e'][$key],
	    				);
	    			}
	    		}
	    		//删除上次分发的数据
	    		$card_type_company = new CardTypeCompany;
	    		$card_type_company::destroy(['card_type_id'=>$data['card_type']]);

	    		if($card_type_company->saveAll($fsdata)){
	    			return $this->success('操作成功','/Index/member_card_type/index');
	    		}else{
	    			return $this->error($card_type_company->getError());
	    		}
	    	}else{
	    		$list = model('card_type_company')->field('company_id,open_standard,recharge_standard,open_standard_e,recharge_standard_e')->where('card_type_id='.$id)->select();
	    		$card_type_company = array();
	    		foreach ($list as $k => $v) {
	    			$card_type_company['company_id'][] = $v['company_id'];
	    			$card_type_company['open_standard'][$v['company_id']] = $v['open_standard'];
	    			$card_type_company['open_standard_e'][$v['company_id']] = $v['open_standard_e'];
	    			$card_type_company['recharge_standard'][$v['company_id']] = $v['recharge_standard'];
	    			$card_type_company['recharge_standard_e'][$v['company_id']] = $v['recharge_standard_e'];
	    		}
	    		$this->assign('card_type_company',$card_type_company);

	    		$card_type = model('member_card_type')->get($id);
	    		$company = model('company')->where(['status'=>1,'level'=>1])->select();
	    		$companys = [];
		    	foreach ($company as $k => $v) {
		    		$companys[$k] = $v;
		    		$companys[$k]['list'] = model('company')->where('parent_id='.$v->id.' and status=1')->select();
		    	}
		    	$this->assign('companys',$companys);
		    	$this->assign('card_type',$card_type);
		    	return $this->fetch();
	    	}
	    }else{
	    	return $this->error('非法操作');
	    }
    }
    //分发活动卡类型给门店
    public function activity_distribution($id = null){
    	if($id&&is_numeric($id)){
    		if(Request::instance()->isPost()){
    			$data = input('post.');
	    		$card_type_company = new ActivityCardTypeCompany;
	    		$card_type_company::destroy(['card_type_id'=>$data['card_type']]);
	    		$fsdata = [];
	    		$ctype = MCT::get($data['card_type']);
	    		foreach (@$data['companys'] as $k => $v) {
	    			foreach ($v as $key => $val) {
	    				$fsdata[] = array(
	    				'company_id' => $key,
	    				'card_type_id' => $data['card_type'],
	    				'open_standard' => $data['open_standard'][$key],
	    				'open_standard_e' => $data['open_standard_e'][$key],
	    				'recharge_standard' => $data['recharge_standard'][$key],
	    				'recharge_standard_e' => $data['recharge_standard_e'][$key],
	    				);
	    			}
	    		}
	    		if($card_type_company->saveAll($fsdata)){
	    			return $this->success('操作成功','/Index/member_card_type/index');
	    		}else{
	    			return $this->error($card_type_company->getError());
	    		}
	    	}else{
	    		$list = model('activity_card_type_company')->field('company_id,open_standard,recharge_standard,open_standard_e,recharge_standard_e')->where('card_type_id='.$id)->select();
	    		$card_type_company = array();
	    		foreach ($list as $k => $v) {
	    			$card_type_company['company_id'][] = $v['company_id'];
	    			$card_type_company['open_standard'][$v['company_id']] = $v['open_standard'];
	    			$card_type_company['open_standard_e'][$v['company_id']] = $v['open_standard_e'];
	    			$card_type_company['recharge_standard'][$v['company_id']] = $v['recharge_standard'];
	    			$card_type_company['recharge_standard_e'][$v['company_id']] = $v['recharge_standard_e'];
	    			$card_type_company['activity_time'][$v['company_id']] = $v['start_time']?date('Y-m-d H:i:s',$v['start_time']):'';
	    			$card_type_company['activity_time_e'][$v['company_id']] = $v['end_time']?date('Y-m-d H:i:s',$v['end_time']):'';
	    		}
	    		$this->assign('card_type_company',$card_type_company);

	    		$card_type = model('member_card_type')->get($id);
	    		$company = model('company')->where(['status'=>1,'level'=>1])->select();
	    		$companys = [];
		    	foreach ($company as $k => $v) {
		    		$companys[$k] = $v;
		    		$companys[$k]['list'] = model('company')->where('parent_id='.$v->id.' and status=1')->select();
		    	}
		    	$this->assign('companys',$companys);
		    	$this->assign('card_type',$card_type);
		    	return $this->fetch();
	    	}
	    }else{
	    	return $this->error('非法操作');
	    }
    }
}
