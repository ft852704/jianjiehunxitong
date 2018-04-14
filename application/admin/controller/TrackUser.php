<?php
namespace app\admin\controller;
use app\admin\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\admin\model\TrackUser as TrackUserModel;

class TrackUser extends Base
{
	public $state = [
		'未知',
		'有效',
		'无效',
		'未联系上',
		'潜在',
		'已下单',
		'all' => '全部',
	];
	public $adviser = [
		0 => '其他',
		2 => 'Lina',
		3 =>'在容',
	];
	public $DS = '{/}';//备注中的分隔符
	public function _initialize()
    {
        
    }
	//账号列表
    public function index()
    {
    	$data = input();
    	if(session::get('id')==1){
    		$where = [];
    	}else{
    		$where = ['adviser'=>session::get('id')];
    	}
    	if(isset($data['name'])&&$data['name']){
    		//$where = ['name'=>['like'=>'%'.$data['name'].'%']];
    		if($data['stype']==1){//手机号
    			$where =  array('mobile'=>array('like','%'.$data['name'].'%'));
    		}
    		if($data['stype']==2){//微信
    			$where =  array('email'=>array('like','%'.$data['name'].'%'));
    		}
    	}else{
    		$data['name'] = '';
    		$data['stype'] = 1;
    	}
    	if(isset($data['from'])&&$data['from']){
    		if($data['from']!='all')
    			$where['from'] = $data['from'];
    	}else{
    		$data['from'] = 0;
    	}
    	if(isset($data['state'])&&$data['state']){
    		if($data['state']!='all')
    			$where['state'] = $data['state'];
    	}else{
    		$data['state'] = 0;
    		$where['state'] = $data['state'];
    	}
    	$where1 = '';//录入时间
    	$data['time1'] = isset($data['time1'])?strtotime($data['time1']):0;
    	$data['time2'] = isset($data['time2'])?strtotime($data['time2']):0;
    	if(isset($data['time1'])&&$data['time1']){
    		if(isset($data['time2'])&&$data['time2']){
    			$where1 = 'next_time>='.$data['time1'].' and next_time<='.$data['time2'];
    		}else{
    			$where1 = 'next_time>='.$data['time1'];
    		}
    	}elseif(isset($data['time2'])&&$data['time2']){
    		$where1 = 'next_time<='.$data['time2'];
    	}
    	
    	//婚期
    	$data['marry_date1'] = isset($data['marry_date1'])?strtotime($data['marry_date1']):0;
    	$data['marry_date2'] = isset($data['marry_date2'])?strtotime($data['marry_date2']):0;
    	if(isset($data['marry_date1'])&&$data['marry_date1']){
    		if(isset($data['marry_date2'])&&$data['marry_date2']){
    			$where1 = $where1?$where1.' and ':'';
    			$where1 .= 'marry_date>='.$data['marry_date1'].' and marry_date<='.$data['marry_date2'];
    		}else{
    			$where1 = $where1?$where1.' and ':'';
    			$where1 .= 'marry_date>='.$data['marry_date1'];
    		}
    	}elseif(isset($data['marry_date2'])&&$data['marry_date2']){
    		$where1 = $where1?$where1.' and ':'';
    		$where1 .= 'marry_date<='.$data['marry_date2'];
    	}

    	//检查账号权限
		/*if(!$this->is_power()){
			$this->error('没有操作权限，请联系管理员给予对应权限后在操作。');
		}*/
    	//$list = LoginFamily::->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$count = TrackUserModel::where($where)->count();
    	$this->assign('count',$count);
    	$list = TrackUserModel::where($where)->where($where1)->order('next_time DESC,id DESC')->paginate(15,false,array('query'=>$data));
    	foreach ($list as $k => $v) {
    		$list[$k]['remark'] = explode($this->DS, $v['remark']);
    	}
    	$this->assign('list',$list);

    	$data['time1'] = $data['time1']?date('Y-m-d',$data['time1']):'';
    	$data['time2'] = $data['time2']?date('Y-m-d',$data['time2']):'';
    	$data['marry_date1'] = $data['marry_date1']?date('Y-m-d',$data['marry_date1']):'';
    	$data['marry_date2'] = $data['marry_date2']?date('Y-m-d',$data['marry_date2']):'';
    	$this->assign('data',$data);

    	$this->assign('from',$this->from);
    	$this->assign('state',$this->state);
	    $this->assign('adviser',$this->adviser);
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
	    	if(!($data['mobile']||$data['email'])){
	    		echo json_encode(['sta'=>0,'msg'=>'手机号和微信号必须填写一个']);
	    		exit;
	    	}
	    	$re1 = model('user')->where(['name'=>$data['name']])->find();
	    	if(!empty($re1)){
	    		echo json_encode(['sta'=>0,'msg'=>'登录名已存在，请重新输入']);
	    		exit;
	    	}
	    	$re2 = model('user')->where(['mobile'=>$data['mobile']])->find();
	    	if(!empty($re2)&&$data['mobile']){
	    		echo json_encode(['sta'=>0,'msg'=>'手机号已存在，请重新输入']);
	    		exit;
	    	}
	    	if($data['email']){
	    		$re3 = model('user')->where(['email'=>$data['email']])->find();
		    	if(!empty($re3)){
		    		echo json_encode(['sta'=>0,'msg'=>'微信号已存在，请重新输入']);
		    		exit;
		    	}
	    	}

	    	$data['password'] = md5($data['password']);
	    	$data['time'] = time();
	    	$data['marry_date'] = strtotime($data['marry_date']);
	    	$data['remark'] = $data['remark']?date('Y-m-d').' '.$data['remark'].$this->DS:'';
	    	if($data['next_time']&&$data['marry_date']){
	    		$data['state'] = 1;
	    	}
	    	if($data['next_time']&&!$data['marry_date']){
	    		$data['state'] = 4;
	    	}
	    	if(!$data['next_time']){
	    		$data['state'] = 0;
	    	}
	    	$user = new TrackUserModel;
	    	if($user->save($data)){
	    		echo json_encode(['sta'=>1,'msg'=>'添加成功']);
	    		exit;
	    	}else{
	    		echo json_encode(['sta'=>0,'msg'=>'添加失败']);
	    		exit;
	    	}
	    }
	    $this->assign('adviser',$this->adviser);
    	$this->assign('state',$this->state);
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
	    	$admin = TrackUserModel::get($data['id']);

