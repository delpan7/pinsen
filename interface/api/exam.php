<?php
/**
 * 试题相关接口文件
 */
include 'base.php';
class exam extends base{
	/**
	 * 试题概要
	 */
	public function outline($param){
		if($param['course_id'] && $param['uid']){
			$uid = intval($param['uid']);
			$course_id = intval($param['course_id']);
			
			$map['a.id'] = $course_id;
			$map['b.state'] = 1;
			$field = 'a.title as course_title,a.type,a.gold,b.rate,b.exam_id,b.title as exam_title,b.e_time';
			$data = M()->table(C('DB_PREFIX').'course a')->join(C('DB_PREFIX').'exam b on a.exam_id=b.exam_id')
			           ->field($field)->where($map)->find();
			if(!empty($data)){
				$data['course_level'] = $data['type'] == 1 ? ENTERPRISE : PLATFORM;
				$data['course_type'] = $this->course_types[$data['type']];
				unset($data['type']);
				
				$exam_id = intval($data['exam_id']);
				$question_data = M('exam_question')->field('count(1) as num,sum(max_value) as total_value')->where('exam_id='.$exam_id)->find();
				$data['question_count'] = intval($question_data['num']);
				$data['total_value'] = intval($question_data['total_value']);
				$rate = intval($data['rate']);
				$data['pass_value'] = $data['total_value']*$rate/10;
				
				//状态
				$wh['uid'] = $uid;
				$wh['course_id'] = $course_id;
				$record = M('course_record')->where($wh)->field('status')->find();
				$data['status'] = intval($record['status']) < 3 ? 1 : 2;
				
				$this->getResponse($data,'0');
			}else{
				$this->getResponse('','601');
			}
		}else{
			$this->getResponse('','999');
		}
	}
	
	/**
	 * 试题列表
	 */
	public function lists($param){
		if($param['exam_id']){
			$exam_id = intval($param['exam_id']);
			$field = 'quest_id,title,max_value,quest_type';
			$question_data = M('exam_question')->field($field)->where('exam_id='.$exam_id)->order('sort_no asc,quest_id desc')->select();
			$data = array();
			foreach($question_data as $k=>$v){
				$quest_id = intval($v['quest_id']);
				$data[$k]['quest_id'] = $quest_id;
				$data[$k]['title'] = trim($v['title']);
				$data[$k]['quest_type'] = intval($v['quest_type']);
				$data[$k]['max_value'] = intval($v['max_value']);
				$options = M('exam_options')->where('quest_id='.$quest_id)->field('opt_id,opt_name,opt_value')->order('sort_no asc,opt_id desc')->select();
				$data[$k]['options'] = $options ? $options : array();
			}
			$this->getResponse($data,'0');
		}else{
			$this->getResponse('','999');
		}
	}
	
	/**
	 * 考试结果
	 */
	public function result($param){
		if($param['exam_id'] && $param['result'] && $param['course_id'] && $param['uid']){
			$uid = intval($param['uid']);
			$exam_id = intval($param['exam_id']);
			$result = trim($param['result']);
			$course_id = intval($param['course_id']);
			
			$exam_info = M('exam')->where('exam_id='.$exam_id)->field('rate')->find();
			$rate = intval($exam_info['rate'])/10;
			$quest_info = M('exam_question')->where('exam_id='.$exam_id)->field('sum(max_value) as total_value')->find();
			$pass_value = intval($quest_info['total_value'])*$rate;
			//$submit_info = M('exam_options')->where("opt_id in ({$param['opt_ids']})")->field('sum(opt_value) as submit_value')->find();
			//$submit_value = intval($submit_info['submit_value']);
			
			$submit_value = 0;
			$result = json_decode($result,true);
			foreach($result as $val){
				$quest_id = intval($val['quest_id']);
				$opt_ids = trim($val['opt_ids']);
				$opt_arr = explode(',',$opt_ids);
				$opt_num = count($opt_arr);
				$quest = M('exam_question')->where("quest_id={$quest_id}")->find();
				
				if($quest['quest_type'] == 1 ){ //单选
					if($opt_num > 1){
						continue;
					}else{
						$option = M('exam_options')->where("opt_id={$opt_ids}")->field('opt_value')->find();
						if($option['opt_value'] > 0){
							$submit_value += intval($option['opt_value']);
						}
					}
				}else{ //多选
					$option = M('exam_options')->where("opt_id in ({$opt_ids}) and opt_value>0")->field("sum(opt_value) as submit_value,count(1) as num")->find();
					if($option['num'] == $opt_num){
						$submit_value += intval($option['submit_value']);
					}
				}
			}
			
			if($submit_value >= $pass_value){
				$course_info = M('course')->where('id='.$course_id)->field('type,gold')->find();
				if($course_info['type'] == 1){
					$data['gold'] = $course_info['gold'];
					//奖励金币
					$update['gold'] = array('exp',"`gold`+{$data['gold']}");
					M('member')->where('uid='.$uid)->save($update);
				}
				
				$save['status'] = 3;
				$save['update_time'] = time();
				
				$data['is_pass'] = '1';
				$data['submit_value'] = $submit_value;
			}else{
				$data['is_pass'] = '0';
				$data['submit_value'] = $submit_value;
				
				$save['status'] = 2;
				$save['update_time'] = time();
			}
			
			$map['uid'] = $uid;
			$map['course_id'] = $course_id;
			M('course_record')->where($map)->save($save);
			$this->getResponse($data,'0');
		}else{
			$this->getResponse('','999');
		}
	}
	
}