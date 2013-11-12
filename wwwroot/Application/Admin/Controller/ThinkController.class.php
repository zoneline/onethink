<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------

namespace Admin\Controller;

/**
 * 模型数据管理控制器
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */

class ThinkController extends AdminController {

    /**
     * 显示指定模型列表数据
     * @param  String $model 模型标识
     * @author 麦当苗儿 <zuojiazi@vip.qq.com>
     */
    public function lists($model = null, $p = 0){
        $model || $this->error('模型名标识必须！');
        $page = intval($p);
        $page = $page ? $page : 1; //默认显示第一页数据

        //获取模型信息
        $model = M('Model')->getByName($model);
        $model || $this->error('模型不存在！');
		
        //解析列表规则
        $fields = array();
        $grids  = preg_split('/[;\r\n]+/s', $model['list_grid']);
        foreach ($grids as &$value) {
            $val      = explode(':', $value);
            $val[0]   = explode('|', $val[0]);
            $value    = array('field' => $val[0], 'title' => $val[1]);
			if(isset($val[2])){
				$value['href']	=	$val[2];
			}
            $fields[] = $val[0][0];
        }
		// 关键字搜索
		$map	=	array();
		$key	=	$model['search_key']?$model['search_key']:'title';
		if(isset($_REQUEST[$key])){
			$map[$key]	=	array('like','%'.$_GET[$key].'%');
			unset($_REQUEST[$key]);
		}
		// 条件搜索
		foreach($_REQUEST as $name=>$val){
			if(in_array($name,$fields)){
				$map[$name]	=	$val;
			}
		}
        $row    = empty($model['list_row']) ? 10 : $model['list_row'];

        //读取模型数据列表
        if($model['extend']){
            $name   = get_table_name($model['id']);
            $parent = get_table_name($model['extend']);
            $fix    = C("DB_PREFIX");

            $key = array_search('id', $fields);
            if(false === $key){
                array_push($fields, "{$fix}{$parent}.id as id");
            } else {
                $fields[$key] = "{$fix}{$parent}.id as id";
            }

			/* 查询记录数 */
			$count = M($parent)->join("RIGHT JOIN {$fix}{$name} ON {$fix}{$parent}.id = {$fix}{$name}.id")->where($map)->count();

			// 查询数据
            $data   = M($parent)
                ->join("RIGHT JOIN {$fix}{$name} ON {$fix}{$parent}.id = {$fix}{$name}.id")
                /* 查询指定字段，不指定则查询所有字段 */
                ->field(empty($fields) ? true : $fields)
				// 查询条件
				->where($map)
                /* 默认通过id逆序排列 */
                ->order("{$fix}{$parent}.id DESC")
                /* 数据分页 */
                ->page($page, $row)
                /* 执行查询 */
                ->select();

        } else {
            in_array('id', $fields) || array_push($fields, 'id');
            $name = parse_name(get_table_name($model['id']), true);
            $data = M($name)
                /* 查询指定字段，不指定则查询所有字段 */
                ->field(empty($fields) ? true : $fields)
				// 查询条件
				->where($map)
                /* 默认通过id逆序排列 */
                ->order('id DESC')
                /* 数据分页 */
                ->page($page, $row)
                /* 执行查询 */
                ->select();

			/* 查询记录总数 */
			$count = M($name)->where($map)->count();
        }

        //分页
        if($count > $row){
            $page = new \COM\Page($count, $row);
            $page->setConfig('theme','%FIRST% %UP_PAGE% %LINK_PAGE% %DOWN_PAGE% %END% %HEADER%');
            $this->assign('_page', $page->show());
        }

        $this->assign('model', $model);
        $this->assign('list_grids', $grids);
        $this->assign('list_data', $data);
		$this->meta_title = $model['title'].'列表';
        $this->display($model['template_list']);
    }

    public function del($model = null, $ids=null){
        $model = M('Model')->find($model);
        $model || $this->error('模型不存在！');

        $ids = array_unique((array)I('ids',0));


        if ( empty($ids) ) {
            $this->error('请选择要操作的数据!');
        }

        $Model = M(get_table_name($model['id']));
        $map = array('id' => array('in', $ids) );
        if($Model->where($map)->delete()){
            $this->success('删除成功');
        } else {
            $this->error('删除失败！');
        }
    }

    public function edit($model = null, $id = 0){
        //获取模型信息
        $model = M('Model')->find($model);
        $model || $this->error('模型不存在！');

        if(IS_POST){
            $Model = M(get_table_name($model['id']));
            if($Model->create() && $Model->save()){
                $this->success('保存成功！', U('lists?model='.$model['name']));
            } else {
                $this->error('保存出错！');
            }
        } else {
            $fields = get_model_attribute($model['id']);

            //获取数据
            $data = M(get_table_name($model['id']))->find($id);
            $data || $this->error('数据不存在！');

            $this->assign('model', $model);
            $this->assign('fields', $fields);
            $this->assign('data', $data);
			$this->meta_title = '编辑'.$model['title'];
            $this->display();
        }
    }

    public function add($model = null){
        //获取模型信息
        $model = M('Model')->where(array('status' => 1))->find($model);
        $model || $this->error('模型不存在！');
        if(IS_POST){
            $Model = M(get_table_name($model['id']));

            if($Model->create() && $Model->add()){
                $this->success('添加成功！', U('lists?model='.$model['name']));
            } else {
                $this->error('添加出错！');
            }
        } else {

            $fields = get_model_attribute($model['id']);

            $this->assign('model', $model);
            $this->assign('fields', $fields);
			$this->meta_title = '新增'.$model['title'];
            $this->display();
        }
    }

}