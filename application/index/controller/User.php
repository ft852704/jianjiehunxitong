<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\index\model\User as UserModel;
use app\index\model\Bespeak;
use app\index\model\Order;
use app\index\model\Service;
use app\index\model\ServiceTemplate;
use app\index\model\Professional;

class User extends Base
{
	public function _initialize()
    {
        if(!Session::get('user_id')){
        	$this->error('请登陆后再进入个人中心');
        }
        $request = request();
        $this->assign('action',$request->action());
    }
	//主页  个人中心
    public function index()
    {
    	$data = input();
    	if(!Session::get('user_id')){
    		$this->error('请登陆后再进入个人中心');
    	}
    	if(Request::instance()->post()){
    		$user = UserModel::get($data['id']);
    		$data['marry_date'] = strtotime($data['marry_date']);
    		if($data['password']){
    			$data['password'] = md5($data['password']);
    		}else{
    			unset($data['password']);
    		}
    		if($user->save($data)){
    			$this->success('修改成功', 'user/index');
    		}else{
    			$this->success('修改失败', 'user/index');
    		}
    	}
    	$user = UserModel::get(Session::get('user_id'));
    	$this->assign('user',$user);
    	$sex = [
    		0 => '未知',
    		1 => '新郎',
    		2 => '新娘',
    	];

    	$this->assign('sex',$sex);

    	return $this->fetch();
    }
    //订单页面
    public function order(){
    	$data = input();
    	if(!Session::get('user_id')){
    		$this->error('请登陆后再进入个人中心');
    	}
    	$order = Order::where(['user_id'=>Session::get('user_id'),'status'=>1,'state'=>array('in','1,2,3,4')])->order('id DESC')->limit(10)->select();
    	foreach ($order as $k => $v) {
    		$service = Service::field('s.*,st.name')->alias('s')->join('service_template st','s.template_id=st.id','left')->where(['s.id'=>$v['service_id']])->find();
    		if(empty($service)){
    			continue;
    		}
    		$pro = Professional::get($v['linkman_id']);
    		$order[$k]['activer_id'] = $service['name'];
    		$order[$k]['enclosure'] = $pro['name'];
    	}
    	$this->assign('order',$order);
    	return $this->fetch();
    }
    //订单详情页
    public function orderDetail(){
    	$data = input();
    	$order = Order::get($data['id']);

    	$this->assign('order',$order);

    	$user = UserModel::get($order['user_id']);
    	$this->assign('user',$user);
    	$service = Service::get($order['service_id']);
    	$this->assign('service',$service);
    	$st = ServiceTemplate::get($service['template_id']);
    	$this->assign('st',$st);

    	$this->assign('sex',[0=>'',1=>'先生',2=>'女士']);
    	$this->assign('order_type',[1=>'人员费',2=>'布置费']);
    	$this->assign('banquet_type',[0=>'不确定',1=>'午宴',2=>'晚宴']);
    	$this->assign('order_state',$this->order_state);

    	return $this->fetch();
    }
    //支付选择
    public function pay_choose(){
    	$data = input();
    	$this->assign('data',$data);
    	return $this->fetch();
    }
    //支付成功
    public function order_sucess(){
    	$data = input();
    	return $this->fetch();
    }
    //预约单页面
    public function bespeak(){
		$data = input();
    	if(!Session::get('user_id')){
    		$this->error('请登陆后再进入个人中心');
    	}
    	$bespeak = Bespeak::where(['user_id'=>Session::get('user_id'),'status'=>1])->order('id DESC')->limit(10)->select();
    	$this->assign('bespeak',$bespeak);

    	$this->assign('pro_type',$this->pro_type);

    	$this->assign('bespeak_state',$this->bespeak_state);
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
	    	$user = UserModel::get($data['id']);
	    	if($data['password']){
	    		$data['password'] = md5($data['password']);
	    	}else{
	    		unset($data['password']);
	    	}
	    	if($user->save($data)){
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
}
