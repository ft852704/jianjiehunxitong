<?php
namespace app\admin\controller;
use app\admin\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\admin\model\Picture as PictureModel;

class Picture extends Base
{
	public function _initialize()
    {
        
    }
	//图片列表
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
    //文件上传类
    public function uploadfile(){
    	$url = ROOT_PATH . 'public' . DS . 'file';
    	$root = DS.'file'.DS;
    	// 5 minutes execution time
		@set_time_limit(5 * 60);
	    // 获取表单上传文件 例如上传了001.jpg
	    $file = request()->file('file');
	    // 移动到框架应用根目录/public/file/ 目录下
	    $info = $file->validate(['size'=>1024*1024*20,'ext'=>'txt,xls'])->move($url,true,false);
	    if($info){
	        // 成功上传后 获取上传信息
	        // 输出 jpg
	        $ex = $info->getExtension();
	        // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
	        $url = $info->getSaveName();
	        // 输出 42a79759f284b767dfcb2a0197904287.jpg
	        $filename = $info->getFilename(); 
	        echo json_encode(['status'=>1,'ex'=>$ex,'url'=>$root.$url]);
	        exit;
	    }else{
	        // 上传失败获取错误信息
	        echo json_encode(['status'=>0,'msg'=>$file->getError()]);
	        exit;
	    }
    }
    //图片上传类
    public function upload($url=''){
    	switch ($url) {
    		case 'head':
    			$url = ROOT_PATH . 'public' . DS . 'head';
    			$root = DS.'head'.DS;
    			break;
    		
    		default:
    			$url = ROOT_PATH . 'public' . DS . 'uploads';
    			$root = DS.'uploads'.DS;
    			break;
    	}
	    // 5 minutes execution time
		@set_time_limit(5 * 60);
	    // 获取表单上传文件 例如上传了001.jpg
	    $file = request()->file('file');
	    // 移动到框架应用根目录/public/uploads/ 目录下
	    $info = $file->validate(['size'=>1024*1024*20,'ext'=>'jpg,png,gif'])->move($url,true,false);
	    if($info){
	        // 成功上传后 获取上传信息
	        // 输出 jpg
	        $ex = $info->getExtension();
	        // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
	        $url = $info->getSaveName();
	        // 输出 42a79759f284b767dfcb2a0197904287.jpg
	        $filename = $info->getFilename(); 
	        echo json_encode(['status'=>1,'ex'=>$ex,'url'=>$root.$url]);
	        exit;
	    }else{
	        // 上传失败获取错误信息
	        echo json_encode(['status'=>0,'msg'=>$file->getError()]);
	        exit;
	    }
	}
	//图片上传类(上传头像)
    public function uploadhead($url=''){
    	switch ($url) {
    		case 'head':
    			$url = ROOT_PATH . 'public' . DS . 'head';
    			$root = DS.'head'.DS;
    			break;
    		
    		default:
    			$url = ROOT_PATH . 'public' . DS . 'uploads';
    			$root = DS.'uploads'.DS;
    			break;
    	}
	    // 5 minutes execution time
		@set_time_limit(5 * 60);
	    // 获取表单上传文件 例如上传了001.jpg
	    $file = request()->file('file');
	    // 移动到框架应用根目录/public/uploads/ 目录下
	    $info = $file->validate(['size'=>1024*1024*20,'ext'=>'jpg,png,gif'])->move($url,true,false);
	    if($info){
	        // 成功上传后 获取上传信息
	        // 输出 jpg
	        $ex = $info->getExtension();
	        // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
	        $url = $info->getSaveName();
	        // 输出 42a79759f284b767dfcb2a0197904287.jpg
	        $filename = $info->getFilename(); 
	        echo $root.$url;exit;
	        echo json_encode(['status'=>1,'ex'=>$ex,'url'=>$url]);
	        exit;
	    }else{
	        // 上传失败获取错误信息
	        echo json_encode(['status'=>0,'msg'=>$file->getError()]);
	        exit;
	    }
	}
}
