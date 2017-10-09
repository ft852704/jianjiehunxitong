<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use app\index\model\Family as FamilyModel;
use app\index\model\Data;
use app\index\model\FamilyServices;

use think\Db;
use \think\Session;

class Family extends Base
{
	//$rule = [
	//    ['name','require|max:25','名称必须|名称最多不能超过25个字符'],
	//    ['age','number|between:1,120','年龄必须是数字|年龄必须在1~120之间'],
	//    ['email','email','邮箱格式错误']
	//];
	public $rule = [
	            //公司添加字段
	            'number|员工编号'   => 'require|unique:family',
				'name|员工名称' => 'require|chs',
				'mobile_phone|手机号码' => 'number|length:11',
				'urgenter|紧急联系人' => 'chs',
				'js_year|工龄' => 'number',
	        ];
	public $msg = [
			    'number.require' => '员工编号必须填写',
			    'number.unique'     => '员工编号必须唯一',
			];
	public $star = [3=>53,4=>54,1=>55,2=>56,0=>0];
    public $teachnician = [1=>27,2=>28,3=>29];
	//员工主页
    public function index()
    {
	    $family = new FamilyModel;
	    $where = array();
	    $data = array('search'=>'','search_type'=>'','company_name'=>'');
	    if(Request::instance()->get()){
	    	$data = input('get.');
	    	//搜索条件
	    	if(isset($data['search'])&&$data['search']){
		    	if($data['search_type']==1){
		    		$where =  array('f.name'=>array('like','%'.$data['search'].'%'));
		    	}elseif($data['search_type']==2){
		    		$where =  array('f.address'=>array('like','%'.$data['search'].'%'));
		    	}elseif($data['search_type']==3){
		    		$where =  array('f.number'=>array('like','%'.$data['search'].'%'));
		    	}else{
		    		$where =  array('f.ID_NO'=>array('like','%'.$data['search'].'%'));
		    	}
		    }
		    if(isset($data['company_name'])&&$data['company_name']){
		    	$data['company_name'] = isset($data['company_name'])?$data['company_name']:'';
		    	$where['c.full_name'] =  array('like','%'.$data['company_name'].'%');
		    }else{
		    	if(isset($data['company'])){
			    	$companystr = $this->getChildCompany($data['company']);
			    	$where['f.company'] = array('in',$companystr);
		    	}
		    }
	    }
	    if(Session::get('role')==2){
    		$id = Session::get('company_id');
    		$where['f.company'] = $id;
    		$companylist = $this->getCompanyList($id,1);
    	}else{
	    	$companylist = $this->getCompanyList(5,1);
    	}

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
    	$used_data = $this->getUsedData('52,67');
    	foreach ($used_data as $k => $v) {
    		$used_data_list[$v['id']] = $v['name'];
    	}
    	$list = $family->alias('f')->field('f.*')->join('company c','f.company=c.id','left')->where($where)->order('f.status DESC,f.id DESC')->paginate(15,false,array('query'=>$data));  
    	//整理列表中文字内容  	
    	foreach ($list as $k => &$v) {
    		$v['company_name'] = $companyall_arr[$v['company']];
    		$v['therapist_star_str'] = isset($used_data_list[$v['therapist_star']])?$used_data_list[$v['therapist_star']]:'无';
    		$v['acupuncture_star_str'] = isset($used_data_list[$v['acupuncture_star']])?$used_data_list[$v['acupuncture_star']]:'无';
    		$v['chinese_star_str'] = isset($used_data_list[$v['chinese_star']])?$used_data_list[$v['chinese_star']]:'无';
    		$v['job_name'] = isset($used_data_list[$v['job']])?$used_data_list[$v['job']]:'无';
    	}

    	$this->assign('list',$list);
    	$this->assign('search',$data);
    	return $this->fetch();
    }
    //添加员工
    public function add(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $family = new FamilyModel;

	        // 数据验证
	        $validate = new Validate($this->rule,$this->msg);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $family->save($data)){
	        		if($data['job']==68){
	        			$js_sex_type = 0;
		        		$star = [];
		        		$this->star = array_flip($this->star);
		        		if($data['chinese_star']){
		        			$js_sex_type = 3;
		        			$star = $this->star[$data['chinese_star']];
		        		}
		        		if($data['acupuncture_star']){
		        			$js_sex_type = 2;
		        			$star = $this->star[$data['acupuncture_star']];
		        		}
		        		if($data['therapist_star']){
		        			$js_sex_type = 1;
		        			$star = $this->star[$data['therapist_star']];
		        		}
		        		$data_app = [
		        			'id' => $family['id'],
		        			'js_name' => $data['name'],
		        			'js_nickname' => $data['nickname'],
		        			'js_no' => $data['number'],
		        			'js_desc' => $data['js_desc'],
		        			//'js_tech' => $data['js_tech'],
		        			'js_sex' => $data['sex']?$data['sex']:2,
		        			'js_year' => $data['js_year'],
		        			'js_shop_id' => $data['company'],
		        			'js_is_promote' => $data['js_is_promote'],
		        			'js_sex_type' => $js_sex_type?$js_sex_type:0,
		        			'js_level' => $star,
		        			'js_pic_path' => '',
		        			'js_character' => $data['js_character'],
		        			'js_interest' => $data['js_interest'],
		        			'js_customer_case1' => $data['js_customer_case1'],
		        			'js_customer_case2' => $data['js_customer_case2'],
		        			'js_training_department' => $data['js_training_department'],
		        			'js_customer' => $data['js_customer'],
		        		];
		        		$data_app['js_shop_id'] = $data['status']?$data_app['js_shop_id']:0;
		        		$re = Db::connect('db_config_app')->table('dz_jishi')->insert($data_app);
	        		}
		            return $this->success('员工资料添加成功','/Index/family/index');
		        } else {
		            return $this->error($family->getError());
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
    		$company_list = Db::table('company')->order('parent_id')->select();
    		$this->assign('company_list',$company_list);
    		//星级类型
    		$star_list = Db::table('used_data')->where('parent_id=52')->select();
    		$this->assign('star_list',$star_list);
    		//人物关系
    		$relation_list = Db::table('used_data')->where('parent_id=39')->select();
    		$this->assign('relation_list',$relation_list);
    		//学历
    		$education_list = Db::table('used_data')->where('parent_id=30')->select();
    		$this->assign('education_list',$education_list);
    		//职务类型
    		$job_list = $this->getUsedData(67);
    		$this->assign('job_list',$job_list);

    		return $this->fetch();
    	}
    	
    }
    //编辑员工信息
    public function edit($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $family = new FamilyModel;

	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        $data_app = [];

	        $family_old = $family->get($data['id']);
	        
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $family->save($data,array('id'=>$data['id']))){
		        	
	        		if($data['job']==68){
	        			//更换技师所属门店后，需删除以前项目和该技师的绑定关系
			        	if($family_old['company']!=$data['company']){
			        		FamilyServices::destroy(['family'=>$data['id']]);
			        	}

	        			$js_sex_type = 0;
		        		$star = [];
		        		$this->star = array_flip($this->star);
		        		if($data['chinese_star']){
		        			$js_sex_type = 3;
		        			$star = $this->star[$data['chinese_star']];
		        		}
		        		if($data['acupuncture_star']){
		        			$js_sex_type = 2;
		        			$star = $this->star[$data['acupuncture_star']];
		        		}
		        		if($data['therapist_star']){
		        			$js_sex_type = 1;
		        			$star = $this->star[$data['therapist_star']];
		        		}
		        		$data_app = [
		        			'js_name' => $data['name'],
		        			'js_nickname' => $data['nickname'],
		        			'js_no' => $data['number'],
		        			'js_desc' => $data['js_desc'],
		        			//'js_tech' => $data['js_tech'],
		        			'js_sex' => $data['sex']?$data['sex']:2,
		        			'js_year' => $data['js_year'],
		        			'js_shop_id' => $data['company'],
		        			'js_is_promote' => $data['js_is_promote'],
		        			'js_sex_type' => $js_sex_type?$js_sex_type:0,
		        			'js_level' => $star,
		        			'js_pic_path' => '',
		        			'js_character' => $data['js_character'],
		        			'js_interest' => $data['js_interest'],
		        			'js_customer_case1' => $data['js_customer_case1'],
		        			'js_customer_case2' => $data['js_customer_case2'],
		        			'js_training_department' => $data['js_training_department'],
		        			'js_customer' => $data['js_customer'],
		        		];
		        		$data_app['js_shop_id'] = $data['status']?$data_app['js_shop_id']:0;
		        		Db::connect('db_config_app')->table('dz_jishi')->where('id',$data['id'])->update($data_app);
	        		}
	        		
		            return $this->success('员工资料修改成功','/Index/family/index');
		        } else {
		            return $this->error($family->getError());
		        }
	        }
	        
    	}else{
    		//公司列表
    		$company_list = Db::table('company')->order('parent_id')->select();
    		$this->assign('company_list',$company_list);
    		//星级类型
    		$star_list = Db::table('used_data')->where('parent_id=52')->select();
    		$this->assign('star_list',$star_list);
    		//人物关系
    		$relation_list = Db::table('used_data')->where('parent_id=39')->select();
    		$this->assign('relation_list',$relation_list);
    		//学历
    		$education_list = Db::table('used_data')->where('parent_id=30')->select();
    		$this->assign('education_list',$education_list);
    		//职务类型
    		$job_list = $this->getUsedData(67);
    		$this->assign('job_list',$job_list);
    		//获取该员工信息
    		$family = model('family')->get($id);
    		$this->assign("family",$family);

    		return $this->fetch();
    	}
    }
    //更改员工状态
    public function del($id=null){
    	if($id&&is_numeric($id)){
    		$family = FamilyModel::get($id);
    		if($family->status==1)$family->status=0;
    		elseif($family->status===0)$family->status=1;

    		if($family->save()){
    			if($family->status==1)$shop=$family->company;
    			elseif($family->status===0)$shop=0;

    			Db::connect('db_config_app')->table('dz_jishi')->where('id',$id)->update(array('js_shop_id'=>$shop));
    			return $this->success('操作成功','/Index/family/index');
    		}else{
    			return $this->error($family->getError());
    		}
    	}else{
    		return $this->success('错误操作','/Index/family/index');
    	}
    }
    //给技师绑定项目
    //
    public function binding_service($id=1){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$family_services = new FamilyServices;
    		$family_services::destroy(['family'=>$data['family_id']]);
    		$fsdata = array();
    		foreach ($data['services'] as $k => $v) {
    			foreach ($v as $key => $value) {
    				$fsdata[] = array(
    				'service' => $k,
    				'services' => $key,
    				'family' => $data['family_id'],
    				);
    			}
    		}
    		if($family_services->saveAll($fsdata)){
    			return $this->success('操作成功','/Index/family/index');
    		}else{
    			return $this->error($family_services->getError());
    		}
    	}else{
    		$list = model('family_services')->field('services')->where('family='.$id)->select();
    		$family_services = array();
    		foreach ($list as $k => $v) {
    			$family_services[] = $v['services'];
    		}
    		$this->assign('family_services',$family_services);

    		$family = model('family')->field('f.*,c.full_name')->alias('f')->join('company c','f.company = c.id','left')->where('f.id='.$id)->find();
    		$where = [
    			'company_id'=>$family->company,
    			'status' => 1,
    		];
    		$condition = '';
    		//筛选出该技师所能做的项目
    		if($family['therapist_star']>0){
    			$condition = '27';
    		}
    		if($family['acupuncture_star']>0){
    			$condition .= $condition?',28':28;
    		}
    		if($family['chinese_star']>0){
    			$condition .= $condition?',29':29;
    		}
    		$where['technician'] = ['in',$condition];
	    	$service = model('service')->where($where)->select();
	    	$services = array();
	    	foreach ($service as $k => $v) {
	    		$services[$k] = $v;
	    		$services[$k]['list'] = model('services')->where('parent_id='.$v->id.' and status=1')->select();
	    	}
	    	$this->assign('services',$services);
	    	$this->assign('family',$family);
	    	return $this->fetch();
    	}
    }
}
