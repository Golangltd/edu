<?php
/**
 *    BaseController(Admin\Controller\BaseController.class.php)
 *
 *    �������ܣ���̨��ҳ���������
 *
 *    �������ߣ��
 *    ���ʱ�䣺2018/04/10
 *    �ޡ����ģ�2018/04/10
 *
 */
namespace Admin\Model;

use Think\Model\ViewModel;

class CourseUserViewModel extends ViewModel
{
    public $viewFields = array(
        'course' => array(
            'id',
            'title',
            'serializemode',
            'studentnum',
            'status',
            '_type'=>'LEFT'
        ),
        'user' => array(
            'nickname' => 'createduser',
            '_on'=>'course.userId=user.id',
        )
    );
}