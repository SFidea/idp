<?php
class PublicAction extends Action {
    // 检查用户是否登录
    protected function checkUser() {
        if(!isset($_SESSION[C('USER_AUTH_KEY')])) {
            $this->error('没有登录','Public/login');
        }
    }
    
    public function create(){
        $User=D("User");
        if(!$User->create()) {
            $this->error($User->getError());
        }else{
            // 写入帐号数据
            if($result=$User->add()) {
                
                $this->addRole($result);
                /*$Teams=M("Teams");
                $Teams->title=$_REQUEST['title'];
                $Teams->creator=$_REQUEST['account'];
                $Teams->create_time=time();
                $rs=$Teams->add();
                $this->addTeamsUserMap($rs,$result);*/
                
                import('@.ORG.Util.Mail');
                $mailContent='<p>Hi, '.$_REQUEST['account'].':</p><p>欢迎使用项目协作平台, 点击下面的链接激活账号，以便获得更好的服务体验：</p><div><a href="'.C('domain').U('Public/active',array('id'=>$result)).'" target="_blank">'.C('domain').U('Public/active',array('id'=>$result)).'</a><div></div></div><p>如果你不清楚上述所说，请忽略此邮件哈。</p>';
                SendMail($_REQUEST['email'],'感谢您注册使用项目协作平台',$mailContent,'IT项目实验室(QQ群:273950048)');
                
            $_SESSION[C('USER_AUTH_KEY')]	=	$result;
            $_SESSION['email']	=	$_REQUEST['email'];
            $_SESSION['userName']		=	$_REQUEST['account'];
            //$_SESSION['lastLoginTime']		=	time();
            //$_SESSION['login_count']	=	$authInfo['login_count'];
            /*if($authInfo['account']=='admin') {
                $_SESSION['administrator']		=	true;
            }*/
            //保存登录信息
            $User	=	M('User');
            $ip		=	get_client_ip();
            $time	=	time();
            $data = array();
            $data['id']	=	$result;
            $data['last_login_time']	=	$time;
            $data['login_count']	=	array('exp','login_count+1');
            $data['last_login_ip']	=	$ip;
            $User->save($data);
            // 缓存访问权限
            import('@.ORG.Util.RBAC');
            RBAC::saveAccessList();
                
                $this->success('创建成功！',U('Projects/index'));
            }else{
                $this->error('创建失败！');
            }
        }
    }
    
    // 登录检测
    public function checkLogin() {
        if(empty($_POST['account'])) {
            $this->error('帐号错误！');
        }elseif (empty($_POST['password'])){
            $this->error('密码必须！');
        }/*elseif (empty($_POST['verify'])){
            $this->error('验证码必须！');
        }*/
        //生成认证条件
        $map            =   array();
        // 支持使用绑定帐号登录
        $map['account']	= $_POST['account'];
        /*$map["status"]	=	array('gt',0);
        if(session('verify') != md5($_POST['verify'])) {
            $this->error('验证码错误！');
        }*/
        import ( '@.ORG.Util.RBAC' );
        $authInfo = RBAC::authenticate($map);
        //dump($authInfo);
        //使用用户名、密码和状态的方式进行认证
        if(false === $authInfo) {
            $this->error('帐号不存在或已禁用！');
        }else {
            if($authInfo['password'] != md5($_POST['password'])) {
                $this->error('密码错误！');
            }
            $_SESSION[C('USER_AUTH_KEY')]	=	$authInfo['id'];
            $_SESSION['email']	=	$authInfo['email'];
            $_SESSION['userName']		=	$authInfo['account'];
            $_SESSION['lastLoginTime']		=	$authInfo['last_login_time'];
            $_SESSION['login_count']	=	$authInfo['login_count'];
            /*if($authInfo['account']=='admin') {
                $_SESSION['administrator']		=	true;
            }*/
            
            $menu  = array();
            if(isset($_SESSION['menu'.$_SESSION[C('USER_AUTH_KEY')]])) {
                //如果已经缓存，直接读取缓存
                $menu   =   $_SESSION['menu'.$_SESSION[C('USER_AUTH_KEY')]];
            }else {
                //读取数据库模块列表生成菜单项
                $node    =   M("Node");
                $id	=	$node->getField("id");
                $where['level']=2;
                $where['status']=1;
                $where['pid']=$id;
                $list	=	$node->where($where)->field('id,name,group_id,title')->order('sort asc')->select();
                if(isset($_SESSION['_ACCESS_LIST'])) {
                    $accessList = $_SESSION['_ACCESS_LIST'];
                }else{
                    import('@.ORG.Util.RBAC');
                    $accessList =   RBAC::getAccessList($_SESSION[C('USER_AUTH_KEY')]);
                }
                foreach($list as $key=>$module) {
                     if(isset($accessList[strtoupper(APP_NAME)][strtoupper($module['name'])]) || $_SESSION['administrator']) {
                        //设置模块访问权限
                        $module['access'] =   1;
                        $menu[$key]  = $module;
                    }
                }
                //缓存菜单访问
                $_SESSION['menu'.$_SESSION[C('USER_AUTH_KEY')]]	=	$menu;
            }
            
            //保存登录信息
            $User	=	M('User');
            $ip		=	get_client_ip();
            $time	=	time();
            $data = array();
            $data['id']	=	$authInfo['id'];
            $data['last_login_time']	=	$time;
            $data['login_count']	=	array('exp','login_count+1');
            $data['last_login_ip']	=	$ip;
            $User->save($data);

            // 缓存访问权限
            RBAC::saveAccessList();
            //dump($_SESSION);
            //exit(0);
            $this->success('登录成功！',U('Projects/index'));

        }
    }
    
