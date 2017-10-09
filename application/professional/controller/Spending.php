<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use app\index\model\Expenditure;
use app\index\model\Expenditures;

use think\Db;
use \think\Session;

class Spending extends Base
{
	public $rule = [
	            //支出
				//'number|单号' => 'require|unique:expenditure',
				//'total|总金额' => 'require|number',
	        ];
	 public $srule = [
	            //项目字段
	        ];
	public $is_default = ['否','是'];
	public $is_discount = ['否','是'];

	//支出主页
    public function index()
    {
	    $expenditure = new Expenditure;
	    $where = array();
	    $data = array('search'=>'','search_type'=>'');
	    if(Request::instance()->get()){
	    	$data = input('get.');
	    	//搜索条件
	    	if(isset($data['company'])){
		    	$companystr = $this->getChildCompany($data['company'],1);
		    	$where['e.company_id'] = array('in',$companystr);
	    	}
	    }
	    if(Session::get('role')==2){
	    	$where['e.company_id'] = Session::get('company_id');
	    }
	    //公司列表
	    $companylist = $this->getCompanyList(5,1);
    	$this->assign('companylist',$companylist); 
    	//将公司列表转换形式
    	foreach ($companylist as $k => $v) {
    		$company_arr[$v['id']] = $v['full_name'];
    	}
    	//获取支出科目
    	$used_data = $this->getUsedData('74');
    	foreach ($used_data as $k => $v) {
    		$spending_type[$v['id']] = $v['name'];
    	}

    	$list = $expenditure->alias('e')->field('e.*,c.full_name')->join('company c','e.company_id=c.id','left')->where($where)->order('e.id', 'DESC')->paginate(15,false,array('query'=>$data));  

    	$this->assign('list',$list);
    	$this->assign('search',$data);
    	return $this->fetch();
    }
    //添加支出
    public function add(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $expenditure = new Expenditure;
	        $total = 0;
	        foreach ($data['money'] as $k => $v) {
	        	$total+=$v;
	        }
	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check(['time'=>strtotime($data['time']),'total'=>$total]);
	        $expenditure_data = [
	        	'time'=> strtotime($data['time']),
	        	'total'=> $total,
	        	'manager' => $data['manager'],
	        	'company_id' => Session::get('company_id'),
	        	'login_id' => Session::get('login_id'),
	        ];
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($expenditure->save($expenditure_data)){
	        		$expenditures = new Expenditures;
	        		foreach ($data['type'] as $k => $v) {
			        	$expenditures_data[$k] = [
			        		'type' => $data['type'][$k],
			        		'money' => $data['money'][$k],
			        		'parent_id' => $expenditure['id'],
			        		'remark' => $data['remark'][$k],
			        	];
			        }
			        $expenditures->saveAll($expenditures_data);
		            return $this->success('添加支出成功','/Index/Spending/index');
		        } else {
		            return $this->error($expenditure->getError());
		        }
	        }

	        
    	}else{
    		$this->assign('time',date('Y-m-d H:i:s'));
    		//支出科目
    		$used_data = $this->getUsedData('74');
	    	foreach ($used_data as $k => $v) {
	    		$spending_type[$v['id']] = $v['name'];
	    	}
	    	$this->assign('spending_type',$spending_type);
    		return $this->fetch();
    	}
    	
    }
    //编辑
    public function edit($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $expenditure = new Expenditure;
	        $total = 0;
	        if(!isset($data['money'])){
	        	return $this->error('没有支出项目，请正确提交');
	        }
	        foreach ($data['money'] as $k => $v) {
	        	$total+=$v;
	        }
	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        $expenditure_data = [
	        	'time'=> strtotime($data['time']),
	        	'total'=> $total,
	        	'manager' => $data['manager'],
	        	//'company_id' => Session::get('company_id'),
	        	'login_id' => Session::get('login_id'),
	        ];
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($expenditure->save($expenditure_data,['id'=>$data['id']])){
	        		Db::table('expenditures')->where('parent_id',$data['id'])->delete();
	        		foreach ($data['type'] as $k => $v) {
	        			if(!$v){
	        				continue;
	        			}
	        			$expenditures = new Expenditures;
			        	$expenditures_data = [
			        		'parent_id' => $data['id'],
			        		'type' => $data['type'][$k],
			        		'money' => $data['money'][$k],
			        		'remark' => $data['remark'][$k],
			        	];
			        	$expenditures->save($expenditures_data);
			        }
		            return $this->success('修改支出成功','/Index/Spending/index');
		        } else {
		            return $this->error($expenditure->getError());
		        }
	        }

    	}else{
    		if(!empty($id)&&is_numeric($id)){
    			$expenditure = model('expenditure')->get($id);
    			$this->assign('expenditure',$expenditure);

    			$expenditures = Expenditures::where(['parent_id'=>$id])->select();
    			$this->assign('expenditures',$expenditures);
		    	//支出科目
	    		$used_data = $this->getUsedData('74');
		    	foreach ($used_data as $k => $v) {
		    		$spending_type[$v['id']] = $v['name'];
		    	}
		    	$this->assign('spending_type',$spending_type);

	    		return $this->fetch();
    		}
    	}
    }
    //删除
    public function del($id=null){
    	if($id&&is_numeric($id)){
    		Db::table('expenditures')->where('parent_id',$id)->delete();
    		Db::table('expenditure')->where('id',$id)->delete();
    		return $this->success('删除成功','/Index/Spending/index');
    	}else{
    		return $this->success('错误操作','/Index/service/index');
    	}
    }
    //子项列表
    public function childlist($parent_id = 0){
    	$services = new ServicesModel;
	    $where = array('parent_id'=>$parent_id);
	    
    	$list = $services->where($where)->order('id', 'ASC')->paginate(15,false,array('query'=>array('parent_id'=>$parent_id)));  

    	$used_data = $this->getUsedData('52,22');
    	foreach ($used_data as $k => $v) {
    		$used_data_list[$v['id']] = $v['name'];
    	}
    	//整理列表中文字内容  	
    	foreach ($list as $k => &$v) {
    		$v['star_name'] = $used_data_list[$v['star']];
    		$v['technician_str'] = isset($used_data_list[$v['technician']])?$used_data_list[$v['technician']]:'无';
    		$v['is_default'] = $this->is_default[$v['is_default']];
    		$v['is_discount'] = $this->is_discount[$v['is_discount']];
    		$v['old_is_discount'] = $this->is_discount[$v['old_is_discount']];
    		$v['old_price'] = $v['old_price']?$v['old_price']:'-';
    	}

    	$this->assign('list',$list);
    	$this->assign('parent_id',$parent_id);

    	$this->assign('is_default',$this->is_default);
    	$this->assign('is_discount',$this->is_discount);
    	return $this->fetch();
    }
    //添加子项
    public function childadd($parent_id=0){

    	//父项目
    	$service = model('service')->get($parent_id);
    	$this->assign('service',$service);

    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$star = model('Data')->get($data['star']);
    		$data['name'] = $service['name'].'/'.$star['name'].'/'.$data['time_long'].'分钟';
	        $services = new ServicesModel;

	        // 数据验证
	        $validate = new Validate($this->srule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $services->save($data)){
		            return $this->success('子项目添加成功','/Index/Service/childlist/parent_id/'.$parent_id);
		        } else {
		            return $this->error($service->getError());
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
	    	//星级
	    	$services_star = $this->getUsedData(52);
	    	$this->assign('services_star',$services_star); 
	    	//服务类型 
	    	$type = $this->getUsedData(22);
	    	foreach ($type as $k => $v) {
	    		$services_type[$v['id']] = $v['name'];
	    	}
	    	$this->assign('services_type',$services_type); 
    		return $this->fetch();
    	}
    }
    //编辑子项
    public function childedit($id = 0){
	    $services = new ServicesModel;
    	$services = $services->get($id);
    	$this->assign('services',$services);

    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$service = model('service')->get($services['parent_id']);
    		$star = model('Data')->get($data['star']);
    		$data['name'] = $service['name'].'/'.$star['name'].'/'.$data['time_long'].'分钟';

    		$services = new ServicesModel;
	        // 数据验证
	        $validate = new Validate($this->srule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $services->save($data,'id='.$data['id'])){
		            return $this->success('子项目修改成功','/Index/Service/childlist/parent_id/'.$services['parent_id']);
		        } else {
		            return $this->error($services->getError());
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
	    	//星级
	    	$services_star = $this->getUsedData(52);
	    	$this->assign('services_star',$services_star); 
	    	//服务类型 
	    	$type = $this->getUsedData(22);
	    	foreach ($type as $k => $v) {
	    		$services_type[$v['id']] = $v['name'];
	    	}
	    	$this->assign('services_type',$services_type); 
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
}
