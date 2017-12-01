<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\index\model\LoginFamily;
use app\index\model\Role;
use app\index\model\LoginAce;
use app\index\model\LoginLog;
use app\index\model\MarryCase;
use app\index\model\MarryCasePic;
use app\index\model\Professional;
use app\index\model\Picture;
use app\index\model\ProfessionalPic;
use app\index\model\User;
use app\index\model\UserLoginLog;
use app\index\model\Order;
use app\index\model\Service;
use app\index\model\ServiceTemplate;


class Index extends Base
{
	public $role = [
    		1 => '管理员',
    		2 => '收银员',
    	];
	public function _initialize()
    {
    	vendor( "pay.init");
    }
    public function test(){
    	//https://www.pingxx.com/api?language=PHP#支付渠道-extra-参数说明
    	//提交过来的参数中需要有
    	$input_data = json_decode(file_get_contents('php://input' ), true);
		if (empty($input_data[ 'channel'])||empty($input_data[ 'order_no'])) {
			echo 'channel or order_no is empty';
			exit();
		}
		$order = Order::where(['order_no'=>$input_data[ 'order_no'],'status'=>1,'state'=>1,'user_id'=>Session::get('user_id')])->find();
		if(empty($order)){
			echo '';exit;
		}
		$service = Service::alias('s')->field('s.id,st.name')->join('service_template st','s.template_id=st.id','left')->where(['s.id'=>$order['service_id'],'s.status'=>1])->find();
		if(empty($service)){
			echo '';exit;
		}

		//$channel = strtolower($input_data['channel'].'alipay' );//支付方式
		$channel = $input_data[ 'channel'];//支付方式

		$amount = ($order['total']*100); //支付金额
		$orderno = $order['order_no']; //订单号
		$client_ip= $_SERVER['SERVER_ADDR']; //客户端ip
		$subject=$service['name']; //商品名称
		$body='该服务最终解释权归简结婚所有'; //商品描述信息

		//$extra 在使用某些渠道的时候，需要填入相应的参数，其它渠道则是 array() .具体见以下代码或者官网中的文档。其他渠道时可以传空值也可以不传。
		$extra = array();
		switch ($channel) {
		case 'alipay_pc_direct':
		$extra = array(
		'success_url' => 'http://admin.jianjiehun.com/index/user/order_sucess' ,//支付成功的回调地址。
		);
		break;
		case 'alipay_wap':
		$extra = array(
		'success_url' => 'http://admin.jianjiehun.com/test.php' ,//支付成功的回调地址。
		'cancel_url' => 'http://admin.jianjiehun.com/test.php'//支付取消的回调地址， app_pay 为 true 时，该字段无效。
		);
		break;
		case 'upmp_wap':
		$extra = array(
		'result_url' => 'http://www.yourdomain.com/result?code='
		);
		break;
		case 'bfb_wap':
		$extra = array(
		'result_url' => 'http://www.yourdomain.com/result?code=' ,
		'bfb_login' => true
		);
		break;
		case 'upacp_wap':
		$extra = array(
		'result_url' => 'http://www.yourdomain.com/result'
		);
		break;
		case 'wx_pub':
		$extra = array(
		'open_id' => 'Openid'
		);
		break;
		case 'wx_pub_qr':
		$extra = array(
		'product_id' => 'Productid'
		);
		break;
		case 'yeepay_wap':
		$extra = array(
		'product_category' => '1',
		'identity_id'=> 'your identity_id',
		'identity_type' => 1,
		'terminal_type' => 1,
		'terminal_id'=> 'your terminal_id',
		'user_ua'=> 'your user_ua',
		'result_url'=> 'http://admin.jianjiehun.com/test.php'
		);
		break;
		case 'jdpay_wap':
		$extra = array(
		'success_url' => 'http://www.yourdomain.com',
		'fail_url'=> 'http://www.yourdomain.com',
		'token' => 'dsafadsfasdfadsjuyhfnhujkijunhaf'
		);
		break;

		}
		\Pingpp\Pingpp::setApiKey($this->Ping['ApiKey']);
		try {
			$ch = \Pingpp\Charge:: create(
			array(
				'order_no' => $orderno, //订单编号
				'amount' => $amount,//订单金额  单位  分
				'app' => array ('id' => $this->Ping['AppID']),//支付使用的  app 对象的  id ，expandable 可展开，查看 如何获取App ID 。https://help.pingxx.com/article/198599/
				'channel' => $channel,//支付渠道  alipay	支付宝 APP 支付  alipay_wap	支付宝手机网页支付   wx_wap	微信 H5 支付   wx	微信 APP 支付
				'currency' => 'cny',//货币代码  cny 人民币
				'client_ip' => $client_ip,//客户端请求ip
				'subject' => $subject,//商品标题，该参数最长为 32 个 Unicode 字符。银联全渠道（ upacp / upacp_wap ）限制在 32 个字节；支付宝部分渠道不支持特殊字符。  https://help.pingxx.com/article/1059334/
				'body' => $body,//商品描述信息，该参数最长为 128 个 Unicode 字符。 yeepay_wap 对于该参数长度限制为 100 个 Unicode 字符；支付宝部分渠道不支持特殊字符。
				'extra' => $extra //特定渠道发起交易时需要的额外参数，以及部分渠道支付成功返回的额外参数，详细参考 支付渠道 extra 参数说明 。
			)
		);
			echo $ch;
		} catch (\Pingpp\Error\base $e) {
			header( 'Status: ' . $e->getHttpStatus());
			echo($e->getHttpBody());
		}





    }
	//主页
    public function index()
    {
    	$professional = Professional::where(['status'=>1])->limit(8)->order('id DESC')->select();
    	$this->assign('professional',$professional);

    	$marrycase = MarryCase::where(['status'=>1])->limit(9)->order('id DESC')->select();
    	$this->assign('marrycase',$marrycase);

    	return $this->fetch();
    }
    //登录
    public function login(){
    	if(Request::instance()->post()){
    		$data = input();
    		$data['name'] = trim($data['name']);
    		$data['password'] = md5(trim($data['password']));
    		$login = User::where($data)->find();
    		if($login){
	    		if(!$login['status']){
	    			$this->error('账号未启用，请联系管理员启用后再登录', 'Index/login');
	    		}
    			Session::set('user_id',$login['id']);
    			Session::set('user_nick',$login['nick']);
    			Session::set('real_name',$login['real_name']);

				UserLoginLog::insert(['user_id'=>$login['id'],'ip'=>$_SERVER["REMOTE_ADDR"],'time'=>time()]);
				$this->success('登录成功', 'Index/index');
    		}else{
    			$this->error('用户名或密码错误，请重新输入', 'Index/login');
    		}
    	}else{
    		return $this->fetch();	
    	}
    }
    //退出登录
    function loginout(){
    	Session::delete('user_id');
    	$this->success('退出成功', 'Index/index');
    }
    //登录账号列表
    function loginlist(){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	$role = Role::select();
    	$rolelist = [];
    	foreach ($role as $key => $value) {
    		$rolelist[$value['id']] = $value['name'];
    	}
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$list = LoginFamily::alias('lf')->field('lf.*,c.full_name')->join('company c','lf.company=c.id','left')->order('lf.status DESC,lf.id DESC')->paginate(15,false);
    	foreach ($list as $k => &$v) {
    		$list[$k]['role_name'] = $rolelist[$v['role']];
    	}
    	$this->assign('list',$list);

    	return $this->fetch();	
    }
    //登录账号添加
    function loginadd(){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	$data['password'] = md5($data['password']);
	    	$data['time'] = date('Y-m-d H:i:s');
	    	$lf = new LoginFamily;
	    	if($lf->save($data)){
	    		return $this->success('登录账号添加成功','/Index/index/loginlist');
	    	}else{
		        return $this->error($lf->getError());
	    	}
	    }
    	//公司列表
    	$company_list = Db::table('company')->where(['level'=>2,'status'=>1])->order('parent_id')->select();
    	$this->assign('company_list',$company_list);
    	//角色
    	$role = Role::select();
    	$rolelist = [];
    	foreach ($role as $key => $value) {
    		$rolelist[$value['id']] = $value['name'];
    	}
		$this->assign('rolelist',$rolelist);