	    	if(!($data['mobile']||$data['email'])){
	    		echo json_encode(['sta'=>0,'msg'=>'手机号和微信号必须填写一个']);
	    		exit;
	    	}

	    	$re1 = model('user')->where(['name'=>$data['name'],'id'=>['NEQ',$data['id']]])->find();
	    	if(!empty($re1)){
	    		echo json_encode(['sta'=>0,'msg'=>'登录名已存在，请重新输入']);
	    		exit;
	    	}
	    	$re2 = model('user')->where(['mobile'=>$data['mobile'],'id'=>['NEQ',$data['id']]])->find();
	    	if(!empty($re2)&&$data['mobile']){
	    		echo json_encode(['sta'=>0,'msg'=>'手机号已存在，请重新输入']);
	    		exit;
	    	}
	    	if($data['email']){
	    		$re3 = model('user')->where(['email'=>$data['email'],'id'=>['NEQ',$data['id']]])->find();
		    	if(!empty($re3)){
		    		echo json_encode(['sta'=>0,'msg'=>'微信号已存在，请重新输入']);
		    		exit;
		    	}
	    	}

	    	if($data['remark']){
	    		$data['remark'] = $admin['remark'].date('Y-m-d').' '.$data['remark'].$this->DS;
	    	}else{
	    		unset($data['remark']);
	    	}
	    	if($data['password']){
	    		$data['password'] = md5($data['password']);
	    	}else{
	    		unset($data['password']);
	    	}
	    	if($data['next_time']&&$data['marry_date']){
	    		$data['state'] = 1;
	    	}
	    	if($data['next_time']&&!$data['marry_date']){
	    		$data['state'] = 4;
	    	}
	    	if(!$data['next_time']){
	    		$data['state'] = 0;
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
		$user = TrackUserModel::get($id);
		$user['remark'] = explode($this->DS, $user['remark']);
		$this->assign('user',$user);
	    $this->assign('from',$this->from);
    	$this->assign('state',$this->state);
	    $this->assign('adviser',$this->adviser);

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
    		$user = TrackUserModel::get($id);
    		if($user->status==1){
    			$user->status=0;
    			$user->state=2;
    		}elseif($user->status===0){
    			$user->status=1;
    			$user->state=0;
    		}

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
