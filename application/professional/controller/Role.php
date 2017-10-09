<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use app\index\model\Role as RoleModel;
use app\index\model\RoleAce;

use think\Db;
use \think\Session;

class Role extends Base
{
	//$rule = [
	//    ['name','require|max:25','名称必须|名称最多不能超过25个字符'],
	//    ['age','number|between:1,120','年龄必须是数字|年龄必须在1~120之间'],
	//    ['email','email','邮箱格式错误']
	//];
	//角色主页
    public function index()
    {
	    $list = RoleModel::paginate(15,false,array('query'=>[]));

    	$this->assign('list',$list);

    	$companyalllist = $this->getCompanyList(5);
    	$this->assign('companylist',$companyalllist); 

    	return $this->fetch();
    }
    //添加角色
    public function add(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $role = new RoleModel;
	        if($role->save($data)){
	        	return $this->success('角色添加成功','/Index/Role/index');
	        }else{
	        	return $this->error($role->getError());
	        }
    	}else{
    		return $this->fetch();
    	}
    	
    }
    //编辑角色信息
    public function edit($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $role = new RoleModel;
	        if($role->save($data,['id'=>$data['id']])){	
		        return $this->success('角色资料修改成功','/Index/role/index');
		    } else {
		        return $this->error($role->getError());
		    }
	        
    	}else{
    		//获取该角色信息
    		$role = model('role')->get($id);
    		$this->assign("role",$role);

    		return $this->fetch();
    	}
    }
    //更改角色状态
    public function del($id=null){
    	if($id&&is_numeric($id)){
    		$role = RoleModel::get($id);
    		if($role->status==1)$role->status=0;
    		elseif($role->status===0)$role->status=1;

    		if($role->save()){

    			return $this->success('操作成功','/Index/role/index');
    		}else{
    			return $this->error($role->getError());
    		}
    	}else{
    		return $this->success('错误操作','/Index/role/index');
    	}
    }
    //给角色绑定权限 //
    public function binding_ace($id=1){
    	if($id&&is_numeric($id)){
    		if(Request::instance()->isPost()){
    			$data = input('post.');
	    		$role_ace = new RoleAce;
	    		$role_ace::destroy(['role_id'=>$data['role']]);
	    		$fsdata = [];
	    		foreach ($data['paces'] as $k => $v) {
	    			$fsdata[] = array(
	    				'role_id' => $data['role'],
	    				'ace_id' => $k,
	    				);
	    		}
	    		if($role_ace->saveAll($fsdata)){
	    			return $this->success('操作成功','/Index/role/index');
	    		}else{
	    			return $this->error($role_ace->getError());
	    		}
	    	}else{
	    		$list = model('role_ace')->field('ace_id')->where('role_id='.$id)->select();
	    		$role_ace = array();
	    		foreach ($list as $k => $v) {
	    			$role_ace[] = $v['ace_id'];
	    		}
	    		$this->assign('role_ace',$role_ace);

	    		$role = model('role')->where('id='.$id)->find();
	    		$pace = model('ace')->where(['parent_id'=>0])->select();
	    		$paces = [];
		    	foreach ($pace as $k => $v) {
		    		$paces[$k] = $v;
		    		$paces[$k]['list'] = model('ace')->where('parent_id='.$v->id)->select();
		    	}
		    	$this->assign('paces',$paces);
		    	$this->assign('role',$role);
		    	return $this->fetch();
	    	}
	    }else{
	    	return $this->error('非法操作');
	    }
    }
}
