<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\index\model\LoginFamily;
use app\index\model\Role;
use app\index\model\LoginAce;
use app\index\model\LoginLog;

class Index extends Base
{
	public $role = [
    		1 => '管理员',
    		2 => '收银员',
    	];
	public function _initialize()
    {
        
    }
	//主页
    public function index()
    {
    	if(!Session::get('login_id')){
    		$this->redirect('login');
			//$this->success('请登录', 'Index/login');
		}
		$this->is_power();
		$role = Session::get('role');
		$this->assign('role',$role);
		//$family_name = Session::get('family_name');
		//$this->assign('family_name',$family_name);
    	return $this->fetch();
    }
    //登录
    public function login(){
    	if(Request::instance()->post()){
    		$data = input();
    		$data['username'] = trim($data['username']);
    		$data['password'] = md5(trim($data['password']));
    		$login = LoginFamily::where($data)->find();
    		if($login){
	    		if(!$login['status']){
	    			$this->error('账号未启用，请联系管理员启用后再登录', 'Index/login');
	    		}
    			Session::set('family_id',$login['family_id']);
		        Session::set('family_name',$login['name']);
				Session::set('company_id',$login['company']);
				Session::set('login_id',$login['id']);
				Session::set('role',$login['role']);
				LoginLog::insert(['login_id'=>$login['id'],'ip'=>$_SERVER["REMOTE_ADDR"],'time'=>time()]);
				$this->success('登录成功', 'Index/index');
    		}else{
    			$this->error('用户名或密码错误，请重新输入', 'Index/login');
    		}
    	}else{
    		return $this->fetch();	
    	}
    }
    //退出登录
    function loginout(){
    	Session::delete('login_id');
    	$this->success('请登录', 'Index/login');
    }
    //登录账号列表
    function loginlist(){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	$role = Role::select();
    	$rolelist = [];
    	foreach ($role as $key => $value) {
    		$rolelist[$value['id']] = $value['name'];
    	}
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = LoginFamily::alias('lf')->field('lf.*,c.full_name')->join('company c','lf.company=c.id','left')->order('lf.status DESC,lf.id DESC')->paginate(15,false);
    	foreach ($list as $k => &$v) {
    		$list[$k]['role_name'] = $rolelist[$v['role']];
    	}
    	$this->assign('list',$list);

    	return $this->fetch();	
    }
    //登录账号添加
    function loginadd(){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	$data['password'] = md5($data['password']);
	    	$data['time'] = date('Y-m-d H:i:s');
	    	$lf = new LoginFamily;
	    	if($lf->save($data)){
	    		return $this->success('登录账号添加成功','/Index/index/loginlist');
	    	}else{
		        return $this->error($lf->getError());
	    	}
	    }
    	//公司列表
    	$company_list = Db::table('company')->where(['level'=>2,'status'=>1])->order('parent_id')->select();
    	$this->assign('company_list',$company_list);
    	//角色
    	$role = Role::select();
    	$rolelist = [];
    	foreach ($role as $key => $value) {
    		$rolelist[$value['id']] = $value['name'];
    	}
		$this->assign('rolelist',$rolelist);

    	return $this->fetch();	
    }
    //登录账号编辑
    function loginedit($id=0){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	if($data['password']){
	    		$data['password'] = md5($data['password']);
	    	}else{
	    		unset($data['password']);
	    	}
	    	$lf = LoginFamily::get($data['id']);
	    	if($lf->save($data)){
	    		return $this->success('登录账号修改成功','/Index/index/loginlist');
	    	}else{
		        return $this->error($lf->getError());
	    	}
	    }
    	//公司列表
    	$company_list = Db::table('company')->where(['level'=>2,'status'=>1])->order('parent_id')->select();
    	$this->assign('company_list',$company_list);
    	//角色
    	$role = Role::select();
    	$rolelist = [];
    	foreach ($role as $key => $value) {
    		$rolelist[$value['id']] = $value['name'];
    	}
		$this->assign('rolelist',$rolelist);

		//登录账号
		$lf = LoginFamily::get($id);
		$this->assign('lf',$lf);

    	return $this->fetch();	
    }
    //登录账号删除
    function loginedel($id=null){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	if($id&&is_numeric($id)){
    		$lf = LoginFamily::get($id);
    		if($lf->status==1)$lf->status=0;
    		elseif($lf->status===0)$lf->status=1;

    		if($lf->save()){
    			return $this->success('操作成功','/Index/index/loginlist');
    		}else{
    			return $this->error($family->getError());
    		}
    	}else{
    		return $this->success('错误操作','/Index/index/loginlist');
    	}
    }

    //给账号绑定权限 
    public function binding_ace($id=1){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	if($id&&is_numeric($id)){
    		if(Request::instance()->isPost()){
    			$data = input('post.');
	    		$login_ace = new LoginAce;
	    		$login_ace::destroy(['login_id'=>$data['login']]);
	    		$fsdata = [];
	    		foreach ($data['paces'] as $k => $v) {
	    			$fsdata[] = array(
	    				'login_id' => $data['login'],
	    				'ace_id' => $k,
	    				);
	    		}
	    		if($login_ace->saveAll($fsdata)){
	    			return $this->success('操作成功','/Index/index/loginlist');
	    		}else{
	    			return $this->error($login_ace->getError());
	    		}
	    	}else{
	    		$list = model('login_ace')->field('ace_id')->where('login_id='.$id)->select();
	    		$login_ace = array();
	    		if(empty($list)){
	    			$login = model('login_family')->get($id);
	    			$list = model('role_ace')->field('ace_id')->where('role_id='.$login['role'])->select();
	    			foreach ($list as $k => $v) {
		    			$login_ace[] = $v['ace_id'];
		    		}
	    		}else{
	    			foreach ($list as $k => $v) {
		    			$login_ace[] = $v['ace_id'];
		    		}
	    		}
	    		
	    		$this->assign('login_ace',$login_ace);

	    		$login = model('login_family')->where('id='.$id)->find();
	    		$pace = model('ace')->where(['parent_id'=>0])->select();
	    		$paces = [];
		    	foreach ($pace as $k => $v) {
		    		$paces[$k] = $v;
		    		$paces[$k]['list'] = model('ace')->where('parent_id='.$v->id)->select();
		    	}
		    	$this->assign('paces',$paces);
		    	$this->assign('login',$login);
		    	return $this->fetch();
	    	}
	    }else{
	    	return $this->error('非法操作');
	    }
    }
}
