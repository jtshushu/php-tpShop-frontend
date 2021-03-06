<?php

namespace Home\Controller;

use Layout\Controller\LayoutController;

class IndexController extends LayoutController {

    //首先,执行父控制方法
  public function __construct() {
    parent::__construct();
  }

    //首页初始化方法
  public function index() {
        $goods = D('goods'); //商品表
        $g1 = $goods->get_goods_by_recname('疯狂抢购');
        $g2 = $goods->get_goods_by_recname('热卖商品');
        $g3 = $goods->get_goods_by_recname('推荐商品');
        $g4 = $goods->get_goods_by_recname('新品上架');
        $g5 = $goods->get_goods_by_recname('猜您喜欢');
        //取出推荐到中间的顶级分类 只将2级分类当成推荐分类,2级分类下的分类不会被推荐到首页去
        $categorys = D('category');
        //取出推荐到'首页中间大类推荐'的顶级分类
        $cat = $categorys->get_cat_by_recname('首页中间大类推荐');
        //循环这个顶级分类取出这个大类下的二级分类,三级的不取
        foreach ($cat as $k => $c) {
//            $rec_array = array(); //存储最终的2级分类和2级分类下的分类
            $cat[$k]['recSubcat'] = $categorys->get_cat_by_recname('首页中间大类推荐', $c['id'], 4); //取出首页中间大类推荐的下属被推荐分类
            //取出没有被推荐的二级分类       
            $recCatId = array();
            $recCatSub = array(); //所有被推荐的二级分类的子分类
            foreach ($cat[$k]['recSubcat'] as $kk => $cc) {
              $recCatId[] = $cc['id'];
                //取出这个被推荐分类的所有子分类
              $_subCat = $categorys->get_category($cc['id'], true);
                $_subCat[] = $cc['id']; //将当前这个分类也加入进去
                $recCatSub = array_merge($recCatSub, $_subCat);
                $_subCat = implode(',', $_subCat);
                $recCatSub[] = $cc['id']; //把二级分类本身也放进来
                $cat[$k]['recSubcat'][$kk]['goods'] = $goods->get_goods_by_recname('首页中间大类推荐', 8, 'cat_id in(' . $_subCat . ')');
              }
            $recCatIdStr = implode(',', $recCatId); //将所有的没有被推荐到额二级分类转化为字符串
            if ($recCatId)//假如有被推荐的二级分类,就排除,放到这个大类下面
            $cat[$k]['subCat'] = $categorys->where('parent_id=' . $c['id'] . ' and id not in(' . $recCatIdStr . ')')->select();
            else//否则就不排除,直接取出全部的
            $cat[$k]['subCat'] = $categorys->where('parent_id=' . $c['id'])->select();
            //取出这个大类下所有子分类的id
            $children_id = $categorys->get_category($c['id'], true);
            $children_id[] = $c['id']; //把顶级分类的id也放进来
            //从所有的子分类中去掉所有被推荐的分类
            $allNotRecCatId = array_diff($children_id, $recCatSub);
            $allNotRecCatId = implode(',', $allNotRecCatId);
            //取出这个大类下第一框"推荐商品"中的八件商品(这个大类下没有被推荐的分类下的商品)
            $cat[$k]['goods'] = $goods->get_goods_by_recname('首页中间大类推荐', 8, 'cat_id in(' . $allNotRecCatId . ')');
            //将当前大类以及所有的子类都集中成字符串
            $children_id = implode(',', $children_id);
            //判断下这个字符串的存在,因为如果为空会影响下面的sql报错
            if ($children_id)
            //取出商品的cat_id在这个大类里面的,并且品牌id不能为空    
              $brands = $goods->alias('a')->field('b.brand_logo,b.brand_url')->join('sh_brand b on a.brand_id=b.id')->where('cat_id in (' . $children_id . ') and brand_id!=0')->select();
            $cat[$k]['brand'] = array_unique($brands); //去掉重复的,因为商品对应的品牌有相同的(其实用mysql的命令既可以)
          }
        //取出广告的信息
          $ad = D('ad');
        $ad1 = $ad->get_ad(1); //取出id为1的广告位
        $ad2 = $ad->get_ad(2); //取出id为2的广告位
        //将数据传递到页面
        $this->assign(array(
          //网站首页的标题
          'page_title' => '首页',
          //网站首页使用的css文件名
          'css' => array('index'),
          //网站首页使用的js文件名
          'js' => array('index'),
          //是否显示下拉导航菜单,不需要就不要传参数
          'nav_show' => 1,
          //网站首页的关键字
          'page_keywords' => '',
          //网站首页的描述
          'page_description' => '',
          'goods1' => $g1,
          'goods2' => $g2,
          'goods3' => $g3,
          'goods4' => $g4,
          'goods5' => $g5,
          'cat' => $cat,
          'ad1' => $ad1,
          'ad2' => $ad2
          )
        );
        $this->display();
      }

