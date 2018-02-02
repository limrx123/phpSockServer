<?php
/*
	php socket server
*/
require_once('./BigEndianBuffer.php');

class sockServer extends BigEndianBuffer {
	private $ssock = null;
	private $csock = null;
	private $listenHost = "0.0.0.0";
	private $listenPort = "1258";
	private $callback = '';
	private $debug = true;
	private $isListen = true;
	private $procUser = 'daemon';
	private $procUid;
	private $procGid;
	private $procHome;
	private $pidFile = '';

	public function __construct($callback, $host='', $port=null){
		declare(ticks = 1);
		$this->callback = $callback?$callback:'';
		if($host)
			$this->listenHost = $host;
		if($port)
			$this->listenPort = $port;
		//安装信号处理器
		$this->installSignalHandler();
	}

	public function __destruct(){
		@socket_close($this->ssock);
		@socket_close($this->csock);
		$pid = posix_getpid();
		$this->log('Socket close in parent pid: '.$pid);
		$this->log('Ending from parent pid: '.$pid);
	}

	//以什么用户身份运行socket server
	public function run($user = null){
		//进程守护化
		$this->daemon($user);
		//初始化套接字
		$this->log('socket_create() create socket.');
		//创建通讯节点
		if(false === ($this->ssock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))){
			$this->error('socket_create() failed: '.socket_strerror(socket_last_error($this->ssock)));
			die('socket_create() failed: '.socket_strerror(socket_last_error($this->ssock)));
		}
		//设置通讯节点的套接字选项
		if(false === (socket_set_option($this->ssock, SOL_SOCKET, SO_SNDTIMEO, array('sec'=>10,'usec'=>0)))){
			$this->error('Failed to set socket option: '.socket_strerror(socket_last_error($this->ssock)));
		}
		if(false === (socket_set_option($this->ssock, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>10,'usec'=>0)))){
			$this->error('Failed to set socket option: '.socket_strerror(socket_last_error($this->ssock)));
		}
		//绑定ipaddr:port
		if(false === (socket_bind($this->ssock, $this->listenHost,$this->listenPort))){
			$this->error('socket_bind() failed: '.socket_strerror(socket_last_error($this->ssock)));
		}
		//监听套接字
		if(false === socket_listen($this->ssock,0)){
			$this->error('socket_listen() failed: '.socket_strerror(socket_last_error($this->ssock)));
		}
		//设置通讯节点套接字为非阻塞模式
		socket_set_nonblock($this->ssock);

		$this->log('Waiting for clients to connect.');

		while($this->isListen){
			$this->csock = @socket_accept($this->ssock);
			if(false === $this->csock){
				$error_msg = 'socket_accept() no connection: '.socket_strerror(socket_last_error($this->ssock)).PHP_EOL;
				$this->error($error_msg);
			}
			while(false === $this->csock){
				$this->csock = @socket_accept($this->ssock);
				usleep(1000); //1ms
			}
			//fork子进程处理逻辑
			$this->client();
		}

	}
	//fork子进程处理逻辑
	public function client(){
		$ssock = $this->ssock;
		$csock = $this->csock;
		$this->log('Starting fork to process client.');
		$pid = pcntl_fork();
		if(-1 == $pid){
			$this->error('pcntl_fork() failed: '.pcntl_get_last_error());
		}else if(0 < $pid){
			//父进程
			$this->log('Parent close csock: '.$csock);
			socket_close($csock);
			unset($csock);
		}else{
			//子进程(0 == $pid)
			$this->isListen = false;
			$this->log('Sub processr work for client.');
			//处理client 请求
			call_user_func($this->callback,$csock,$this);

			$this->log('Sub process close csock: '.$csock);

			socket_close($csock);
			unset($csock);

			$this->log('Post SIGTERM signal to terminate subprocess.');

			posix_kill(posix_getpid(),SIGTERM);
		}
	}
	//子进程守护化
	private function daemon($user){
		if($user)
			$this->procUser = $user;
		$this->getUserInfo();
		//父进程ppid
		$ppid = posix_getpid();
		//子进程守护化
		$this->log('Starting from parent pid: '.$ppid);
		//创建pidfile文件
		$this->log('Parent pid('.$ppid.')创建pidfile.');
		$this->createPidFile();
		//切换root daemon
		$this->changeProcIdentity();
		//fork
		$pid = pcntl_fork();
		if($pid == -1){
			$this->error('pcntl_fork() sub process failed: '.pcntl_strerror(pcntl_get_last_error()));
		}else if($pid){
			//父进程 退出
			$this->log('Ending from parent pid: '.$ppid);
			exit();
		}else{
			//子进程 COW
			$this->log('Promote sub process to daemon.');
			posix_setsid();
			chdir($this->procHome);
			umask(0);
			$pid = posix_getpid();
			$this->log('Starting from sub pid: '.$pid);
			$this->setPidFile($pid);
		}

	}

	private function createPidFile(){
		//创建pidfile文件
		$piddir = '/var/run/phpsocket/';
		if(!file_exists($piddir)){
			mkdir($piddir,0775,true);
		}
		$pidfile = $piddir.'/php.pid';
		if(!file_exists($pidfile)){
			fopen($pidfile,'w+');
		}
		//改变pidfile身份
		chown($piddir,$this->procUid);
		chgrp($piddir,$this->procGid);
		chown($pidfile,$this->procUid);
		chgrp($pidfile,$this->procGid);
		$this->pidFile = $pidfile;
	}
	//设置pidfile
	private function setPidFile($pid){
		if($pid){
			file_put_contents($this->pidFile, $pid);
		}
	}
	//获取运行用户的信息
	private function getUserInfo(){
		$userInfo = posix_getpwnam($this->procUser);
		$this->procUid = $userInfo['uid'];
		$this->procGid = $userInfo['gid'];
		$this->procHome = $userInfo['dir'];
	}
	//切换运行用户身份
	private function changeProcIdentity(){
		if(!posix_setuid($this->procUid))
			$this->error('Failed to change uid to '.$this->procUid);
		/*if(!posix_setgid($this->procGid))	//所数组保留root
			$this->error('Failed to change gid to '.$this->procGid);*/
	}
	//保存错误信息
	public function error($errmsg){
		$this->log($errmsg);
	}
	//详细记录日志信息
	public function log($errmsg){
		if($this->debug){
			echo '['.date('Ymd-H:i:s').']'.$errmsg.PHP_EOL;
		}else{
			//写入日志文件

		}
	}
	//安装信号处理器
	private function installSignalHandler(){
		pcntl_signal(SIGINT, array($this,'sigHandler'));
		pcntl_signal(SIGCHLD, array($this,'sigHandler'));
		//pcntl_signal(SIGTERM, array($this,'sigHandler'));
	}
	//自定义信号处理器
	private function sigHandler($sig){
		switch ($sig) {
			//case SIGTERM:
			case SIGINT:
				exit();
				break;
			case SIGCHLD:
				pcntl_waitpid(-1, $status);
			default:
				break;
		}
	}
	//从套接字读取指定长度的字节串
	public function readBytes($len){
		$csock = $this->csock;
		$received = 0;
		$buf = '';
		if(1 > intval($len))
			return false;
		if(0 >= ($received = @socket_recv($csock,$buf,$len,0))){
			return false;
		}
		//$this->log('Received: '.$received.'|'.'total_len: '.$len);
		if($received == $len)
			return $buf;
		//一次读取只读了部分
		//$this->log('Receive data for looping.');
		$tstr = $buf;
		//$this->log('Received first data: '.strlen($buf));
		$buf = '';
		$len = $len-$received;
		while($len > 0){
			if(false === ($received = @socket_recv($csock,$buf,$len,0))){
				return false;
			}
			$len = $len-$received;
			$tstr .= $buf;
		}
		return $tstr;
	}
	//向套接字写数据
	public function writeBytes($bytes){
		$len = strlen($bytes);
		$writen = 0;
		$writen = @socket_write($this->csock, $bytes,$len);
		//$this->log('Write first data to client: '.(int)$writen);
		if($writen <= 0){
			return false;
		}
		if($writen == $len){
			return true;
		}
		//$this->log('Writing data for looping.');
		$len = $len - $writen;
		$writen = 0;
		while ($len > 0) {
			if(0 >= ($writen = @socket_write($this->csock, $bytes,$len))){
				return false;
			}
			$len = $len - $writen;
		}
		/*if(0 >= @socket_write($this->csock, $bytes,$len)){
				return false;
			}*/
		return true;
	}


}
