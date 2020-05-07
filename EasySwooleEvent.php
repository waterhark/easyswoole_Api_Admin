<?php

    namespace EasySwoole\EasySwoole;


    use EasySwoole\EasySwoole\Swoole\EventRegister;
    use EasySwoole\EasySwoole\AbstractInterface\Event;
    use EasySwoole\Http\Message\Status;
    use EasySwoole\Http\Request;
    use EasySwoole\Http\Response;
    use EasySwoole\Component\Di;
    use EasySwoole\ORM\Db\Connection;
    use EasySwoole\ORM\DbManager;
    use EasySwoole\ORM\Db\Config as OrmConfig;

    class EasySwooleEvent implements Event
    {

        public static function initialize()
        {
            // TODO: Implement initialize() method.
            date_default_timezone_set('Asia/Shanghai');
            $config = new OrmConfig(Config::getInstance()->getConf('MYSQL'));
            DbManager::getInstance()->addConnection(new Connection($config));

//        允许 URL 最大解析至7层
//            Di::getInstance()->set(SysConst::HTTP_CONTROLLER_MAX_DEPTH, 7);
        }

        public static function mainServerCreate(EventRegister $register)
        {
            // TODO: Implement mainServerCreate() method.
        }

        public static function onRequest(Request $request, Response $response): bool
        {
            // TODO: Implement onRequest() method.
            $url = Config::getInstance()->getConf('FRONT_END_URL');
            $response->withHeader('Access-Control-Allow-Origin', $url);
            $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->withHeader('Access-Control-Allow-Credentials', 'true');
            $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            if ($request->getMethod() === 'OPTIONS') {
                $response->withStatus(Status::CODE_OK);
                return false;
            }
            return true;
        }

        public static function afterRequest(Request $request, Response $response): void
        {
            // TODO: Implement afterAction() method.
        }
    }