<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\index\model\Bespeak as BespeakModel;
use app\index\model\Professional;
use app\index\model\Service;
use app\index\model\User;
use app\index\model\UserLoginLog;

class Bespeak extends Base
{
	public function _initialize()
    {
        
    }
	//预约单列表
    public function index()
    {
    	$data = input();
    	$where['professional_id'] = Session::get('professional_id');
    	$where['state'] = ['neq',1];
    	$where['status'] = 1;
    	if(isset($data['name'])&&$data['name']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		//$where =  array('name'=>array('like','%'.$data['name'].'%'));
    		$where['name'] = array('like','%'.$data['name'].'%');
    	}
    	
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = BespeakModel::where($where)->order('id DESC')->paginate(15,false,array('query'=>$data));

    	$this->assign('list',$list);
    	$data['start_time'] = '';
    	$data['end_time'] = '';
    	$this->assign('data',$data);
    	return $this->fetch();
    }
    //发起预约
    public function start_bespeak(){
    	$data = input();
    	if(Request::instance()->post()){
    		$user = User::where(['name'=>$data['mobile']])->find();
	    		if(empty($user)){
	    			$duser = [
	    			'name' => $data['mobile'],
	    			'nick' => $data['real_name'],
	    			'sex' => $data['sex'],
	    			'mobile' => $data['mobile'],
	    			'real_name' => $data['real_name'],
	    			'hotel' => $data['hotel'],
	    			'area' => $data['area'],
	    			'address' => $data['address'],
	    			'marry_date' => strtotime($data['marry_date']),
	    			'time' => time(),
	    			'password' => md5($data['mobile']),
	    		];
	    		$user = new User;
	    		$re = $user->save($duser);
	    		if(!$re){
					$this->error('预约失败');
	    		}
    		}
    		$professional = Professional::get($data['professional_id']);

    		$bespeak = new BespeakModel;
    		$dbespeak = [
    			'user_id' => $user['id'],
    			'user_sex' => $user['sex'],
    			'user_name' => $user['real_name'],
    			'user_mobile' => $user['mobile'],
    			'professional_id' => $professional['id'],
    			'professional_name' => $professional['name'],
    			'professional_type' => $professional['type'],
    			'professional_mobile' => $professional['mobile'],
    			'bespeak_date' => strtotime($data['bespeak_date']),
    			'user_mark' => $data['user_mark'],
    			'banquet_type' => $data['banquet_type'],
    			'address' => $data['address'],
    			'hotel' => $data['hotel'],
    			'marry_date' => strtotime($data['marry_date']),
    			'bespeak_address' => '春熙路时代广场A座806a（UR旁边巷子进来的大厅左侧电梯）',
    			'service_id' => $data['service_id'],
    		];
    		if($re = $bespeak->save($dbespeak)){
    			Session::set('user_id',$bespeak['user_id']);
    			Session::set('user_nick',$bespeak['user_name']);
    			Session::set('real_name',$bespeak['user_name']);
				UserLoginLog::insert(['user_id'=>$bespeak['user_id'],'ip'=>$_SERVER["REMOTE_ADDR"],'time'=>time()]);

    			$this->redirect('bespeak/bespeak_success', array('professional_id' => $data['professional_id'],'service_id'=>$data['service_id']));
    		}else{
    			$this->error('预约失败');
    		}
    	}
    	//此处可以分两种情况，1已登录，2未登录
    	//未登录
    	$professional = Professional::get($data['id']);
    	$this->assign('professional',$professional);
    	$service = Service::get($data['sid']);
    	$this->assign('service',$service);
    	$this->assign('pro_type',$this->pro_type);
    	//已登录需要用户基础数据
    	if(Session::get('user_id')){
    		$user = User::get(Session::get('user_id'));
    	}else{
    		$user = [
    			'name' => '',
    			'hotel' => '',
    			'address' => '',
    			'marry_date' => time(),
    			'mobile' => '',
    			'sex' => 1,
    			'real_name' => '',
    		];
    	}
    	$this->assign('user',$user);
    	return $this->fetch();
    }
    //预约成功提醒页面
    function bespeak_success(){
    	$data = input();
    	$professional = Professional::get($data['professional_id']);
    	$this->assign('professional',$professional);
    	$service = Service::get($data['service_id']);
    	$this->assign('service',$service);
    	$this->assign('pro_type',$this->pro_type);
    	return $this->fetch();
    }
    //预约单编辑
    function edit(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//
    	/*if(Request::instance()->post()){
	    	$data = input('post.');
	    	$bespeak = BespeakModel::get($data['id']);
	    	if($bespeak->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
	    }*/
	    $data = input();
	    $id = $data['id'];

		//
		$bespeak = BespeakModel::get($id);
		$bespeak['user_sex'] = $bespeak['user_sex']==1?'男':'女';
	    //职业人类型（1，策划师，2化妆师，3摄像师，4主持人,5摄影师）
	    $professional = [
			1 => '策划师',
			2 => '化妆师',
			3 => '摄像师',
			4 => '主持人',
			5 => '摄影师',
		];
		$bespeak['professional_type'] = $professional[$bespeak['professional_type']];
		$this->assign('bespeak',$bespeak);
		
		//预约状态  状态（1，未审核，2，已审核，3已完成，4，已生成订单）
		$state = [
			1 => '未审核',
			2 => '已审核',
			3 => '已完成',
			4 => '已生成订单',
		];
		$this->assign('state',$state);

    	return $this->fetch();	
    }
    //图片上传  职业人头像处理
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
	public function files_upload($name="photo",$save_dir="professional_head",$allow_suffix=array('jpg','jpeg','gif','png'))
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
}
