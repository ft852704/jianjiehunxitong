<?php
namespace app\shop\controller;
use app\shop\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\shop\model\Shop as ShopModel;
use app\shop\model\Coupons;

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
    		//$data['user_name'] = trim($data['user_name']);
    		$data['password'] = md5(trim($data['password']));
    		$login = ShopModel::where($data)->find();
    		if($login){
	    		if(!$login['status']){
	    			$this->error('账号未启用，请联系管理员启用后再登录', 'Index/login');
	    		}
    			Session::set('shop_id',$login['id']);
		        Session::set('shop_name',$login['name']);
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
    	Session::delete('shop_id');
    	session_destroy();
    	$this->success('请登录', 'Index/login');
    }
    //欢迎页
    public function welcome(){
    	return $this->fetch();
    }
    //抵用券首页
    function couponsList(){
    	$data = input();
    	$where['shop_id'] = Session::get('shop_id');
    	$where = ' shop_id = '.Session::get('shop_id');
    	if(isset($data['code'])&&$data['code']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		//$where =  array('name'=>array('like','%'.$data['name'].'%'));
    		$where.=' and code like "%'.$data['code'].'%"';
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
    	
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = Coupons::where($where)->order('id DESC')->paginate(15,false,array('query'=>$data));
    	$count = Coupons::where($where)->count();
    	$this->assign('count',$count);
    	//隐藏手机号
    	foreach ($list as $k => $v) {
    		$list[$k]['phone'] = substr_replace($list[$k]['phone'],'****',3,4);
    	}
    	$this->assign('list',$list);
    	$this->assign('status',['未验证','已验证']);
    	$this->assign('data',$data);

    	$shop = ShopModel::get(Session::get('shop_id'));
    	$this->assign('shop',$shop);

    	return $this->fetch();
    }
    //验证兑换码
    function couponsDel(){
    	$data = input();
		$id = $data['id'];
    	if($id&&is_numeric($id)){
    		$user = Coupons::get($id);
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
    //商家的推广二维码
    public function newConponsCode(){
    	$this->assign('shop_id',Session::get('shop_id'));
    	$this->assign('domian',$_SERVER['SERVER_NAME']);
    	return $this->fetch();
    }
    //用抵用券收集用户手机号
    public function getphonebycoupons(){
    	$data = input();
    	if(!isset($data['shop_id'])){
    		$this->error('非法访问');
    	}
    	$this->assign('shop_id',$data['shop_id']);
    	$this->assign('time',time());

    	return $this->fetch();
    }
    //返回抵用码
    public function getCoupons(){
    	$data = input();
    	if(!isset($data['shop_id'])||!$data['shop_id']){
    		echo json_encode(['status'=>0,'msg'=>'非法访问']);
    		exit;
    	}
    	if(!isset($data['phone'])||!$data['phone']){
    		echo json_encode(['status'=>0,'msg'=>'请填写手机号']);
    		exit;
    	}
    	if(!$this->isMobile($data['phone'])){
    		echo json_encode(['status'=>0,'msg'=>'请填写真实的手机号码']);
    		exit;
    	}

    	$characters = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ";
    	$code = ''; 
    	for ($i = 0; $i < 6; $i++) 
		{ 
			$code .= $characters[mt_rand(0, strlen($characters)-1)]; 
		}
		$coupons = Coupons::where(['status'=>0,'phone'=>$data['phone']])->find();
		if(empty($coupons)){
			$shop = ShopModel::where(['area'=>$data['area']])->find();
			Coupons::insert(['shop_id'=>$shop['id'],'phone'=>$data['phone'],'time'=>time(),'code'=>$code,'area'=>$data['area'],'price'=>$shop['price'],'civil_affairs_bureau'=>$shop['name']]);
		}else{
			$code = $coupons['code'];
		}

    	echo json_encode(['status'=>1,'code'=>$code]);
    	exit;
    }
    /**
	* 验证手机号是否正确
	* @author honfei
	* @param number $mobile
	*/
	public function isMobile($mobile) {
	    if (!is_numeric($mobile)) {
	        return false;
	    }
	    return preg_match('#^13[\d]{9}$|^14[5,7]{1}\d{8}$|^15[^4]{1}\d{8}$|^17[0,6,7,8]{1}\d{8}$|^18[\d]{9}$#', $mobile) ? true : false;
	}
    /** 生成唯一优惠券码
	* @param int $no_of_codes//定义一个int类型的参数 用来确定生成多少个优惠码 
	* @param array $exclude_codes_array//定义一个exclude_codes_array类型的数组 
	* @param int $code_length //定义一个code_length的参数来确定优惠码的长度 
	* @param int $coupons //优惠券父id
	* @return array//返回数组 
	*/ 
	function generate_promotion_code($no_of_codes,$exclude_codes_array='',$code_length = 4,$coupons=0) 
	{ 
		$characters = "23456789ABCDEFGHJKLMNPQRSTUVWXYZ"; 
		$promotion_codes = array();//这个数组用来接收生成的优惠码 
		for($j = 0 ; $j < $no_of_codes; $j++) 
		{ 
			$code = ""; 
			for ($i = 0; $i < $code_length; $i++) 
			{ 
				$code .= $characters[mt_rand(0, strlen($characters)-1)]; 
			} 
			//如果生成的4位随机数不再我们定义的$promotion_codes函数里面 
			if(!in_array($code,$promotion_codes)) 
			{ 
				if(is_array($exclude_codes_array))// 
				{ 
					if(!in_array($code,$exclude_codes_array))//排除已经使用的优惠码 
					{ 
						$promotion_codes[$j] = $code;//将生成的新优惠码赋值给promotion_codes数组
						$couponss = new Cs;
						$couponssdata = [
							'parent_id' => $coupons,
							'code' => $code,
						];
						if(!$couponss->save($couponssdata)){
							$j--;
						}
					} 
					else 
					{ 
						$j--; 
					} 
				} 
				else 
				{ 
					$promotion_codes[$j] = $code;//将优惠码赋值给数组 
					$couponss = new Cs;
					$couponssdata = [
						'parent_id' => $coupons,
						'code' => $code,
					];
					if(!$couponss->save($couponssdata)){
						$j--;
					}
				} 
			} 
			else 
			{ 
				$j--; 
			} 
		} 
		return $promotion_codes; 
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
 