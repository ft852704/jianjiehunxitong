<?php
namespace app\admin\controller;
use app\admin\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\admin\model\Shop as ShopModel;
use app\admin\model\Coupons as CouponsModel;

class Coupons extends Base
{
	public function _initialize()
    {
    }
	//抵用券列表
    public function index()
    {
    	$data = input();
    	$where = '1 = 1';
    	if(isset($data['code'])&&$data['code']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		//$where =  array('name'=>array('like','%'.$data['name'].'%'));
    		//$where['s.area'] = array('like','%'.$data['code'].'%');
    		$where.= ' and s.area like "%'.$data['code'].'%"';
    	}else{
    		$data['code'] = '';
    	}
    	if(isset($data['start_time'])&&$data['start_time']){
    		$where.= ' and c.time >='.strtotime($data['start_time']);
    	}else{
    		$data['start_time'] = '';
    	}
    	if(isset($data['state'])&&$data['state']){
    		$where.= ' and c.state ='.$data['state'];
    	}else{
    		$data['state'] = 0;
    	}
    	if(isset($data['end_time'])&&$data['end_time']){
    		$where.= ' and c.time <='.strtotime($data['end_time']);
    	}else{
    		$data['end_time'] = '';
    	}
    	
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = CouponsModel::field('*,c.id as cid,c.status as status,c.time as ctime,c.price as cprice')->alias('c')->join('shop s','c.shop_id=s.id','left')->where($where)->order('c.time DESC')->paginate(15,false,array('query'=>$data));
    	$count = CouponsModel::field('*,c.id as cid,c.status as status,c.time as ctime,c.price as cprice')->alias('c')->join('shop s','c.shop_id=s.id','left')->where($where)->count();
    	$this->assign('count',$count);
    	$this->assign('list',$list);
    	$this->assign('status',['未验证','已验证']);
    	$this->assign('data',$data);
    	$this->assign('area',$this->area);

    	$shop = ShopModel::get(Session::get('shop_id'));
    	$this->assign('shop',$shop);

    	return $this->fetch();
    }
    //店铺列表
    function shoplist(){
    	$data = input();
    	$where = '1 = 1';
    	if(isset($data['code'])&&$data['code']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		//$where =  array('name'=>array('like','%'.$data['name'].'%'));
    		//$where['s.area'] = array('like','%'.$data['code'].'%');
    		$where.= ' and user_name like "%'.$data['code'].'%"';
    	}else{
    		$data['code'] = '';
    	}
    	if(isset($data['start_time'])&&$data['start_time']){
    		$where.= ' and time >='.strtotime($data['start_time']);
    	}else{
    		$data['start_time'] = '';
    	}
    	if(isset($data['end_time'])&&$data['end_time']){
    		$where.= ' and time <='.strtotime($data['end_time']);
    	}else{
    		$data['end_time'] = '';
    	}
    	$shop = ShopModel::order('id DESC')->where($where)->paginate(15,false,array('query'=>$data));
    	$this->assign('list',$shop);

    	$this->assign('status',['未验证','已验证']);
    	$this->assign('data',$data);
    	return $this->fetch();
    }
    //转换商家状态
    function shopDel(){
    	$data = input();
		$id = $data['id'];
    	if($id&&is_numeric($id)){
    		$coupons = ShopModel::get($id);
    		if($coupons->status==1)$coupons->status=0;
    		elseif($coupons->status===0)$coupons->status=1;

    		if($coupons->save()){
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
    //编辑商家
    function editShop(){
    	$data = input();
    	$shop = ShopModel::get($data['id']);
    	if(Request::instance()->post()){
	    	$data['time'] = time();
	    	if($data['password']){
	    		$data['password'] = md5($data['password']);
	    	}else{
	    		unset($data['password']);
	    	}
	    	
    		if($shop->save($data)){
				echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
    	}else{
    		$this->assign('status',['未验证','已验证']);
    		$this->assign('shop',$shop);
    		return $this->fetch();
    	}
    }
    //添加商家
    function addShop(){
    	if(Request::instance()->post()){
	    	$data = input();
	    	$shop = new ShopModel;
	    	$data['time'] = time();
	    	$data['password'] = md5($data['password']);
	    	/*$head = $this->files_upload('head');
    		if($head['status']){
    			$data['head'] = $head['path'][0];
    		}*/
	    	if($shop->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'添加成功']);
	    		exit;
	    	}else{
	    		echo json_encode(['sta'=>0,'msg'=>'添加失败']);
	    		exit;
	    	}
	    }
	    $this->assign('area',$this->area);
    	return $this->fetch();
    }
    //验证兑换码
    function couponsDel(){
    	$data = input();
		$id = $data['id'];
    	if($id&&is_numeric($id)){
    		$coupons = CouponsModel::get($id);
    		if($coupons->status==1)$coupons->status=0;
    		elseif($coupons->status===0)$coupons->status=1;

    		if($coupons->save()){
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
    //抵用券编辑
    function couponsedit(){
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	$coupons = CouponsModel::get($data['id']);
	    	
	    	if($coupons->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
	    }
	    $data = input();
	    $id = $data['id'];

		//
		$coupons = CouponsModel::get($id);
		$shop = ShopModel::get($coupons['shop_id']);
		$this->assign('shop',$shop);
		$this->assign('coupons',$coupons);

    	return $this->fetch();
    }
    //预约单添加
    function add(){
    	exit;
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input();
	    	if($data['password']){
	    		$data['password'] = md5($data['password']);
	    	}else{
	    		$data['password'] = md5(123456);
	    	}
	    	$data['time'] = time();
	    	$pic = isset($data['pic'])?$data['pic']:[];
	    	unset($data['pic']);
	    	/*$head = $this->files_upload('head');
    		if($head['status']){
    			$data['head'] = $head['path'][0];
    		}*/
    		dump($data);
	    	$professional = new ProfessionalModel;
	    	if($professional->save($data)){
	    		if(!empty($pic)){
	    			foreach ($pic as $k => $v) {
	    				dump(ProfessionalPic::insert(['professional_id'=>$professional['id'],'url'=>$v,'time'=>time(),'sort'=>$k]));
	    			}
	    		}
	    		echo json_encode(['sta'=>1,'msg'=>'添加成功']);
	    		exit;
	    	}else{
	    		echo json_encode(['sta'=>0,'msg'=>'添加失败']);
	    		exit;
	    	}
	    }

    	return $this->fetch();	
    }
    //预约单编辑
    function edit(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	$bespeak = BespeakModel::get($data['id']);
	    	$user = User::get($data['user_id']);
	    	$pro = Professional::get($data['professional_id']);
	    	$data['marry_date'] = strtotime($data['marry_date']);
	    	$data['bespeak_date'] = strtotime($data['bespeak_date']);
	    	if(empty($user)){
	    		$this->error('新人不存在，请查询新人id');
	    	}
	    	if(empty($pro)){
	    		$this->error('职业人不存在，请查询职业人id');
	    	}
	    	$data['professional_name'] = $pro['name'];
	    	$data['professional_mobile'] = $pro['mobile'];
	    	$data['professional_type'] = $pro['type'];
	    	$data['user_name'] = $user['name'];
	    	$data['user_mobile'] = $user['mobile'];
	    	$data['user_sex'] = $user['sex'];
	    	
	    	if($bespeak->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'修改成功']);
	    		exit;
	    	}else{
		        echo json_encode(['sta'=>0,'msg'=>'修改失败']);
	    		exit;
	    	}
	    }
	    $data = input();
	    $id = $data['id'];

		//
		$bespeak = BespeakModel::get($id);
		$bespeak['user_sex'] = $bespeak['user_sex']==1?'男':'女';
		$bespeak['professional_type'] = $this->professional_type[$bespeak['professional_type']];
		$this->assign('bespeak',$bespeak);
		
		$this->assign('state',$this->bespeak_state);

    	return $this->fetch();	
    }
    //预约单弃用
    function del(){
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
		$data = input();
		$id = $data['id'];
    	if($id&&is_numeric($id)){
    		$user = BespeakModel::get($id);
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
