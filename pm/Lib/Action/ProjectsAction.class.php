<?php
class ProjectsAction extends CommonAction {
    //过滤查询字段
    /*function _filter(&$map){
        if(!empty($_POST['name'])) {
        $map['title'] = array('like',"%".$_POST['name']."%");
        }
        //dump($_SESSION);
    }*/
    public function _before_index(){
        cookie('_currentUrl_', __SELF__);
        //dump(cookie('_currentUrl_'));
    }
    
    function insert() {
        if(empty($_REQUEST['title'])) {
            $this->error('项目名称不能为空！');
        }
        
        $name = $this->getActionName();
        $model = D($name);
        if (false === $model->create()) {
            $this->error($model->getError());
        }
        //保存当前数据对象
        $list = $model->add();
        if ($list !== false) { //保存成功
            $proUser = M("ProjectsUser");
            $proUser->user_id=$_REQUEST['creator'];
            $proUser->project_id=$list;
            $proUser->add();
            if( !empty($_REQUEST['invite']) ) {
                import('@.ORG.Util.Mail');
                $mailContent='<p>Hi! '.$_SESSION['userName'].' 邀请你一起参与'.$_REQUEST['title'].'项目。</p><p>点击下面的链接，即可参与：</p><div> <a href="'.C('domain').U('Public/join',array('id'=>$list)).'" target="_blank"> '.C('domain').U('Public/join',array('id'=>$list)).' </a><div></div></div><p>如有问题，可与 '.$_SESSION['userName'].' 联系: <a href="mailto:'.$_SESSION['email'].'" target="_blank">'.$_SESSION['email'].'</a></p>';
                $mailArr=explode(',',$_REQUEST['invite']);
                foreach($mailArr as $k=>$v){
                    SendMail($v,$_REQUEST['title'].' 项目成员邀请函',$mailContent,'IT项目实验室(QQ群:273950048)');
                }
            }
            
            $this->success('创建成功!',cookie('_currentUrl_'));
        } else {
            //失败提示
            $this->error('创建失败!');
        }
    }
}