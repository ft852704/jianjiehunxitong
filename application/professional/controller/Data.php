<?php
namespace app\index\controller;
use app\index\controller\Base;
use think\Validate; 
use think\Request;
use app\index\model\Data as DataModel;
use app\index\model\SetPayWay;
use think\Db;

class Data extends Base
{
	public $rule = [
	            //常用资料添加字段
	            'number|编号'   => 'require',
				'name|名称' => 'require',
				'status|状态'     => 'require',
	        ];
	//常用资料主页
    public function index()
    {
	    $data = new DataModel;
	    $where = array('parent_id'=>'0');
	    $datap = array('search'=>'');
	    if(Request::instance()->get()){
	    	$datap = input('get.');
	    	//搜索条件
	    	$where =  array('name'=>array('like','%'.$datap['search'].'%'));
	    	$where['parent_id'] = 0;
	    }
    	$list = $data->where($where)->order('status,id', 'ASC')->paginate(15,false,array('query'=>$datap));
    	$this->assign('list',$list);
    	$this->assign('search',$datap);
    	return $this->fetch();
    }
    //子分类列表
    public function childlist($parent_id = 0){
    	$data = new DataModel;
	    $where = array('parent_id'=>$parent_id);

    	$list = $data->where($where)->order('status,number', 'ASC')->paginate(15,false,array('query'=>$where));
    	foreach ($list as $k => &$v) {
    		$name = Db::table("used_data")->where("id=".$v['parent_id'])->find();
    		if(!empty($name)){
    			$v['parent_name'] = $name['name'];
    		}else{
    			$v['parent_name'] = '无';
    		}
    	}

    	$this->assign('parent_id',$parent_id);
    	$this->assign('list',$list);
    	return $this->fetch();
    }
    //添加分类
    public function add(){ 
    	if(Request::instance()->isPost()){
    		$data = input('post.');
	        $datamodel = new DataModel;
	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);
	        if(!$result){
	            return  $validate->getError();
	        }else{
	        	$num_result = $this->validate_number($data['number'],$data['parent_id']);
	        	if(!$num_result){
	        		return '编码重复!';
	        	}

	        	if($id = $datamodel->save($data)){
	        		if($datamodel->parent_id>0){
	    				return $this->success('修改成功','/Index/data/childlist/parent_id/'.$datamodel->parent_id.'.html');
	    			}else{
	    				return $this->success('修改成功','/Index/data/index');
	    			}
		            return $this->success('分类添加成功','/Index/data/index');
		        } else {
		            return $this->error($datamodel->getError());
		        }
	        }
	        
    	}else{
    		$data = input('get.');
    		$parent_id = isset($data['parent_id'])?$data['parent_id']:0;
    		$number = '';
    		if(!$parent_id){
    			$numberlist = Db::table('used_data')->field('number')->where('parent_id=0')->select();
    			$max = max($numberlist);
    			$number = $max['number']+1;
    		}
    		$this->assign('number',$number);
    		$this->assign('parent_id',$parent_id);

    		$datalist = Db::table('used_data')->where('parent_id=0')->select();
    		$this->assign('list',$datalist);
    		return $this->fetch();
    	}
    	
    }
    //编辑常用资料信息
    public function edit($id = null){
    	if(Request::instance()->isPost()){
    		$data = input('post.');
    		$id = $data['id'];
	        $used_data = new DataModel;

	        // 数据验证
	        $validate = new Validate($this->rule);
	        $result   = $validate->check($data);

	        if(!$result&&!$id){
	            return  $validate->getError();
	        }else{
	        	$num_result = $this->validate_number($data['number'],$data['parent_id'],$id);
	        	if(!$num_result){
	        		return '编码重复!';
	        	}
	        	if($id = $used_data->save($data,array('id'=>$id))){
		            if($used_data->parent_id>0){
	    				return $this->success('修改成功','/Index/data/childlist/parent_id/'.$used_data->parent_id.'.html');
	    			}else{
	    				return $this->success('修改成功','/Index/data/index');
	    			}
		        } else {
		            return $this->error($used_data->getError());
		        }
	        }

    	}else{
    		if(!empty($id)&&is_numeric($id)){
    			$datalist = Db::table('used_data')->where('parent_id=0')->select();
	    		$this->assign('list',$datalist);

    			$re = Db::table('used_data')->where('id='.$id)->find();
    			$this->assign('data',$re);
    			return $this->fetch();
    		}
    	}
    }
    //更改常用资料状态
    public function del($id=null){
    	if($id&&is_numeric($id)){
    		$data = DataModel::get($id);
    		if($data->status==1)$data->status=0;
    		elseif($data->status===0)$data->status=1;
    		if($data->save()){
    			if($data['parent_id']>0){
    				return $this->success('操作成功','/Index/data/childlist/parent_id/'.$data['parent_id'].'.html');
    			}else{
    				return $this->success('操作成功','/Index/data/index');
    			}
    		}else{
    			return $this->error($data->getError());
    		}
    	}else{
    		return $this->success('错误操作','/Index/data/index');
    	}
    }
    //设置支付方式到营业额
    public function setpayway(){
    	if(Request::instance()->isPost()){
    		//$re = Db::table('set_pay_way')->where('company=1')->find();
    		$setpayway = new SetPayWay();
    		$re = $setpayway->where('company=1')->find();
    		$re->text = serialize(input('post.'));
    		if($re->save()){
    			return $this->success('操作成功','/Index/data/setpayway');
    		}else{ 
    			return $this->error($setpayway->getError());
    		}
    	}else{
    		$data = Db::table('used_data')->where('parent_id','in','10,6')->select();
    		$setpayway = new SetPayWay();
    		$re = $setpayway->where('company=1')->find();
    		$re = @unserialize($re['text']);
    		$this->assign('re',$re);

	    	$this->assign('list',$data);
	    	return $this->fetch();
    	}
    	
    }
    //验证子类编码的唯一性
    //@ $number 编号
    //@ $parent_id 父id
    //@ $id 数据id
    public function validate_number($number,$parent_id=0,$id=0){
    	$data = Db::table('used_data')->where(array('number'=>$number,'parent_id'=>$parent_id))->find();
    	if(!empty($data)){
    		if($id==$data['id']){
    			return true;
    		}else{
    			return false;
    		}
    	}else{
    		return true;
    	}
    }
}
