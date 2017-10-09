<?php
namespace app\index\controller;
use app\index\controller\Base;
use app\index\model\Coupons as C;
use app\index\model\Couponss as Cs;
use app\index\model\CouponsCompany as CC;
use think\Db;
use think\Request;
use \think\Session;

class Coupons extends Base
{
	public $rule = [
	        ];

	//优惠券主页
    public function index()
    {

    	$coupons = new C;
	    $where = 'c.type = 1';
	    if(Session::get('role')!=1){
    		$id = Session::get('company_id');
    		$where .=' and c.company_id ='.$id;
    	}

	    $data = array('search'=>'');
	    $datap = input('get.');
	    if(isset($datap['search'])&&$datap['search']){
	    	//搜索条件
	    	$data['search'] = $datap['search'];
	    	$where .=  ' and c.name like "%'.$datap['search'].'%"';
	    }
	    

    	$list = $coupons->field('c.*,cy.full_name')->alias('c')->join('company cy','c.company_id=cy.id','left')->where($where)->order('c.status,c.id', 'DESC')->paginate(15,false,array('query'=>$data));
    	$this->assign('list',$list);
    	$this->assign('search',$data);
    	return $this->fetch();
    }
    //添加抵用券
    public function add(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$couponsdata = [
    			'name' => $data['name'],
    			'company_id' => $data['company'],
    			'time' => time(),
    			'price' => $data['price'],
    			'start_time' => $data['start_time']?strtotime($data['start_time']):0,
    			'end_time' => $data['end_time']?strtotime($data['end_time'])+(24*3600-1):0,
    			'type' => $data['type'],
    			'count' => $data['count'],
    		];
    		$coupons = new C;
    		$re = $coupons->save($couponsdata);
    		if($re){
    			//生成抵用码
    			$this->generate_promotion_code($data['count'],'',7,$coupons->id);
    			return $this->success('抵用券添加成功','/Index/coupons/index');
    		}else{
    			return $this->error($coupons->getError());
    		}

    	}else{
    		//有效期
	    	$this->assign('time',['stime'=>date('Y-m-d'),'etime'=>date('Y-m-d',time()+3600*24*365*20)]);
	    	//公司列表
    		$company_list = Db::table('company')->order('parent_id')->select();
    		$this->assign('company_list',$company_list);

    		return $this->fetch();
    	}
    	
    }
    //编辑抵用券
    public function edit(){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$couponsdata = [
    			'name' => $data['name'],
    			'company_id' => $data['company'],
    			'start_time' => $data['start_time']?strtotime($data['start_time']):0,
    			'end_time' => $data['end_time']?strtotime($data['end_time'])+(24*3600-1):0,
    		];
    		$coupons = new C;
    		$re = $coupons->save($couponsdata,['id'=>$data['id']]);
    		if($re){
    			return $this->success('抵用券修改成功','/Index/coupons/index');
    		}else{
    			return $this->error($coupons->getError());
    		}

    	}else{
    		$data = input();
    		if(!$data['id']){
    			return $this->error('非法访问');
    		}
    		$coupons = C::get($data['id']);
    		$this->assign('coupons',$coupons);
	    	//公司列表
    		$company_list = Db::table('company')->order('parent_id')->select();
    		$this->assign('company_list',$company_list);

    		return $this->fetch();
    	}
    }
    //查看抵扣码
    public function childlist($parent_id = 0){
    	if(!$parent_id){
    		return $this->error('非法访问');
    	}

    	$couponss = new Cs;
    	$where = ['cs.parent_id'=>$parent_id];
	    $data = array('search'=>'');
	    $datap = input('get.');
	    if(isset($datap['search'])&&$datap['search']){
	    	//搜索条件
	    	$data['search'] = $datap['search'];
	    	$where =  array('cs.code'=>array('like','%'.$datap['search'].'%'));
	    }

    	$list = $couponss->field('cs.*,c.name,c.price')->alias('cs')->join('coupons c','cs.parent_id=c.id','left')->where($where)->order('cs.id', 'ASC')->paginate(15,false,array('query'=>array('parent_id'=>$parent_id)));  
    	$this->assign('search',$data);
    	$this->assign('list',$list);
    	$this->assign('parent_id',$parent_id);

    	return $this->fetch();
    }
    //分发抵扣码
    public function binding_company($id = 0){
    	if($id&&is_numeric($id)){
    		if(Request::instance()->isPost()){
    			$data = input('post.');
	    		$coupons_company = new CC;
	    		$coupons_company::destroy(['coupons_id'=>$data['coupons']]);
	    		$fsdata = [];
	    		foreach ($data['companys'] as $k => $v) {
	    			foreach ($v as $key => $val) {
	    				$fsdata[] = array(
	    				'coupons_id' => $data['coupons'],
	    				'company_id' => $key,
	    				);
	    			}
	    		}
	    		if($coupons_company->saveAll($fsdata)){
	    			model('coupons')->save(['status'=>1],['id'=>$id]);
	    			return $this->success('操作成功','/Index/Coupons/index');
	    		}else{
	    			return $this->error($coupons_company->getError());
	    		}
	    	}else{
	    		$list = model('coupons_company')->field('company_id')->where('coupons_id='.$id)->select();
	    		$coupons_company = array();
	    		foreach ($list as $k => $v) {
	    			$coupons_company[] = $v['company_id'];
	    		}
	    		$this->assign('coupons_company',$coupons_company);

	    		$coupons = model('coupons')->where('id='.$id)->find();
	    		$company = model('company')->where(['status'=>1,'level'=>1])->select();
	    		$companys = [];
		    	foreach ($company as $k => $v) {
		    		$companys[$k] = $v;
		    		$companys[$k]['list'] = model('company')->where('parent_id='.$v->id.' and status=1')->select();
		    	}
		    	$this->assign('companys',$companys);
		    	$this->assign('coupons',$coupons);
		    	return $this->fetch();
	    	}
	    }else{
	    	return $this->error('非法操作');
	    }
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
}
