<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Db;
use \think\Session;
use think\Request;
use app\index\model\MarryCase as MarryCaseModel;
use app\index\model\MarryCasePic;
use app\index\model\Picture;
use app\index\model\Professional;

class MarryCase extends Base
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
    	//案例
    	$marry_case = MarryCaseModel::where(['status'=>1])->order('status DESC,id DESC')->paginate(15,false,array('query'=>$data));
    	foreach ($marry_case as $k => $v) {
    		$substr = mb_substr($v['description'],0,50,'utf-8');
    		if($substr!=$v['description']){
    			$marry_case[$k]['description'] = $substr.'…';
    		}
    	}
    	$this->assign('list',$marry_case);
    	return $this->fetch();
    }
    //详细页
    public function detail()
    {
    	$data = input();
    	if(!isset($data['id'])){
    		$this->error('非法访问');
    	}
    	$marry_case = MarryCaseModel::get($data['id']);
    	$mc_pic = MarryCasePic::where(['marry_id'=>$data['id']])->select();
    	//职业人
    	$professional = Professional::get($marry_case['professional_id']);

    	$this->assign('professional',$professional);
    	$this->assign('marry_case',$marry_case);
    	$this->assign('mc_pic',$mc_pic);

    	return $this->fetch();
    }
}
