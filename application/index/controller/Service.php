<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use app\index\model\Service as ServiceModel;
use app\index\model\Services as ServicesModel;
use app\index\model\FamilyServices;
use app\index\model\Company;

use think\Db;
use \think\Session;

class Service extends Base
{
	public $rule = [
	            //项目字段
	            'number|项目编号'   => 'require',
				'name|中文全称' => 'require',
				'short_name|中文简称'   => 'require',
				'type|项目类型'   => 'require',
				'technician|服务类型'     => 'require',
				'company_id|所属公司'     => 'require',
				'count|服务人数'     => 'require|max:1|number',
				'status|状态'     => 'require',
	        ];
	 public $srule = [
	            //项目字段
	            'parent_id| 项目类'   => 'require',
				'name|名称' => 'require',
				'time_long|时长'   => 'require',
				'technician|服务类型' => 'require',
				'star|星级'   => 'require',
				'price|价格'   => 'require',
				'is_discount|是否参与打折'     => 'require',
				'is_default|是否默认'     => 'require',
				'status|状态'     => 'require',
	        ];
	public $is_default = ['否','是'];
	public $is_discount = ['否','是'];

	//公司主页
    public function index()
    {
	    $service = new ServiceModel;
	    $where = array();
	    $data = array('search'=>'','search_type'=>'','company_name'=>'');
	    if(Request::instance()->get()){
	    	$data = input('get.');
	    	//搜索条件
	    	if(isset($data['search'])&&$data['search']){
		    	if($data['search_type']==1){
		    		$where =  array('s.name'=>array('like','%'.$data['search'].'%'));
		    	}elseif($data['search_type']==2){
		    		$where =  array('s.number'=>array('like','%'.$data['search'].'%'));
		    	}
		    }
	    	if(isset($data['company_name'])&&$data['company_name']){
		    	$where['c.full_name'] =  array('like','%'.$data['company_name'].'%');
		    }else{
		    	if(isset($data['company'])){
			    	$companystr = $this->getChildCompany($data['company']);
			    	$where['s.company_id'] = array('in',$companystr);
		    	}
		    }
	    }
	    if(Session::get('role')==2){
    		$id = Session::get('company_id');
    		$where['s.company_id'] = $id;
    		$companylist = $this->getCompanyList($id,1);
    	}else{
	    	$companylist = $this->getCompanyList(5,1);
    	}
	    //公司列表
    	$this->assign('companylist',$companylist); 
    	$companyalllist = $this->getCompanyList(5);
    	//将公司列表转换形式
    	foreach ($companylist as $k => $v) {
    		$company_arr[$v['id']] = $v['full_name'];
    	}
    	foreach ($companyalllist as $k => $v) {
    		$companyall_arr[$v['id']] = $v['full_name'];
    	}
    	//获取常用资料
    	$used_data = $this->getUsedData('22,71');
    	foreach ($used_data as $k => $v) {
    		$used_data_list[$v['id']] = $v['name'];
    	}
    	$list = $service->alias('s')->field('s.*')->join('company c','s.company_id=c.id','left')->where($where)->order('s.id', 'DESC')->paginate(15,false,array('query'=>$data));  
    	//整理列表中文字内容
    	foreach ($list as $k => &$v) {
    		$v['company_name'] = $companyall_arr[$v['company_id']];
    		$v['technician_str'] = isset($used_data_list[$v['technician']])?$used_data_list[$v['technician']]:'无';
    		$v['type_name'] = isset($used_data_list[$v['type']])?$used_data_list[$v['type']]:'无';
    	}

    	$this->assign('list',$list);
    	$this->assign('search',$data);
    	return $this->fetch();
    }
    //添加父项
    public function add(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$data['time'] = time();
	        $service = new ServiceModel;
	        $tmp = $service->where(['number'=>$data['number'],'company_id'=>$data['company_id']])->find();
	        if(!empty($tmp)){
	        	return $this->error('该门店已有该项目编号');
	        }

	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
    			$file = $this->files_upload('image');
    			if($file['status']){
    				$data['image'] = $file['path'][0];
    			}
	        	if($id = $service->save($data)){
		            return $this->success('父项目添加成功','/Index/Service/index');
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

	    	//公司列表
		    $companylist = $this->getCompanyList();
	    	$this->assign('companylist',$companylist); 
	    	//项目分类
	    	$service_type = $this->getUsedData(71);
	    	$this->assign('service_type',$service_type); 
	    	//服务类型 
	    	$services_type = $this->getUsedData(22);
	    	$this->assign('services_type',$services_type); 
	    	//门店id
	    	$this->assign('company_id',Session::get('company_id'));
	    	
    		return $this->fetch();
    	}
    	
    }
    //编辑父项
    public function edit($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $service = new ServiceModel;

	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	$service_old = $service->get($id);
	        	$tmp = $service->where('number='.$data['number'].' and company_id ='.$data['company_id'].' and id <>'.$id)->find();
		        if(!empty($tmp)){
		        	return $this->error('该门店已有该项目编号');
		        }
    			$file = $this->files_upload('image');
    			if($file['status']){
    				$data['image'] = $file['path'][0];
    			}
	        	if($id = $service->save($data,'id='.$id)){
	        		//更改项目归属公司时，删除该项目和技师的绑定关系
	        		if($service_old['company_id']!=$service['company_id']){
	        			FamilyServices::destroy(['service'=>$service_old['id']]);
	        		}
		            return $this->success('父项目修改成功','/Index/Service/index');
		        } else {
		            return $this->error($service->getError());
		        }
	        }
    	}else{
    		if(!empty($id)&&is_numeric($id)){
    			$service = model('service')->get($id);
    			$this->assign('service',$service);

    			//公司列表
			    $companylist = $this->getCompanyList();
		    	$this->assign('companylist',$companylist); 
		    	//项目分类
		    	$service_type = $this->getUsedData(71);
		    	$this->assign('service_type',$service_type); 
		    	//服务类型 
		    	$services_type = $this->getUsedData(22);
		    	$this->assign('services_type',$services_type); 
		    		return $this->fetch();
    		}
    	}
    }
    //更改父项状态
    public function del($id=null){
    	if($id&&is_numeric($id)){
    		$service = ServiceModel::get($id);
    		if($service->status==1)$service->status=0;
    		elseif($service->status===0)$service->status=1;
    		if($service->save()){
    			return $this->success('操作成功','/Index/service/index');
    		}else{
    			return $this->error($service->getError());
    		}
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
    		$data['time'] = time();
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
    //将双楠的父项目数据复制到各个门店
    public function copyServiceToCompany(){
    	$service = ServiceModel::select();
    	$companylist = Company::where('level=2 and status=1 and id!=4')->select();
    	
    	foreach ($companylist as $k => $v) {
    		foreach ($service as $key => $value) {
    			$servicedata = [
	    			'number' => $value['number'],
	    			'name' => $value['name'],
	    			'type' => $value['type'],
	    			'short_name' => $value['short_name'],
	    			'count' => $value['count'],
	    			'company_id' => $v['id'],
	    			'technician' => $value['technician'],
	    			'sort' => $value['sort'],
	    		];
	    		$ser = new ServiceModel;
	    		$re = $ser->save($servicedata);
	    		if($re){
	    			$services = ServicesModel::where(['parent_id'=>$value['id']])->select();
	    			foreach ($services as $ke => $val) {
	    				$sers = new ServicesModel;
	    				$servicesdata = [
	    					'parent_id' => $ser['id'],
	    					'name' => $val['name'],
	    					'time_long' => $val['time_long'],
	    					'price' => $val['price'],
	    					'star' => $val['star'],
	    					'technician' => $val['technician'],
	    					'is_discount' => $val['is_discount'],
	    				];
	    				$re = $sers->save($servicesdata);
	    				if($re){
	    					echo $val['name'].' '.$v['full_name'].'<br/>';
	    				}else{
	    					echo $sers->getLastSql();
	    					exit;
	    				}
	    			}
	    		}else{
	    			echo $ser->getLastSql();
	    		}
    		}
    	}
    	exit;
    }
    //图片上传
    /**
	 * [file_upload 文件上传函数，支持单文件，多文件]
	 * Author: 程威明
	 * @param  string $name         input表单中的name
	 * @param  string $save_dir         文件保存路径，相对于当前目录
	 * @param  array  $allow_suffix 允许上传的文件后缀
	 * @return array                array() {
	 *                                         ["status"]=> 全部上传成功为true，全部上传失败为false，部分成功为成功数量
	 *                                         ["path"]=>array() {已成功的文件路径}
	 *                                         ["error"]=>array() {失败信息}
	 *                                      }
	 */
	public function files_upload($name="photo",$save_dir="service_img",$allow_suffix=array('jpg','jpeg','gif','png'))
	{
	    //如果是单文件上传，改变数组结构
	    if(!is_array($_FILES[$name]['name'])){
	        $list = array();
	        foreach($_FILES[$name] as $k=>$v){
	            $list[$k] = array($v);
	        }
	        $_FILES[$name] = $list;
	    }

	    $response = array();
	    $response['status'] = array();
	    $response['path'] = array();
	    $response['error'] = array();

	    //拼接保存目录
	    $save_dir = './'.trim(trim($save_dir,'.'),'/').'/';

	    //判断保存目录是否存在
	    if(!file_exists($save_dir))
	    {
	        //不存在则创建
	        if(false==mkdir($save_dir,0777,true))
	        {
	            $response['status'] = false;
	            $response['error'][] = '文件保存路径错误,路径 "'.$save_dir.'" 创建失败';
	        }
	    }

	    $num = count($_FILES[$name]['tmp_name']);

	    $success = 0;

	    //循环处理上传
	    for($i=0;$i <$num;$i++)
	    {
	        //判断是不是post上传
	        if(!is_uploaded_file($_FILES[$name]['tmp_name'][$i]))
	        {
	            $response['error'][] = '非法上传，文件 "'.$_FILES[$name]['name'][$i].'" 不是post获得的';
	            continue;
	        }

	        //判断错误
	        if($_FILES[$name]['error'][$i]>0)
	        {
	            $response['error'][] = '文件 "'.$_FILES[$name]['name'][$i].'" 上传错误,error下标为 "'.$_FILES[$name]['error'][$i].'"';
	            continue;
	        }

	        //获取文件后缀
	        $suffix = ltrim(strrchr($_FILES[$name]['name'][$i],'.'),'.');

	        //判断后缀是否是允许上传的格式
	        if(!in_array($suffix,$allow_suffix))
	        {
	            $response['error'][] = '文件 "'.$_FILES[$name]['name'][$i].'" 为不允许上传的文件类型';
	            continue;
	        }

	        //得到上传后文件名
	        $new_file_name =date('ymdHis',time()).'_'.uniqid().'.'.$suffix;

	        //拼接完整路径
	        $new_path = $save_dir.$new_file_name;

	        //上传文件 把tmp文件移动到保存目录中
	        if(!move_uploaded_file($_FILES[$name]['tmp_name'][$i],$new_path))
	        {
	            $response['error'][] = '文件 "'.$_FILES[$name]['name'][$i].'" 从临时文件夹移动到保存目录时发送错误';
	            continue;
	        }

	        //返回由图片文件路径组成的数组
	        $response['path'][] =$save_dir.$new_file_name;

	        $success++;
	    }

	    if(0==$success){
	        $success = false;
	    }elseif($success==$num){
	        $success = true;
	    }

	    $response['status'] = $success;

	    return $response;
	}
    //将双楠的子项目数据复制到各个门店
    public function copyServicesToCompany(){
    	$services = Services::select();
    }
}
