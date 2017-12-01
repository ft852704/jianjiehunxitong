<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\index\model\Professional as ProfessionalModel;
use app\index\model\Picture;
use app\index\model\ProfessionalPic;
use app\index\model\Service;
use app\admin\model\MarryCase;

class Professional extends Base
{
	public $role = [
    		1 => '管理员',
    		2 => '收银员',
    	];
	public function _initialize()
    {
        
    }
	//主页
    public function index()
    {
    	$data = input();
    	$where = [
    		'status' => 1,
    	];
    	if(isset($data['type'])){
    		$where['type'] = $data['type'];
    	}
    	$professional = ProfessionalModel::where($where)->order('id DESC')->paginate(15,false,array('query'=>$data));

    	foreach ($professional as $k => $v) {
    		$substr = mb_substr($v['introduction'],0,90,'utf-8');
    		if($substr!=$v['introduction']){
    			$professional[$k]['introduction'] = $substr.'…';
    		}
    		$service = Service::where(['professional_id'=>$v['id']])->find();
    		$professional[$k]['price'] = $service['price']?$service['price']:0;
    	}

    	$this->assign('list',$professional);

    	$this->assign('pro_type',$this->pro_type);
    	return $this->fetch();
    }
    //详细页
    public function detail()
    {
    	$data = input();
    	if(!isset($data['id'])){
    		return $this->error('违法访问');
    	}
    	$data['service_id'] = isset($data['service_id'])?$data['service_id']:0;
    	//个人服务
    	$service = Service::field('st.*,s.id as sid,s.price,s.service_statement')->alias('s')->join('service_template st','s.template_id=st.id','left')->where(['s.professional_id'=>$data['id'],'s.status'=>1])->select();
    	$this->assign('service',$service);
    	//个人资料
    	$professional = ProfessionalModel::get($data['id']);
    	$this->assign('professional',$professional);
    	//个人图片
    	$pro_pic = ProfessionalPic::where(['professional_id'=>$data['id']])->select();
    	if(isset($pro_pic[0])){
    		$furl = $pro_pic[0]['url'];
    		unset($pro_pic[0]);
    	}else{
    		$furl = '';
    	}
    	//个人案例
    	$marry_case = MarryCase::where(['professional_id'=>$data['id'],'status'=>1])->order('status DESC,id DESC')->limit(4)->select();
    	$this->assign('marry_case',$marry_case);
    	$this->assign('data',$data);
    	$this->assign('furl',$furl);
    	$this->assign('pro_pic',$pro_pic);

    	return $this->fetch();
    }
}