    //商品展示
    /**
     * 
     * @param type $id
     */
    public function goods($id) {
        //生成商品表
      $goods = M('goods');
        //取出当前商品的信息
      $goods_data = $goods->where('id=' . $id)->find();
        //取出当前商品的商品类型中的属性信息
      $sql = "select a.*,b.attr_name,b.attr_type from sh_goods_attr a left join sh_attribute b on a.attr_id=b.id where goods_id=$id";
      $attrs = $goods->query($sql);
        //将单选属性和唯一属性拆分开
        $requires = array(); //唯一的属性
        $radio = array(); //单选的属性
        foreach ($attrs as $key => $value) {
          if ($value['attr_type'] == '唯一') {
            $requires[] = $value;
          } else {
                //将商品属性attr_id作为数组下标,便于以后循环    
            $radio[$value['attr_id']][] = $value;
          }
        }

        //取出当前商品的商品图片 
        $goods_pic = M('goodsPic');
        $goods_pics = $goods_pic->where('goods_id=' . $id)->select();

        $this->assign(array(
          //商品页的标题
          'page_title' => '商品页',
          //商品页使用的css
          'css' => array('goods', 'common'),
          //商品页使用的js文件
          'js' => array('goods', 'jqzoom-core'),

          //商品信息
          'goods' => $goods_data,
          //商品单选属性
          'radio' => $radio,
          //商品唯一的属性
          'requires' => $requires,
          //商品图片
          'goods_pic' => $goods_pics
          ));
        $this->display();
      }

    //会员注册检测
      public function regist() {
        $cart = D('cart');
        $cart->delCart(9, 1);
        if (IS_POST) {
          $member = D('member');
          if ($member->create()) {
                //将表单收集的用户名和密码保存起来,add()添加完数据后会删除掉
            $username = $member->username;
            $password = $member->password;
                //add(),表单新增数据成功后,会将收集到的会员数据全部清空,所以提前保存
            if ($member->add()) {
              $member->username = $username;
              $member->password = $password;
                    //注册成功后,调用登录方法
              if ($member->login() == true)
                    //会员注册成功后,直接跳到首页并登录成功    
                $this->success('注册成功', U('login'));
              die;
            }
          }else {
            $this->error($member->getError());
            die;
          }
        }


        $this->display();
      }

    //生成验证码
      public function Verify() {
        ob_clean();
        $Verify = new \Think\Verify();
        $Verify->entry();
      }

    //会员邮箱验证
      public function chkReg($sn) {
        $member = M('member');
        $data = $member->field('id')->where('email_chk_str="' . $sn . '"')->find();
        if ($data['id']) {
          $member->where('id=' . $data['id'])->setField('email_checked', '已验证');
          $this->success("邮箱验证成功", U('login'));
          die;
        } else {
          $this->error("邮箱验证错误");
          die;
        }
      }

