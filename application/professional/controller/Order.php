<?php
namespace app\professional\controller;
use app\professional\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\professional\model\Order as OrderModel;
use app\professional\model\Orders as OrdersModel;
use app\professional\model\Bespeak;
use app\professional\model\User;
use app\professional\model\Professional;

class Order extends Base
{
	public function _initialize()
    {
        
    }
	//订单单列表
    public function index()
    {
    	$data = input();
    	$where = [
    		'linkman_id' => Session::get('professional_id'),
    	];
    	if(isset($data['name'])&&$data['name']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		$where =  array('name'=>array('like','%'.$data['name'].'%'));
    	}
    	
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = OrderModel::where($where)->order('status DESC,id DESC')->paginate(15,false,array('query'=>$data));
    	foreach ($list as $k => $v) {
    		$user = User::get($v['user_id']);
    		$list[$k]['client_ip'] = $user['real_name'];
    	}
    	$this->assign('list',$list);
    	$data['start_time'] = '';
    	$data['end_time'] = '';
    	$this->assign('data',$data);
    	$this->assign('role',$this->role);
    	$this->assign('professional_type',$this->professional_type);
    	$this->assign('order_state',$this->order_state);
    	return $this->fetch();
    }
    //布置费订单添加
    function arrangement_add(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input();
	    	$user = User::where(['mobile'=>$data['user_phone']])->find();
	    	$data['time'] = time();
	    	$data['order_no'] = $this->getOrderNo();
	    	$data['type'] = 2;
	    	$data['user_id'] = $user['id'];
	    	$data['linkman_id'] = Session::get('professional_id');
	    	$data['linkman_name'] = Session::get('professional_name');
	    	//获取职业人信息
	    	$professional = Professional::get(Session::get('professional_id'));
	    	$data['linkman_phone'] = $professional['mobile'];
	    	$data['state'] = 0;
	    	$data['service_id'] = 15;//布置费订单默认服务项目
	    	$data['service_time'] = strtotime($data['service_time']);
	    	$data['commission'] = $this->pepole_tax*$data['price'];
	    	$data['total'] = $data['commission']+$data['price'];

	    	/*$head = $this->files_upload('head');
    		if($head['status']){
    			$data['head'] = $head['path'][0];
    		}*/
	    	$order = new OrderModel;
	    	if($order->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'添加成功']);
	    		exit;
	    	}else{
	    		echo json_encode(['sta'=>0,'msg'=>'添加失败']);
	    		exit;
	    	}
	    }

    	return $this->fetch();	
    }
    //订单编辑
    public function edit(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//
    	$data = input();
    	$order = OrderModel::get($data['id']);
    	if(!$order['type']){
    		return $this->error('非法访问');
    	}
    	if($order['type']==1){
    		return $this->professional_order_edit($data['id']);
    	}elseif($order['type']==2){
    		return $this->arrangement_edit($data['id']);
    	}else{
    		return $this->error('非法访问');
    	}
    }
    //人员费订单修改
    public function professional_order_edit($id=0){
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	$bespeak = OrderModel::get($data['id']);
	    	if($bespeak->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
	    }

	    if(!$id){
	    	return $this->error('非法访问');
	    }
		//订单数据
		$order = OrderModel::get($id);
		$this->assign('order',$order);
		//新人数据
		$user = User::get($order['user_id']);
		$this->assign('user',$user);

		
    	$this->assign('role',$this->role);
		$this->assign('state',$this->order_state);

    	return $this->fetch('/order/professional_order_edit');	
    }
    //布置费订单修改
    public function arrangement_edit($id=0){
    	$order = OrderModel::get($id);
    	if(Request::instance()->post()){
    		$data = input();
	    	$data['state'] = 0;
    		if($order->save($data)){
				echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
    	}else{
    		$this->assign('order',$order);
    		$this->assign('role',$this->role);
			$this->assign('state',$this->order_state);
    		return $this->fetch('/order/arrangement_edit');
    	}
    }
    //订单弃用
    function del(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
		$data = input();
		$id = $data['id'];
    	if($id&&is_numeric($id)){
    		$user = OrderModel::get($id);
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
    //添加人员费订单
    public function professional_order_add($iscreate=0){
		$data = input();
    	if(!$iscreate){
    		$bespeak = Bespeak::get($data['id']);
    		$this->assign('bespeak',$bespeak);
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
    	}else{
			$bespeak = Bespeak::get($data['id']);
			$orderdata = [
				'order_no' => $this->getOrderNo(),
				'type' => 1,
				'linkman_id' => $bespeak['professional_id'],
				'linkman_name' => $data['professional_name'],
				'linkman_phone' => $data['professional_mobile'],
				'user_id' => $bespeak['user_id'],
				'user_name' => $data['user_name'],
				'user_phone' => $data['user_mobile'],
				'service_time' => $data['marry_date'],
				'total' => $data['total'],
				'hotel' => $data['hotel'],
				'banquet_type' => $data['banquet_type'],
				'address' => $data['address'],
				'user_mark' => $data['user_mark'],
				'state' => 1,
				'activer_name' => $data['activer_name'],
				'activer_id' => $bespeak['activer_id'],
				//'marry_date' => ,
				'time' => time(),
				'bespeak_id' => $data['id'],
			];
			$order = new OrderModel;
			if($order->save($orderdata)){
				echo json_encode(['sta'=>1,'msg'=>'生成成功']);
	    		exit;
			}else{
				echo json_encode(['sta'=>0,'msg'=>'生成失败']);
	    		exit;
			}
    	}
    }
    public function getOrderNo(){
    	return date('ymdHis').rand(1000,9999);
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
