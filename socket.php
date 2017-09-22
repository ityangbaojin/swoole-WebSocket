<?php
class Socket {
    private $server;
    public function __construct() {
        if (php_sapi_name() !== 'cli') {
            exit('使用cli模式');
        }
        // 创建 websocket服务器
        $this->server = new swoole_websocket_server("0.0.0.0", 9502);
        // 设置配置
        $this->server->set([
            'daemonize' => false,      // 是否是守护进程
            'max_request' => 10000,    // 最大连接数量
            'dispatch_mode' => 2,
            'debug_mode'=> 1,
            'heartbeat_check_interval' => 5, // 心跳检测的设置，自动踢掉掉线的fd
            'heartbeat_idle_time' => 600
        ]);
        // 监听WebSocket连接打开
        $this->server->on('open', function (swoole_websocket_server $server, $request) {
            echo "new user {$request->fd} join \n";
            // 设置用户ID
            $GLOBALS['fd'][$request->fd]['id']   = $request->fd;
            // 设置用户名
            $GLOBALS['fd'][$request->fd]['name'] = '匿名用户';
        });
        // 监听WebSocket消息事件
        $this->server->on('message', function(swoole_websocket_server $server, $request) {
            // 信息
            $message = $GLOBALS['fd'][$request->fd]['name'] . ': ' . htmlspecialchars($request->data) . "\n";
            if (strstr($request->data, '#name#')) {
                // 用户设置昵称
                $GLOBALS['fd'][$request->fd]['name'] = str_replace('#name#', '', htmlspecialchars($request->data));
                // 群发推送每个客户端
                foreach ($this->server->connections as $fd) {
                    $this->server->push($fd, "热烈欢迎 {$GLOBALS['fd'][$request->fd]['name']} 加入聊天!!!");
                }
            } else {
                // 发送用户信息
                // 发送每一个客户端
                // 遍历所有websocket连接用户的fd，给所有用户推送
                foreach ($this->server->connections as $fd) {
                    $this->server->push($fd, $message);
                }
                /*foreach ($GLOBALS['fd'] as $v) {
                    $this->server->push($v['id'], $message);
                }*/
            }
        });
        // 监听WebSocket连接关闭事件
        $this->server->on('close', function(swoole_websocket_server $server, $request) {
            echo "客户端 {$request} 断开连接\n";
            // 删除已断开的客户端
            unset($GLOBALS['fd'][$request]);
        });

        $this->server->start();
    }
}
new Socket();