    protected function addRole($userId) {
        //新增用户自动加入相应权限组
        $RoleUser = M("RoleUser");
        $RoleUser->user_id	=	$userId;
        // 默认加入网站编辑组
        $RoleUser->role_id	=	C('default_role');
        $RoleUser->add();
    }
    
    protected function addTeamsUserMap($teamId,$userId) {
        $RoleUser = M("TeamsUser");
        $RoleUser->user_id	=	$userId;
        $RoleUser->team_id	=	$teamId;
        $RoleUser->add();
    }

    // 顶部页面
    public function top() {
        C('SHOW_RUN_TIME',false);			// 运行时间显示
        C('SHOW_PAGE_TRACE',false);
        $model	=	M("Group");
        $list	=	$model->where('status=1')->getField('id,title');
        $this->assign('nodeGroupList',$list);
        $this->display();
    }

    public function drag(){
        C('SHOW_PAGE_TRACE',false);
        C('SHOW_RUN_TIME',false);			// 运行时间显示
        $this->display();
    }

    // 尾部页面
    public function footer() {
        C('SHOW_RUN_TIME',false);			// 运行时间显示
        C('SHOW_PAGE_TRACE',false);
        $this->display();
    }

    // 菜单页面
    public function menu() {
        $this->checkUser();
        if(isset($_SESSION[C('USER_AUTH_KEY')])) {
            //显示菜单项
            $menu  = array();
            if(isset($_SESSION['menu'.$_SESSION[C('USER_AUTH_KEY')]])) {
                //如果已经缓存，直接读取缓存
                $menu   =   $_SESSION['menu'.$_SESSION[C('USER_AUTH_KEY')]];
            }else {
                //读取数据库模块列表生成菜单项
                $node    =   M("Node");
                $id	=	$node->getField("id");
                $where['level']=2;
                $where['status']=1;
                $where['pid']=$id;
                $list	=	$node->where($where)->field('id,name,group_id,title')->order('sort asc')->select();
                if(isset($_SESSION['_ACCESS_LIST'])) {
                    $accessList = $_SESSION['_ACCESS_LIST'];
                }else{
                    import('@.ORG.Util.RBAC');
                    $accessList =   RBAC::getAccessList($_SESSION[C('USER_AUTH_KEY')]);
                }
                foreach($list as $key=>$module) {
                     if(isset($accessList[strtoupper(APP_NAME)][strtoupper($module['name'])]) || $_SESSION['administrator']) {
                        //设置模块访问权限
                        $module['access'] =   1;
                        $menu[$key]  = $module;
                    }
                }
                //缓存菜单访问
                $_SESSION['menu'.$_SESSION[C('USER_AUTH_KEY')]]	=	$menu;
            }
            if(!empty($_GET['tag'])){
                $this->assign('menuTag',$_GET['tag']);
            }
            //dump($menu);
            $this->assign('menu',$menu);
        }
        C('SHOW_RUN_TIME',false);			// 运行时间显示
        C('SHOW_PAGE_TRACE',false);
        $this->display();
    }

