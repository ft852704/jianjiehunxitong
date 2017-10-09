<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use app\index\model\Company as CompanyModel;
use app\index\model\MemberCardType as MCT;
use app\index\model\MemberCard as MC;
use app\index\model\Wallet;
use think\Db;

class Company extends Base
{
	public $rule = [
	            //公司添加字段
	            'number|公司编号'   => 'require|unique:company',
				'full_name|中文全称' => 'require',
				'short_name|中文简称'   => 'require',
				'telephone|电话'   => 'require',
				'level|公司级别'     => 'require',
				'mem_card_level|会员卡级别'     => 'require',
				'address|公司地址'     => 'require|min:5',
				'status|状态'     => 'require',
	        ];
	public $city = [
				1 => '成都',
				2 => '北京',
				3 => '上海',
				4 => '苏州',
				5 => '青岛',
				6 => '西安',
			];
	public $company_level = array('总部','大区总部','门店');
	//公司主页
    public function index()
    {
	    $company = new CompanyModel;
	    $where = array();
	    $data = array('search'=>'','search_type'=>'');
	    if(Request::instance()->get()){
	    	$data = input('get.');
	    	//搜索条件
	    	if($data['search_type']==1){
	    		$where =  array('full_name'=>array('like','%'.$data['search'].'%'));
	    	}elseif($data['search_type']==2){
	    		$where =  array('address'=>array('like','%'.$data['search'].'%'));
	    	}else{
	    		$where =  array('number'=>array('like','%'.$data['search'].'%'));
	    	}
	    }
    	$list = $company->where($where)->order('status DESC,parent_id ASC')->paginate(15,false,array('query'=>$data));
    	$this->assign('list',$list);
    	$this->assign('search',$data);
    	$this->assign('level',$this->company_level);
    	return $this->fetch();
    }
    //添加公司
    public function add(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		if($data['start_time']){
    			$start_time = explode(':', $data['start_time']);
    			$data['start_time'] = $start_time[0]*3600+$start_time[1]*60;
    			//$data['start_time'] = strtotime($data['start_time']);
    		}
    		if($data['end_time']){
    			$end_time = explode(':', $data['end_time']);
    			$end_time1 = $end_time[0]*3600+$end_time[1]*60;
    			//$data['end_time'] = strtotime($data['end_time']);
    			if($end_time1<=$data['start_time']){
    				$data['end_time'] = $end_time1+(24*3600);
    			}else{
    				$data['end_time'] = $end_time1;
    			}
    		}
	        $company = new CompanyModel;  

	        $pcompany = CompanyModel::get($data['parent_id']);
	        $data['level'] = empty($pcompany)?0:$pcompany['level']+1;
	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	if($id = $company->save($data)){
	        		if($data['level']==2){
		        		$data_app = [
		        			'id' => $company['id'],
		        			'cid' => $data['cid'],
		        			'shop_name' => $data['full_name'],
		        			'tel' => $data['telephone'],
		        			'address' => $data['address'],
		        			'desc' => $data['desc'],
		        			'long' => $data['long'],
		        			'lat' => $data['lat'],
		        			'add_time' => time(),
		        			'shop_year' => $data['shop_year'],
		        			'is_on' => $data['is_on'],
		        			'start_time' => $data['start_time'],
		        			'end_time' => $data['end_time'],
		        		];
		        		$re = Db::connect('db_config_app')->table('dz_shop')->insert($data_app);
	        		}
		            return $this->success('公司添加成功','/Index/company/index');
		        } else {
		            return $this->error($company->getError());
		        }
	        }