    //ajax检测会员注册时,username是否重名
      public function ajaxChkName($name) {
        $member = M('member');
        //直接统计是否有记录数
        $count = $member->where('username="' . $name . '"')->count();
        //有返回1
        if ($count > 0) {
          echo -1;
            //没有返回1    
        } else {
          echo 1;
        }
      }

    //ajax检测会员注册时,email是否重名
      public function ajaxChkEmail($email) {
        $member = M('member');
        //直接统计是否有记录数
        $count = $member->where('email="' . $email . '"')->count();
        //有返回1
        if ($count > 0) {
          echo -1;
            //没有返回1    
        } else {
          echo 1;
        }
      }

    //ajax检测会员注册时,验证码是否正确
      public function ajaxChkCode($code) {
        $verify = new \Think\Verify();
        //tp默认验证成功后是将session中的验证码删除,设置reset就是将删除session取消掉
        $verify->reset = false;
        echo $verify->check($code);
      }

    //会员登录验证
      public function login() {
        $member = D('member');
        if (IS_POST) {
            //标记现在是登录的状态,第一个参数表示接收表单.第二个参数表示当前是4,表示登录
            //如果是添加可以不填     
          if ($member->create($_POST, 4)) {
            if ($member->login() == true) {
          //判断下session中returnUrl是否存在,存在就跳到这个链接上,用于返回登录前的页面    
              if(!is_null(session("returnUrl"))){
                $this->success('登录成功',session("returnUrl"));
              //然后将其删除掉
                session("returnUrl",null);
                die;
              }


              $this->success('登录成功', '/');
              die;
            } else {
              $this->error('用户名或密码错误');
              die;
            }
          } else {
            $this->error($member->getError());
            die;
          }
        }
        $this->display();
      }

    //首页ajax检测用户是否登录
      public function ajaxChkLogin() {
        //先判断下session中是否有用户名的存在
        $id = session('id');
        $usernmae = session('username');
        //如果session中有id和usernmae的话,直接取出来转换成json返回
        if ($id && $usernmae) {
          $arr = array('id' => $id, 'username' => $usernmae);
            //将php中的数组转换成json格式返回
          echo json_encode($arr);
            // 如果用户没有登录，判断cookie中是否有用户名和密码    
        } else {
            //如果用户没有登录,检测cookie中是否保存了用户信息,有就直接登录,没有返回-1
          if (isset($_COOKIE['username']) && isset($_COOKIE['password'])) {
            $member = D('member');
                //有就将cookie中的信息解密,赋给$member模型
            $member->username = \Think\Crypt::decrypt($_COOKIE['username'], C('Des_key'));
            $member->password = \Think\Crypt::decrypt($_COOKIE['password'], C('Des_key'));
                //调用$member模型的login方法,检查是否能登录成功,能则返回json数据,显示用户登录
            if ($member->login() == true) {
              $arr2 = array('id' => session('id'), 'username' => $data_username);
              echo json_encode($arr2);
            }
          } else {
                //如果最后用户还是没登录,返回-1    
            echo -1;
          }
        }
      }

    //用户退出,清空session并跳转
      public function logout() {
        //用户手动点击退出后,将session清空,cookie中的usernmae和password清空,不再保留30天信息
        session(null);
        setcookie('username', '', 1, '/');
        setcookie('password', '', 1, '/');
        $this->success('已经退出', U('login'));
      }

