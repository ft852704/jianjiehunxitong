<?php
namespace app\admin\controller;
use app\admin\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\admin\model\UDisk;

class Usb extends Base
{
	//主页
    public function index()
    {
		$data = input();
    	$where = '1=1';
    	if(isset($data['code'])&&$data['code']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		//$where =  array('name'=>array('like','%'.$data['name'].'%'));
    		$where.=' and mobile_phone like "%'.$data['code'].'%"';
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
    	if(isset($data['state'])){
    		if($data['state']=='all'){
				$data['state'] = '';
    		}else{
    			$where.= ' and state ='.$data['state'];
    		}
    	}else{
			$data['state'] = '';
    	}
    	
    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = UDisk::where($where)->order('id DESC')->paginate(15,false,array('query'=>$data));
    	$count = UDisk::where($where)->count();
    	$this->assign('count',$count);
    	//隐藏手机号
    	
    	$this->assign('list',$list);
    	$this->assign('status',['未支付','已支付','已退款']);
    	$this->assign('data',$data);

    	return $this->fetch();
    }
    //出租U盘前台页面
    public function rented_u_disk(){
    	//session::set('openid','oA-340lhkbqbxKMkZimYLwCzKNTg');
    	//unset($_SESSION);
    	if(isset($_GET['code'])&&$_GET['code']){
    	//if(!session::get('openid')){
    		//if(!isset($_GET['code'])||!$_GET['code']){
    		//	echo 1;
	    	//	header('Location:https://open.weixin.qq.com/connect/oauth2/authorize?appid=wxbc51b257e7c1b8b3&redirect_uri=http://www.jianjiehun.com/admin/usb/rented_u_disk.html&response_type=code&scope=snsapi_userinfo&state=123#wechat_redirect');
	    	//}
	    	@$code = $_GET['code'];//获取code
			$weixin =  file_get_contents("https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxbc51b257e7c1b8b3&secret=29d243317b5c3b6a1337af41afb596a2&code=".$code."&grant_type=authorization_code");//通过code换取网页授权access_token
			$array = json_decode($weixin,true); //对JSON格式的字符串进行编码
			//$array = get_object_vars($jsondecode);//转换成数组
			$openid = $array['openid'];//输出openid
			session::set('openid',$openid);
    	}
    	
		//dump($openid);exit;
    	//vendor( "Wchat");

    	if(Request::instance()->post()){
    		$data = input();
    		if(!isset($data['mobile_phone'])){
    			echo '测试';exit;
    		}
    		$u = UDisk::where(['mobile_phone'=>$data['mobile_phone'],'state'=>1])->find();
    		if(!empty($u)){
    			echo json_encode(['sta'=>0,'msg'=>'租赁失败，该手机号已经租赁过U盘']);
	    		exit;
    		}
    		$sdata = [
    			'name' => $data['name'],
    			'area' => $data['area'],
    			'mobile_phone' => $data['mobile_phone'],
    			'price' => 30,
    			'time' => time(),
    		];
    		$udisk = new UDisk;
    		if($re = $udisk->save($sdata)){
    			echo json_encode(['sta'=>1,'msg'=>'下单成功','params'=>['id'=>$udisk['id']]]);
	    		exit;
    		}else{
    			echo json_encode(['sta'=>0,'msg'=>'租赁失败']);
	    		exit;
    		}
    	}
    	$this->assign('openid',session::get('openid'));
    	return $this->fetch();
    }
    //购买成功
    public function buy_sucess(){
    	return $this->fetch();
    }
    
    //验证兑换码
    function usbDel(){
    	$data = input();
		$id = $data['id'];
    	if($id&&is_numeric($id)){
    		$user = UDisk::get($id);
    		if($user->state==1)$user->state=2;
    		elseif($user->state===2)$user->state=1;

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
 