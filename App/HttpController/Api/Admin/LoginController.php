<?php


    namespace App\HttpController\Api\Admin;


    use App\HttpController\Api\Lang\Dictionary;
    use App\HttpController\Model\AdminMenuModel;
    use App\HttpController\Model\AdminUserModel;
    use EasySwoole\EasySwoole\Config;
    use EasySwoole\EasySwoole\ServerManager;
    use EasySwoole\Http\Message\Status;
    use EasySwoole\HttpAnnotation\AnnotationTag\DocTag\Api;
    use EasySwoole\HttpAnnotation\AnnotationTag\DocTag\ApiFail;
    use EasySwoole\HttpAnnotation\AnnotationTag\DocTag\ApiRequestExample;
    use EasySwoole\HttpAnnotation\AnnotationTag\DocTag\ApiSuccess;
    use EasySwoole\HttpAnnotation\AnnotationTag\Method;
    use EasySwoole\HttpAnnotation\AnnotationTag\Param;
    use EasySwoole\I18N\I18N;
    use mysql_xdevapi\Exception;
    use EasySwoole\Jwt\Jwt;

    /**
     * Class LoginController
     * @package App\HttpController\Api\Admin
     */
    class LoginController extends ApiBase
    {
        /**
         * @Api(name="login",group="Login",path="/login",description="后台登录接口")
         * @Method(allow={POST})
         * @Param(name="adminName",required="")
         * @Param(name="password",required="")
         * @ApiRequestExample(https://sjs.ngrok.shuimengzhi.com/login)
         * @ApiSuccess({
        "code": 200,
        "result": null,
        "msg": "login success"
        })
         * @ApiFail({
        "code": 401,
        "result": null,
        "msg": "login fail"
        })
         * @throws \EasySwoole\Mysqli\Exception\Exception
         * @throws \EasySwoole\ORM\Exception\Exception
         * @throws \Throwable
         */
        public function login()
        {
            $data = $this->request()->getRequestParam();
            $userModel = AdminUserModel::create();
            //            获取用户信息
            $res = $userModel->where('admin_name', $data['adminName'])->where('password',
                md5($data['password']))->get();
            //            登录失败执行
            if ($res === null) {
                $this->writeJson(Status::CODE_UNAUTHORIZED, null, 'login fail');
                $this->response()->end();
                return false;
            }

            //            Token生成
            $jwtObject = Jwt::getInstance()
                ->setSecretKey('easyswoole') // 秘钥
                ->publish();

            $jwtObject->setAlg('HMACSHA256'); // 加密方式
            $jwtObject->setAud($res->admin_name); // 用户
            $jwtObject->setExp(time() + 3600); // 过期时间
            $jwtObject->setIat(time()); // 发布时间
            $jwtObject->setIss('easyswoole-Admin'); // 发行人
            $jwtObject->setJti(md5(time())); // jwt id 用于标识该jwt
            $jwtObject->setNbf(time() + 60 * 5); // 在此之前不可用
            $jwtObject->setSub('Admin Login'); // 主题

            // 自定义数据
            $jwtObject->setData([
                'adminId' => $res->admin_id,
                'adminName' => $res->admin_name,
            ]);

            // 最终生成的token
            $token = $jwtObject->__toString();
            $domain = Config::getInstance()->getConf('FRONT_END_DOMAIN');
            $this->response()->setCookie('token', $token, time() + 3600, '/', $domain, false, true);
            //            ip部署到服务器的时候再验证一下
            $ipInfo = ServerManager::getInstance()->getSwooleServer()->connection_info($this->request()->getSwooleRequest()->fd);
            $ip = $ipInfo['remote_ip'];
            $lastTime = $ipInfo['last_time'];
            //            更新登录者的IP和登录时间
            $userModel->update(
                ['last_time' => $lastTime, 'last_ip' => $ip, 'token' => $token],
                ['admin_name' => $data['adminName']]
            );
            $this->writeJson(Status::CODE_OK, null, 'login success');

        }

//前端初始化需要的内容
        public function init()
        {
            $request = $this->request();
            $token = $request->getCookieParams('token');


            $homeInfo = [
                'title' => '首页',
                //'href' => 'page/welcome-1.html?t=1',
                'href' => 'view/user/admin_user_list.html',
            ];
            $logoInfo = [
                'title' => 'LAYUI MINI',
                'image' => 'images/logo.png',
            ];
            $menuInfo = $this->getMenuList($token);
            $systemInit = [
                'homeInfo' => $homeInfo,
                'logoInfo' => $logoInfo,
                'menuInfo' => $menuInfo,
            ];
            $this->writeJson(Status::CODE_OK, ['information' => $systemInit], 'init success');
            return true;
        }

        public function test()
        {
            $a = 'HELLO';
            $ret = I18N::getInstance()->translate($a);
            var_dump($ret);//你好
        }

        //获取菜单列表
        private function getMenuList($token): ?array
        {
            $userModel = new AdminUserModel();
            $res = $userModel->where('token', $token)->get();
            $userMenu = $res->menu_list;
            $userMenu = array_map('intval', explode(',', $userMenu));
//            $userMenu = explode(',', $userMenu);
            $model = new AdminMenuModel();
            $res = $model->where('menu_id', $userMenu, 'IN')->all();

            foreach ($res as $key => $value) {
                $data[$key] = [
                    'menuId' => $value['menu_id'],
                    'parentId' => $value['parent_id'],
                    'title' => I18N::getInstance()->translate($value['menu_code']),
                    'icon' => $value['icon'],
                    'href' => $value['href'],
                    'target' => $value['target']
                ];
                $menuArray[] = $data[$key];
            }
            $menuList = $this->buildMenuChild(0, $menuArray);

            return $menuList;
        }

        //递归获取子菜单
        private function buildMenuChild(int $parentId, array $menuList): ?array
        {
            $treeList = [];
            foreach ($menuList as $value) {
                if ($parentId == $value['parentId']) {
                    $node = $value;
                    $child = $this->buildMenuChild($value['menuId'], $menuList);
                    if (!empty($child)) {
                        $node['child'] = $child;
                    }
                    // todo 后续此处加上用户的权限判断
                    $treeList[] = $node;
                }
            }
            return $treeList;
        }
    }