    //忘记密码功能,别人的思路,最终没有实现,还是使用自己的方法吧,原因:刷新页面时,会自动提交表单给forget_password,自动就发送邮件了
//  public function forget_password(){
//    if(IS_POST){
//      $member=D('member'); 
//     //手动接收表单 
//      $username=I('post.username');  
//      $user=$member->where('username="'.$username.'"')->find();
//      if($user){
//          if($user['email_checked']=='已验证'){
//           //生成验证码,并发到邮箱中
//           //取唯一字符串的后7位,作为会员验证码   
//          $get_pass_code=substr(uniqid(),-7);
//          //记录生成时的时间,好进行比对
//          $get_pass_code_time=time();    
//          $member->where('id='.$user['id'])->save(array(
//            'get_pass_code'=>$get_pass_code,  
//            'get_pass_code_time'=>$get_pass_code_time,  
//          ));  
//          $content='找回密码的验证码'.$get_pass_code;
//          sendMail($user['email'], '会员找回密码', $content);
//          $this->assign(array('username'=>$user['username']));
//          $this->display('writecode');
//          die;
//          }else{
//       //如果用户没有验证,就把用户的emial和email_chk_str,放到隐藏域中       
//          $this->assign(array(
//              'email'=>$user['email'],
//              'email_chk_str'=>$user['email_chk_str']
//          ));    
//          $this->display('email_checked');    
//          }
//      }else{
//          $this->error('用户名错误');
//          die;
//      }     
//    } 
//   $this->display();
//  }
    //ajax接收用户名后重新发送验证码,功能未实现
//  public function ajaxReSendCode($username){
//      $member=D('member');
//      $info=$member->where('username="'.$username.'"')->find();
//      if($info){
//      //取唯一字符串的后7位,作为会员验证码   
//      $get_pass_code=substr(uniqid(),-7);
//      //记录生成时的时间,好进行比对
//      $get_pass_code_time=time();       
//      $member->where('id='.$info['id'])->save(array(
//      'get_pass_code'=>$get_pass_code,  
//      'get_pass_code_time'=>$get_pass_code_time,  
//      ));  
//      $content='找回密码的验证码'.$get_pass_code;
//        
//      }
//  }
    //会员忘记密码,并且注册后没有验证email_chk_str,需要重新发送email_chk_str验证
//  public function sendEmailChkCode(){
//      //接收隐藏域中的emial和email_chk_str,再发给用户
//      $email=I('post.email');
//      $email_chk_str=I('post.email_chk_str');
//      $content=" 你好,感谢您的注册，请阅读以下内容<br/>点击以下链接完成注册:<br/><a href=http://www.jdd.com/index.php/Home/Index/chkReg/sn/".$email_chk_str.">http://www.jdd.com/index.php/Home/Index/chkReg/sn/".$email_chk_str."</a><br/> 您已经注册成为百度云网盘资源下载论坛的会员，下资源xiazy.com专注精致分享，精品资源天天有，每天期待您的到来，欢迎推荐您的好友一起加入！ 官方QQ群：371179161 荣誉会员QQ群：333014370 站长QQ：2011820123 如果您有什么疑问可以联系管理员，Email: 2011820123@qq.com 下资源<br/>".date('Y-m-d H:i:s');
//      sendMail($email,'注册信息',$content);
//      $this->success('验证码已经发到您的邮箱,请登录邮箱进行验证');
//  }
    //按自己的思路实现的用户忘记密码后,用户输入用户名和邮箱后,先向用户邮箱发送一个链接,跳转到change_password
//    public function forgetpassword(){
//      if(IS_POST){
//        //生成模型
//        $member=D('member');  
//        //收集表单,新定义的区别于别的5
//        if($member->create($_POST,5)){
//        //调用模型中的方法,检查用户输入的用户名和邮箱的真假    
//        $info=$member->chekEmail();
//        if($info=='-1'){//错误信息
//           $this->error('用户名或邮箱错误'); 
//            die;
//        }else if($info=='-2'){//错误信息
//         $this->error('用户名或邮箱错误'); 
//           die;   
//        }else{//用户名和邮箱都正确
//           $this->success('请前往邮箱中确认'); 
//           die; 
//         }    
//        }else{//表单验证错误
//            $this->error($member->getError());
//            die;
//        }  
//      }
//     $this->display(); 
//  }
    //按自己思路实现的用户忘记密码后,当点击邮箱里面的链接跳转到的地址
      public function change_password($email_chk_str) {
        //生成模型
        $member = D('member');
        //判断用户点击了邮箱的链接跳转过来的
        if (IS_GET) {
            //检查用户点击邮箱链接时,传递过来的$email_chk_str合法性    
          $email_info = $member->where('email_chk_str="' . $email_chk_str . '"')->find();
            //如果合法,就将用户名输出到页面中,并载入新模板
          if ($email_info) {
            $this->assign(array(
              'user' => $email_info['username'],
              'email_chk_str' => $email_info['email_chk_str']
              ));
            $this->display();
            die;
          }
            //判断在新模板用户提交了表单 
        } else if (IS_POST) {
            //收集表单,因为是修改表单,所以应该是2   
          if ($member->create($_post, 2)) {
                //接收隐藏表单,用来操作哪一个用户,否则没法修改,没有条件    
            $email_chk_str = I('post.email_chk_str');
                //判断通过表单提交的用户名是否和数据中的一致,一致就修改数据库  
            $str = $member->where('email_chk_str="' . $email_chk_str . '"')->save(array(
              'password' => md5($member->password),
              ));
            if ($str) {
                    //提示并跳转     
              $this->success('密码修改成功', U('login'));
              die;
            } else {
              $this->error($member->getError('表单提交错误'));
              die;
            }
                //表单收集错误   
          } else {
            $this->error($member->getError());
            die;
          }
        }
      }