    	return $this->fetch();	
    }
    //登录账号编辑
    function loginedit($id=0){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	//登录名，密码，姓名，所属门店，角色
    	if(Request::instance()->post()){
	    	$data = input('post.');
	    	if($data['password']){
	    		$data['password'] = md5($data['password']);
	    	}else{
	    		unset($data['password']);
	    	}
	    	$lf = LoginFamily::get($data['id']);
	    	if($lf->save($data)){
	    		return $this->success('登录账号修改成功','/Index/index/loginlist');
	    	}else{
		        return $this->error($lf->getError());
	    	}
	    }
    	//公司列表
    	$company_list = Db::table('company')->where(['level'=>2,'status'=>1])->order('parent_id')->select();
    	$this->assign('company_list',$company_list);
    	//角色
    	$role = Role::select();
    	$rolelist = [];
    	foreach ($role as $key => $value) {
    		$rolelist[$value['id']] = $value['name'];
    	}
		$this->assign('rolelist',$rolelist);

		//登录账号
		$lf = LoginFamily::get($id);
		$this->assign('lf',$lf);

    	return $this->fetch();	
    }
    //登录账号删除
    function loginedel($id=null){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	if($id&&is_numeric($id)){
    		$lf = LoginFamily::get($id);
    		if($lf->status==1)$lf->status=0;
    		elseif($lf->status===0)$lf->status=1;

    		if($lf->save()){
    			return $this->success('操作成功','/Index/index/loginlist');
    		}else{
    			return $this->error($family->getError());
    		}
    	}else{
    		return $this->success('错误操作','/Index/index/loginlist');
    	}
    }

    //给账号绑定权限 
    public function binding_ace($id=1){
    	//检查账号权限
		if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}
    	if($id&&is_numeric($id)){
    		if(Request::instance()->isPost()){
    			$data = input('post.');
	    		$login_ace = new LoginAce;
	    		$login_ace::destroy(['login_id'=>$data['login']]);
	    		$fsdata = [];
	    		foreach ($data['paces'] as $k => $v) {
	    			$fsdata[] = array(
	    				'login_id' => $data['login'],
	    				'ace_id' => $k,
	    				);
	    		}
	    		if($login_ace->saveAll($fsdata)){
	    			return $this->success('操作成功','/Index/index/loginlist');
	    		}else{
	    			return $this->error($login_ace->getError());
	    		}
	    	}else{
	    		$list = model('login_ace')->field('ace_id')->where('login_id='.$id)->select();
	    		$login_ace = array();
	    		if(empty($list)){
	    			$login = model('login_family')->get($id);
	    			$list = model('role_ace')->field('ace_id')->where('role_id='.$login['role'])->select();
	    			foreach ($list as $k => $v) {
		    			$login_ace[] = $v['ace_id'];
		    		}
	    		}else{
	    			foreach ($list as $k => $v) {
		    			$login_ace[] = $v['ace_id'];
		    		}
	    		}
	    		
	    		$this->assign('login_ace',$login_ace);

	    		$login = model('login_family')->where('id='.$id)->find();
	    		$pace = model('ace')->where(['parent_id'=>0])->select();
	    		$paces = [];
		    	foreach ($pace as $k => $v) {
		    		$paces[$k] = $v;
		    		$paces[$k]['list'] = model('ace')->where('parent_id='.$v->id)->select();
		    	}
		    	$this->assign('paces',$paces);
		    	$this->assign('login',$login);
		    	return $this->fetch();
	    	}
	    }else{
	    	return $this->error('非法操作');
	    }
    }
    //获取ping++ Webhooks请求
    public function getWebhooksData(){
    	$input_data = json_decode(file_get_contents('php://input' ), true);
    	if($input_data['type']=='charge.succeeded'){
    		if($input_data['data']['object']['app']==$this->Ping['AppID']){//
    			$order = Order::where(['order_no'=>$input_data['data']['object']['order_no']])->find();
    			if(!empty($order)){
    				$order->client_ip = $input_data['data']['object']['client_ip'];
    				$order->state = 2;
    				$order->pay_type = 1;
    				$order->pingplus_no = $input_data['data']['object']['id'];
    				$order->pay_no = $input_data['data']['object']['transaction_no'];
    				$order->pay_time = $input_data['data']['object']['created'];
    				$order->webhooks = json_encode($input_data);
    				if($order->save()){
    					return true;
    				}
    			}
    		}else{
    			return false;
    		}
    	}else{
    		return false;
    	}

    }
}