    // 后台首页 查看系统信息
    public function main() {
        $info = array(
            '操作系统'=>PHP_OS,
            '运行环境'=>$_SERVER["SERVER_SOFTWARE"],
            'PHP运行方式'=>php_sapi_name(),
            'ThinkPHP版本'=>THINK_VERSION.' [ <a href="http://thinkphp.cn" target="_blank">查看最新版本</a> ]',
            '上传附件限制'=>ini_get('upload_max_filesize'),
            '执行时间限制'=>ini_get('max_execution_time').'秒',
            '服务器时间'=>date("Y年n月j日 H:i:s"),
            '北京时间'=>gmdate("Y年n月j日 H:i:s",time()+8*3600),
            '服务器域名/IP'=>$_SERVER['SERVER_NAME'].' [ '.gethostbyname($_SERVER['SERVER_NAME']).' ]',
            '剩余空间'=>round((@disk_free_space(".")/(1024*1024)),2).'M',
            'register_globals'=>get_cfg_var("register_globals")=="1" ? "ON" : "OFF",
            'magic_quotes_gpc'=>(1===get_magic_quotes_gpc())?'YES':'NO',
            'magic_quotes_runtime'=>(1===get_magic_quotes_runtime())?'YES':'NO',
            );
        $this->assign('info',$info);
        $this->display();
    }

    // 用户登录页面
    public function login() {
        if(!isset($_SESSION[C('USER_AUTH_KEY')])) {
            $this->display();
        }else{
            $this->redirect('Projects/index');
        }
    }

    public function index() {
        //如果通过认证跳转到首页
        redirect(__APP__);
    }

    // 用户登出
    public function logout() {
        if(isset($_SESSION[C('USER_AUTH_KEY')])) {
            unset($_SESSION[C('USER_AUTH_KEY')]);
            unset($_SESSION);
            session_destroy();
            $this->success('登出成功！',__URL__.'/login/');
        }else {
            $this->error('已经登出！');
        }
    }

    // 更换密码
    public function changePwd() {
        $this->checkUser();
        //对表单提交处理进行处理或者增加非表单数据
        if(md5($_POST['verify'])	!= $_SESSION['verify']) {
            $this->error('验证码错误！');
        }
        $map	=	array();
        $map['password']= pwdHash($_POST['oldpassword']);
        if(isset($_POST['account'])) {
            $map['account']	 =	 $_POST['account'];
        }elseif(isset($_SESSION[C('USER_AUTH_KEY')])) {
            $map['id']		=	$_SESSION[C('USER_AUTH_KEY')];
        }
        //检查用户
        $User    =   M("User");
        if(!$User->where($map)->field('id')->find()) {
            $this->error('旧密码不符或者用户名错误！');
        }else {
            $User->password	=	pwdHash($_POST['password']);
            $User->save();
            $this->success('密码修改成功！');
         }
    }

    public function profile() {
        $this->checkUser();
        $User	 =	 M("User");
        $vo	=	$User->getById($_SESSION[C('USER_AUTH_KEY')]);
        $this->assign('vo',$vo);
        $this->display();
    }

    public function verify() {
        $type	 =	 isset($_GET['type'])?$_GET['type']:'gif';
        import("@.ORG.Util.Image");
        Image::buildImageVerify(4,1,$type);
    }

    // 修改资料
    public function change() {
        $this->checkUser();
        $User	 =	 D("User");
        if(!$User->create()) {
            $this->error($User->getError());
        }
        $result	=	$User->save();
        if(false !== $result) {
            $this->success('资料修改成功！');
        }else{
            $this->error('资料修改失败!');
        }
    }
}