    //ajax检查是否显示评论功能
      public function ajaxGetRemarkConfig() {
        //如果所有用户都可以评论返回1,否则返回-1
        if ($this->config['用户评论规则'] == '全部')
          echo 1;
        else
          echo -1;
      }

//ajax验证用户对商品的评论
      public function ajaxRemark() {
        if (IS_POST) {
            //將用戶的id在session中,取出做服務器端驗證,防止跨站攻擊
          $id = session('id');
            //判断如果当前会员评论规则为只有会员才能评论且session中没有会员的id登录,就不允许提交
          if ($this->config['用户评论规则'] == '会员' && !$id)
            $this->error('匿名用户不允许评论');
            //生成remark模块
          $remark = D('remark');
            //自动收集表单  
          if ($remark->create()) {
                //将商品id保存起来,印象表会用到,一会add()后会删除掉 
            $goods_id = $remark->goods_id;
                //如果收集表单成功,则自动添加到数据库中
            if ($remark->add()) {
              /*                     * ****************************处理会员对商品的印象*************** */
                    //接收印象字段的值
              $yx = I('post.yx_name');
                    //生成印象的模型
              $yinxiang = M('yinxiang');
                    //将中文逗号替换成英文逗号
              $yx = str_replace('，', ',', $yx);
                    //将传递过来的印象,用英文逗号进行分隔
              $yx = explode(',', $yx);
                    //循环这个数组
              foreach ($yx as $k => $v) {
                        //判断如果这个印象已经存在,就更新数量否则就插入新的
                        //判断如果数据表中有相同的印象名并且是通一个商品id的话  
                $count = $yinxiang->where('yx_name="' . $v . '" and goods_id="' . $goods_id . '"')->count();
                if ($count > 0) {
//有的话,就增加yx_count这个字段的数量+1,使用thinkphp自带的setInc()延迟更新方法,延迟60秒    
                  $yinxiang->where('yx_name="' . $v . '" and goods_id=$goods_id')->setInc('yx_count', 60);
//如果没有的话,就要新添加到数据库中
                } else {
//收集yx_name,yx_count,goods_id插入数据库           
                  $yinxiang->add(array(
                    'yx_name' => $v,
                    'yx_count' => 1,
                    'goods_id' => $goods_id,
                    ));
                }
              }
              $this->success('评论成功');
                    //如果表单自动添加失败,报错    
            } else {
              $this->error($remark->getError());
            }
                //如果表单收集失败,报错    
          } else {
            $this->error($remark->getError());
          }
        }
        //如果不是post过来的报错
        $this->error('表单发送错误');
      }

//ajax取出用户对当前商品的评论和印象
      public function ajaxGetRemark($pages, $goods_id) {
        //设置每页显示10条
        $limit = 2;
        //设置翻页算法
        $start = ($pages - 1) * $limit;
        //将匿名用户的名字取出来,如果是匿名用户评论的就用这个名
        $username = $this->config['匿名用户的名称'];
        //将匿名用户的头像图片取出来,如果是匿名用户评论的就使用这个头像
        $face = $this->config['匿名用户头像'];
        //生成评论表的模型
        $remark = M('remark');
        //sql语句用于连表查询,当前商品id的会员以及匿名用户的评论,ifnull()如果第一个参数是null,就返回第二个参数
        $sql = "select a.*,ifnull(b.username,'" . $username . "') username,ifnull(b.face,'" . $face . "') face from sh_remark a left join sh_member b on a.member_id=b.id where a.goods_id=" . $goods_id . " order by a.id desc limit $start,$limit ";
        //执行sql语句,这里赋给remark这个属性,这样前台好循环
        $data['remark'] = $remark->query($sql);
        //将印象表中的数据全都取出来
        $sql2 = 'select * from sh_yinxiang where goods_id=' . $goods_id;
        //将印象的相关内容存储到data数组中,一起assign到页面中
        $data['yinxiang'] = $remark->query($sql2);
        //定义3个变量分别为总记录数
        $count = 0;
        //好评数
        $good = 0;
        //中评数
        $middle = 0;
        //差评数
        $cha = 0;
        //将评论表中的star评分取出来
        $remark_data = $remark->field('star')->where('goods_id=' . $goods_id)->select();
        //循环这个2维数组
        foreach ($remark_data as $y) {
            //循环一次说明有一条评论记录,评论数加一  
          $count++;
            //判断如果星级大于等于4,说明是好评,并加一  
          if ($y['star'] >= 4)
            $good++;
            //判断如果星级等于3,说明是中评并加一
          else if ($y['star'] == 3)
            $middle++;
            //除此之外就是差评,并记录
          else
            $cha++;
        }
        //计算出来好评分数,用好评的数量除以总的记录数,最后再乘以100,并且小数只保留一位
        $good = round($good / $count * 100, 1);
        //同样计算中评数
        $middle = round($middle / $count * 100, 1);
        //同样计算差评数
        $cha = round($cha / $count * 100, 1);
//统一赋给data
        $data['good'] = $good;
        $data['middle'] = $middle;
        $data['cha'] = $cha;
//ajax返回数据
        echo json_encode($data);
      }

//ajax获取当前商品的会员价
    /**
     * 
     * @param type $goods_id
     */
    public function ajaxGetMemberPrice($goods_id) {
      $member_price = D('goods');
//获取当前商品的会员价格时,先判断下会员是否登录了,如果会员已经登录了在进行连表查询  
      if (session('username')) {
        $price_info = $member_price->get_member_price($goods_id);
        echo '￥' . $price_info;
//否则,直接将当前商品的shop_price价格返回即可,不显示打折信息  
      } else {
        $price = $member_price->field('shop_price')->where('id=' . $goods_id)->find();
        echo '￥' . $price['shop_price'];
      }
    }

    
    public function search(){
           //将数据传递到页面
      $this->assign(array(
        'page_title' => '搜索页',
        'css' => array('list'),
        'js' => array('list'),
        'page_keywords' => '',
        'page_description' => '',
        
        )
      );
    //实例化商品表,调用商品表中的search方法  
      $goods=D("goods");
      // $categorys=$goods->getBrands('54');
      $data=$goods->search();
    //最后,将search方法返回的商品信息和分页信息输出到页面中  
      $this->assign(array(
        'data'=>$data['result'],//商品信息
        'page'=>$data['page'],//分页字符串
        'brands'=>$data['brands'],
        'price'=>$data['price'],
        'attr'=>$data['attr']
        ));
      $this->display();
    }



  }
