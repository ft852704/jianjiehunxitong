<?php
namespace app\admin\controller;
use app\admin\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\admin\model\Order as OrderModel;
use app\admin\model\Orders as OrdersModel;
use app\admin\model\Bespeak;
use app\admin\model\User;
use app\admin\model\Professional;
use app\admin\model\Service;
use app\admin\model\ServiceTemplate;
use app\admin\model\TrackUser as TrackUserModel;

class Order extends Base
{
	public $tax = 0;//平台佣金比例 人员费
	public $pepole_tax = 0.05;//布置费比例
	public function _initialize()
    {
        
    }
	//预约单列表
    public function index()
    {
    	$data = input();
    	$where = [];
    	if(isset($data['type'])&&$data['type']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		//$where =  array('name'=>array('like','%'.$data['name'].'%'));
    		$where = [
    			'type' => $data['type'],
    		];
    	}
    	
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = OrderModel::where($where)->order('status DESC,id DESC')->paginate(15,false,array('query'=>$data));

    	$this->assign('list',$list);
    	$data['start_time'] = '';
    	$data['end_time'] = '';
    	$this->assign('data',$data);

    	$this->assign('order_state',$this->order_state);

		$this->assign('order_type',$this->order_type);
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
	    	$pro = Professional::where(['mobile'=>$data['linkman_phone']])->find();
	    	if(empty($user)){
	    		$this->error('新人不存在，请查询新人手机号');
	    	}
	    	if(empty($pro)){
	    		$this->error('职业人不存在，请查询职业人手机号');
	    	}
	    	$data['user_id'] = $user['id'];
	    	$data['user_name'] = $user['name'];
	    	$data['linkman_name'] = $pro['name'];
	    	$data['user_phone'] = $user['mobile'];
	    	$data['linkman_id'] = $pro['id'];
	    	$data['linkman_phone'] = $pro['mobile'];
	    	$data['time'] = time();
	    	$data['service_id']=15;
	    	$data['service_time'] = strtotime($data['service_time']);
	    	$data['order_no'] = $this->getOrderNo();
	    	$data['type'] = 2;
	    	$data['state'] = 0;
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
	    $this->assign('order_state',$this->order_state);
    	return $this->fetch();	
    }
    //布置费订单修改
    public function arrangement_edit($id=0){
    	$order = OrderModel::get($id);
    	if(Request::instance()->post()){
    		$data = input();
    		//$user = User::get($data['user_phone']);
	    	//$pro = Professional::get($data['linkman_phone']);
	    	//if(empty($user)){
	    	//	$this->error('新人不存在，请查询新人手机号');
	    	//}
	    	//if(empty($pro)){
	    	//	$this->error('职业人不存在，请查询职业人手机号');
	    	//}
	    	//$data['user_name'] = $user['name'];
	    	//$data['user_phone'] = $user['mobile'];
	    	//$data['linkman_name'] = $pro['name'];
	    	//$data['linkman_phone'] = $pro['mobile'];
	    	$data['time'] = time();
	    	$data['service_time'] = strtotime($data['service_time']);
	    	$data['commission'] = $this->pepole_tax*$data['price'];
	    	$data['total'] = $data['commission']+$data['price'];
	    	$order = OrderModel::get($id);
    		if($order->save($data)){
				echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
    	}else{
    		$this->assign('order',$order);
	    	$this->assign('order_state',$this->order_state);
    		return $this->fetch('/order/arrangement_edit');
    	}
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
	    	$user = User::where(['user_phone'=>$data['user_phone']])->find();
			$pro = Professional::where(['linkman_phone'=>$data['professional_phone']])->find();
			
	    	$service = Service::get($data['service_id']);
	    	if(empty($user)){
	    		$this->error('新人不存在，请查询新人id');
	    	}
	    	if(empty($pro)){
	    		$this->error('职业人不存在，请查询职业人id');
	    	}
	    	if(empty($service)){
	    		$this->error('服务不存在，请查询服务id');
	    	}

			//$commission = round($data['price']*$this->tax,2);

	    	$data['user_name'] = $user['name'];
	    	$data['user_phone'] = $user['mobile'];
	    	$data['linkman_name'] = $pro['name'];
	    	$data['linkman_phone'] = $pro['mobile'];
	    	//$data['total'] = $commission+$data['price'];
	    	//$data['commission'] = $commission;

	    	$order = OrderModel::get($data['id']);
	    	$data['service_time'] = strtotime($data['service_time']);

	    	if($order->save($data)){
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
		//服务
		$service = $this->getService($order['linkman_id']);
		$this->assign('service',$service);
		
		$this->assign('state',$this->order_state);

		$this->assign('sex',$this->sex);

		$this->assign('order_state',$this->order_state);

    	return $this->fetch('/order/professional_order_edit');
    }
    public function getService($id){
    	$data['professional_id'] = $id;
    	$service = Service::alias('s')->field('s.id,st.name')->join('service_template st','s.template_id=st.id','left')->where(['s.professional_id'=>$data['professional_id']])->select();
    	$list = [];
    	foreach ($service as $k => $v) {
    		$list[$v['id']] = $v['name'];
    	}
    	return $list;
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
    //根据预约单添加人员费订单
    public function professional_order_add($iscreate=0){
		$data = input();
    	if(!$iscreate){
    		$bespeak = Bespeak::get($data['id']);

			$bespeak['professional_type'] = $this->professional_type[$bespeak['professional_type']];

			$service = Service::get($bespeak['service_id']);
			$service_template = ServiceTemplate::get($service['template_id']);
			$bespeak['service_name'] = $service_template['name'];
			$bespeak['price'] = $service['price'];

			$this->assign('bespeak',$bespeak);
			
			$this->assign('state',$this->bespeak_state);

    		return $this->fetch();
    	}else{
			$bespeak = Bespeak::get($data['id']);
			$commission = round($data['price']*$this->tax,2);
			$user = User::get($bespeak['user_id']);
			$data['marry_date'] = $data['marry_date']?$data['marry_date']:date('Y-m-d',$user['marray_date']);
			$orderdata = [
				'order_no' => $this->getOrderNo(),
				'type' => 1,
				'linkman_id' => $bespeak['professional_id'],
				'linkman_name' => $data['professional_name'],
				'linkman_phone' => $data['professional_mobile'],
				'user_id' => $bespeak['user_id'],
				'user_name' => $data['user_name'],
				'user_phone' => $data['user_mobile'],
				'service_time' => strtotime($data['marry_date']),
				'marry_date' => strtotime($data['marry_date']),
				'hotel' => $data['hotel'],
				'banquet_type' => $data['banquet_type'],
				'address' => $data['address'],
				'user_mark' => $data['user_mark'],
				'state' => 1,
				'activer_name' => $data['activer_name'],
				'activer_id' => $bespeak['activer_id'],
				//'marry_date' => ,
				'price' => $data['price'],
				'time' => time(),
				'bespeak_id' => $data['id'],
				'service_id' => $bespeak['service_id'],
				'total' => $data['price']+$commission,
				'commission' => $commission,
			];
			$order = new OrderModel;
			if($order->save($orderdata)){
				//更改用户状态为已下单
				$user = new TrackUserModel;
				$user->where(['mobile'=>$data['user_mobile']])->updata(['state'=>5]);

				$bespeak->state = 4;
				$bespeak->save();
				echo json_encode(['sta'=>1,'msg'=>'生成成功']);
	    		exit;
			}else{
				echo json_encode(['sta'=>0,'msg'=>'生成失败']);
	    		exit;
			}
    	}
    }
    //单独添加人员费订单
    public function professional_order_add1(){
		$data = input();
    	if(!Request::instance()->post()){
			$this->assign('professional_type',$this->professional_type);
			
    		return $this->fetch();	
    	}else{
    		$data = input();
			$user = User::where(['user_phone'=>$data['user_phone']])->find();
			$professional = Professional::where(['linkman_phone'=>$data['professional_phone']])->find();
			$service = Service::get($data['service_id']);
	    	if(empty($user)){
	    		$this->error('新人不存在，请查询新人手机号');
	    	}
	    	if(empty($pro)){
	    		$this->error('职业人不存在，请查询职业人手机号');
	    	}
	    	if(empty($service)){
	    		$this->error('服务不存在，请查询服务id');
	    	}
			$commission = round($data['price']*$this->tax,2);
			$orderdata = [
				'order_no' => $this->getOrderNo(),
				'type' => 1,
				'linkman_id' => $data['professional_id'],
				'linkman_name' => $professional['name'],
				'linkman_phone' => $professional['mobile'],
				'user_id' => $data['user_id'],
				'user_name' => $user['name'],
				'user_phone' => $user['mobile'],
				'service_time' => strtotime($data['marry_date']),
				'hotel' => $data['hotel'],
				'banquet_type' => $data['banquet_type'],
				'address' => $data['address'],
				'user_mark' => $data['user_mark'],
				'state' => 1,
				'activer_name' => $data['activer_name'],
				'activer_id' => 0,
				//'marry_date' => ,
				'time' => time(),
				'bespeak_id' => 0,
				'service_id' => $service['id'],
				'total' => $data['price']+$commission,
				'commission' => $commission,
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