	        // 数据验证
	        /*$company = new CompanyModel;
		    if ($company->validate(true)->save(input('post.'))) {
		        return '公司[ ' . $company->full_name . ':' . $company->id . ' ]新增成功';
		    } else {
		        return $company->getError();
		    }*/
	        
    	}else{

    		$companylist = Db::table('company')->where('level','in','0,1')->order('parent_id')->select();
    		$this->assign('citylist',$this->city);
    		$this->assign('list',$companylist);
    		$this->assign('level',$this->company_level);
    		return $this->fetch();
    	}
    	
    }
    //编辑公司信息
    public function edit($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$id = $data['id'];
	        $company = new CompanyModel;
	        $pcompany = CompanyModel::get($data['parent_id']);
	        $data['level'] = empty($pcompany)?0:$pcompany['level']+1;

	        if($data['start_time']){
    			$start_time = explode(':', $data['start_time']);
    			$data['start_time'] = $start_time[0]*3600+$start_time[1]*60;
    			//$data['start_time'] = strtotime($data['start_time']);
    		}
    		if($data['end_time']){
    			$end_time = explode(':', $data['end_time']);
    			$end_time1 = $end_time[0]*3600+$end_time[1]*60;
    			//$data['end_time'] = strtotime($data['end_time']);
    			if($end_time1<=$data['start_time']){
    				$data['end_time'] = $end_time1+(24*3600);
    			}else{
    				$data['end_time'] = $end_time1;
    			}
    		}

	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        if(!$result&&!$id){
	            return  $validate->getError();
	        }else{
	        	if($id = $company->save($data,array('id'=>$id))){
	        		if($data['level']==2){
		        		$data_app = [
		        			'cid' => $data['cid'],
		        			'shop_name' => $data['full_name'],
		        			'tel' => $data['telephone'],
		        			'address' => $data['address'],
		        			'desc' => $data['desc'],
		        			'long' => $data['long'],
		        			'lat' => $data['lat'],
		        			'add_time' => time(),
		        			'shop_year' => $data['shop_year'],
		        			'is_on' => $data['is_on'],
		        			'start_time' => $data['start_time'],
		        			'end_time' => $data['end_time'],
		        		];
		        		Db::connect('db_config_app')->table('dz_shop')->where('id',$data['id'])->update($data_app);
	        		}
		            return $this->success('公司修改成功','/Index/company/index');
		        } else {
		            return $this->error($company->getError());
		        }
	        }

    	}else{
    		if(!empty($id)&&is_numeric($id)){
    			$companylist = Db::table('company')->where('level','in','0,1')->order('parent_id')->select();
	    		$this->assign('list',$companylist);
	    		$this->assign('level',$this->company_level);
    			$this->assign('citylist',$this->city);

    			$re = Db::table('company')->where('id='.$id)->find();
    			if($re['start_time']){
    				//$re['start_time'] = floor($re['start_time']/3600).':'.($re['start_time']%3600/60);
    				$re['start_time'] = date('H:i',(strtotime('today')+$re['start_time']));
    			}
    			if($re['end_time']>=0){
    				//$re['start_time'] = floor($re['start_time']/3600).':'.($re['start_time']%3600/60);
    				$re['end_time'] = date('H:i',(strtotime('today')+$re['end_time']));
    			}
    			$this->assign('company',$re);
    			return $this->fetch();
    		}
    	}
    }
    //更改公司状态
    public function del($id=null){
    	if($id&&is_numeric($id)){
    		$company = CompanyModel::get($id);
    		if($company->status==1)$company->status=0;
    		elseif($company->status===0)$company->status=1;
    		if($company->save()){
    			return $this->success('操作成功','/Index/company/index');
    		}else{
    			return $this->error($company->getError());
    		}
    	}else{
    		return $this->success('错误操作','/Index/company/index');
    	}
    }
    //app会员卡资料导入
    public function getMemberData(){
    	$companylist = CompanyModel::select();
    	foreach ($companylist as $k => $v) {
    		$company[$v['full_name']] = $v['id'];
    	}
    	$cardlist = MCT::select();
    	foreach ($cardlist as $k => $v) {
    		$card[$v['name']] = $v['id'];
    	}
    	$statusList = [
			'已开卡' => 1,
			'已换卡' => 2,
			'已转卡' => 3,
			'已到期' => 4,
		];
    	$re = Db::table('dz_card_copy')->limit(20000,20000)->select();
    	//echo Db::table('dz_card_copy')->getLastSql();
    	$data = [];
    	foreach ($re as $k => $v) {
    		$tempno = strtolower($v['card_no']);
    		$company_area = 5;
    		$card['8折'] = 99;
    		$card['3000元75折'] = 99;
    		$card['500元85折'] = 99;
    		$card_type = $card[$v['discount']];
    		if($v['discount']=='7折'){
    			$card_type = 15;
    		}elseif($v['discount']=='75折'||$v['discount']=='3000元75折'){
    			$card_type = 13;
    		}elseif($v['discount']=='65折'){
    			$card_type = 8;
    		}elseif($v['discount']=='5折'){
    			$card_type = 5;
    		}elseif($v['discount']=='8折'){
    			$card_type = 18;
    		}elseif($v['discount']=='500元85折'){
    			$card_type = 16;
    		}
    		$data = [
    			'id' => $v['id'],
    			'card_no' => $v['card_no'],
    			'card_type' => $card_type,
    			'company_area' => $company_area,
    			'status' => isset($statusList[$v['card_status']])?$statusList[$v['card_status']]:4,
    			'company_id' => isset($company[$v['shop_name']])?$company[$v['shop_name']]:5,
    			'start_time' => $v['sale_time'],
    			'end_time' => $v['card_expire'],
    			'name' => $v['card_ower_name'],
    			'is_msn' => 1,
    			'sex' => $v['card_ower_sex'],
    			'mobile_phone' => $v['card_ower_tel'],
    			'ID_NO' => '',
    			'birthday' => 0,
    			'work' => '',
    			'work_unit' => '',
    			'address' => '',
    			'recharge' => $v['card_total_count'],
    			'last_recharge' =>  $v['card_last_count'],
    			'last_recharge_time' =>  $v['card_last_time'],
    			'last_recharge_addr' => 5,
    			'last_consumption' =>  $v['card_last_pay'],
    			'last_consumption_addr' => 5,
    			'parent_id' => 0,
    			'relationship' =>  '',
    			'from' => 2,
    			'last_card_id' => 0,
    			'remark' => '',
    		];
    		$rs = MC::insert($data);
    		if($rs){
    			$r = Wallet::insert(['member_id'=>$v['id'],'cash'=>$v['count']]);
    			if(!$r){
    				echo $rs['id'].'<br/>';
    			}
    		}
    	}
		//$re = Db::connect('db_config_app')->table('dz_shop')->insert($data_app);
    }
}
