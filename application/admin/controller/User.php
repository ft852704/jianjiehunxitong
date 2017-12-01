<?php
namespace app\admin\controller;
use app\admin\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\admin\model\User as UserModel;

class User extends Base
{
	public function _initialize()
    {
        
    }
	//账号列表
    public function index()
    {
    	$data = input();
    	$where = [];
    	if(isset($data['name'])&&$data['name']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		$where =  array('name'=>array('like','%'.$data['name'].'%'));
    	}
    	
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = UserModel::where($where)->order('status DESC,id DESC')->paginate(15,false,array('query'=>$data));

    	$this->assign('list',$list);
    	$data['start_time'] = '';
    	$data['end_time'] = '';
    	$this->assign('data',$data);
    	return $this->fetch();
    }
    //欢迎页
    public function welcome(){
    	return $this->fetch();
    }
    //账号添加
    function user_add(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input();
	    	$data['password'] = md5($data['password']);
	    	$data['time'] = time();
	    	$data['marry_date'] = strtotime($data['marry_date']);
	    	$user = new UserModel;
	    	if($user->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'添加成功']);
	    		exit;
	    	}else{
	    		echo json_encode(['sta'=>0,'msg'=>'添加失败']);
	    		exit;
	    	}
	    }
	    $this->assign('from',$this->from);

    	return $this->fetch();	
    }
    //账号编辑
    function user_edit(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	$data['marry_date'] = strtotime($data['marry_date']);
	    	$admin = UserModel::get($data['id']);
	    	if($data['password']){
	    		$data['password'] = md5($data['password']);
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

		//登录账号
		$user = UserModel::get($id);
		$this->assign('user',$user);
	    $this->assign('from',$this->from);

    	return $this->fetch();	
    }
    //账号弃用
    function user_del(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
		$data = input();
		$id = $data['id'];
    	if($id&&is_numeric($id)){
    		$user = UserModel::get($id);
    		if($user->status==1)$user->status=0;
    		elseif($user->status===0)$user->status=1;

    		if($user->save()){
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
}
