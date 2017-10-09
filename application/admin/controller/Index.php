<?php
namespace app\admin\controller;
use app\admin\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\admin\model\Admin;
use app\admin\model\Role;
use app\admin\model\LoginAce;
use app\admin\model\AdminLoginLog;

class Index extends Base
{
	public $role = [
    		1 => '管理员',
    		2 => '收银员',
    	];
	public function _initialize()
    {
        
    }
	//框架
    public function index()
    {
    	if(!Session::get('id')){
    		$this->redirect('login');
			//$this->success('请登录', 'Index/login');
		}
		//$this->is_power();
		$role = Session::get('role');
		//$this->assign('role',$role);
		//$family_name = Session::get('family_name');
		//$this->assign('family_name',$family_name);
    	return $this->fetch();
    }
    //欢迎页
    public function welcome(){
    	return $this->fetch();
    }
    //登录
    public function login(){
    	if(Request::instance()->post()){
    		$data = input();
    		$data['user_name'] = trim($data['user_name']);
    		$admin = Admin::where(['user_name'=>$data['user_name']])->find();
    		if(empty($admin)){
    			$this->error('账号不存在', 'Index/login');
    		}
    		$data['password'] = md5(trim($data['password']).$admin['salt']);
    		$login = Admin::where($data)->find();
    		if(empty($login)){
    			$this->error('密码错误', 'Index/login');
    		}
    		if($login){
	    		if(!$login['status']){
	    			$this->error('账号未启用，请联系管理员启用后再登录', 'Index/login');
	    		}
    			Session::set('id',$login['id']);
		        Session::set('nick',$login['nick']);
				Session::set('role',$login['role']);
				AdminLoginLog::insert(['login_id'=>$login['id'],'ip'=>$_SERVER["REMOTE_ADDR"],'time'=>time()]);
				$this->success('登录成功', '/admin/index');
    		}else{
    			$this->error('用户名或密码错误，请重新输入', 'Index/login');
    		}
    	}else{
    		return $this->fetch();	
    	}
    }
    //退出登录
    function loginout(){
    	Session::delete('id');
    	$this->success('请登录', 'Index/login');
    }
    //登录账号列表
    function admin_list(){
	    $data = input();
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = Admin::order('status DESC,id DESC')->paginate(15,false);

    	$this->assign('list',$list);
    	$data['start_time'] = '';
    	$data['end_time'] = '';
    	$this->assign('data',$data);
    	return $this->fetch();	
    }
    //登录账号添加
    function admin_add(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input();
	    	$data['salt'] = '111';
	    	$data['password'] = md5($data['password'].$data['salt']);
	    	$data['time'] = time();
	    	unset($data['password2']);
	    	$admin = new Admin;
	    	if($admin->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'添加成功']);
	    		exit;
	    	}else{
	    		echo json_encode(['sta'=>0,'msg'=>'添加失败']);
	    		exit;
	    	}
	    }
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
    function admin_edit(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	$admin = Admin::get($data['id']);
	    	if($data['password']){
	    		$data['password'] = md5($data['password'].$admin['salt']);
	    	}else{
	    		unset($data['password']);
	    	}
	    	if($admin->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
	    }
	    $data = input();
	    $id = $data['id'];
    	//角色
    	$role = Role::select();
    	$rolelist = [];
    	foreach ($role as $key => $value) {
    		$rolelist[$value['id']] = $value['name'];
    	}
		$this->assign('rolelist',$rolelist);

		//登录账号
		$admin = Admin::get($id);
		$this->assign('admin',$admin);

    	return $this->fetch();	
    }
    //登录账号删除
    function admin_del(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
		$data = input();
		$id = $data['id'];
    	if($id&&is_numeric($id)){
    		$admin = Admin::get($id);
    		if($admin->status==1)$admin->status=0;
    		elseif($admin->status===0)$admin->status=1;

    		if($admin->save()){
    			echo json_encode(['sta'=>1,'msg'=>'操作成功']);
	    		exit;
    		}else{
    			echo json_encode(['sta'=>0,'msg'=>'操作失败']);
	    		exit;
    		}
    	}else{
    		echo json_encode(['sta'=>0,'msg'=>'非法操作']);
	    	exit;
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
