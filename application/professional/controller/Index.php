<?php
namespace app\professional\controller;
use app\professional\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\professional\model\Professional;
use app\professional\model\ProfessionalLoginLog;

class Index extends Base
{
	//主页
    public function index()
    {
		//$this->is_power();
		//$family_name = Session::get('family_name');
		//$this->assign('family_name',$family_name);
    	return $this->fetch('index');
    }
    //登录
    public function login(){
    	if(Request::instance()->post()){
    		$data = input();
    		$data['user_name'] = trim($data['user_name']);
    		$data['password'] = md5(trim($data['password']));
    		$login = Professional::where($data)->find();
    		if($login){
	    		if(!$login['status']){
	    			$this->error('账号未启用，请联系管理员启用后再登录', 'Index/login');
	    		}
    			Session::set('professional_id',$login['id']);
		        Session::set('professional_name',$login['name']);
		        Session::set('professional_nick',$login['nick']);
		        Session::set('professional_type',$login['type']);
				ProfessionalLoginLog::insert(['professional_id'=>$login['id'],'ip'=>$_SERVER["REMOTE_ADDR"],'time'=>time()]);
				$this->success('登录成功', 'Index/index');
    		}else{
    			$this->error('用户名或密码错误，请重新输入', 'Index/login');
    		}
    	}else{
    		return $this->fetch('index/login');	
    	}
    }
    //退出登录
    function loginout(){
    	Session::delete('professional_id');
    	session_destroy();
    	$this->success('请登录', 'Index/login');
    }
    //欢迎页
    public function welcome(){
    	return $this->fetch();
    }
    //修改个人资料
    public function editPersonal(){
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	$professional = Professional::get(Session::get('professional_id'));
	    	if($data['password']){
	    		$data['password'] = md5($data['password']);
	    	}else{
	    		unset($data['password']);
	    	}
	    	if($professional->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
	    }
	    $data = input();
	    $id = Session::get('professional_id');

		//登录账号
		$user = Professional::get($id);
		$this->assign('user',$user);

    	return $this->fetch();
    }
}
 