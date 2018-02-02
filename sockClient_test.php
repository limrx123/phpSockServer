<?php

require_once('./BigEndianBuffer.php');

class sockClient extends BigEndianBuffer{

	private $debug = true;

	private $connect_host = '192.168.3.211';

	private $connect_port = 1258;

	private $socket = null;


	public function __construct(){
		//创建通讯节点
		if(false === ($this->socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP))){
			myerror('socket_create() failed: '.socket_strerror(socket_last_error($ssock)));
			//die('socket_create() failed: '.socket_strerror(socket_last_error($ssock)));
		}

		//设置通讯节点的套接字选项
		if(false === (socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec'=>10,'usec'=>0)))){
			$this->error('Failed to set socket option: '.socket_strerror(socket_last_error($this->socket)));
		}
		if(false === (socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec'=>10,'usec'=>0)))){
			$this->error('Failed to set socket option: '.socket_strerror(socket_last_error($this->socket)));
		}
		//设置通讯节点套接字为非阻塞模式
		socket_set_block($this->socket);

		//客户端连接socket server
		if(false === @socket_connect($this->socket, $this->connect_host,$this->connect_port)){
			myerror('Failed to connect sockserver. '.socket_strerror(socket_last_error($this->socket)));
		}
	}

	public function __destruct(){
		@socket_close($this->socket);
	}


	public function run($cb_request,$cb_response){
		call_user_func($cb_request,$this->socket,$this);
		call_user_func($cb_response,$this->socket,$this);
	}


	//从套接字读取指定长度的字节串
	public function readBytes($len){
		$csock = $this->socket;
		$received = 0;
		$buf = '';
		if(1 > intval($len))
			return false;
		if(0 >= ($received = @socket_recv($this->socket,$buf,$len,0))){
			return false;
		}
		//$this->mylog('Received: '.$received.'|'.'total_len: '.$len);
		if($received == $len)
			return $buf;
		//一次读取只读了部分（可能默认缓冲已满，但总长度并未读完）重新初始化变量
		//$this->mylog('Receive data for looping.');
		$tstr = $buf;
		//$this->mylog('Received first data: '.strlen($buf));
		$buf = '';
		$len = $len-$received;
		while($len > 0){
			if(false === ($received = @socket_recv($this->socket,$buf,$len,0))){
				return false;
			}
			$len = $len-$received;
			$tstr .= $buf;
		}
		return $tstr;
	}

	public function writeBytes($bytes){
		$total_len = $len = strlen($bytes);
		$writen = 0;
		$writen = @socket_write($this->socket, $bytes,$len);
		//$this->mylog('Write first data to server: '.(int)$writen);
		if($writen <= 0){
			return false;
		}
		if($writen == $len){
			return $total_len;
		}
		//$this->mylog('Writing data for looping.');
		$len = $len - $writen;
		$writen = 0;
		while ($len > 0) {
			if(0 >= ($writen = @socket_write($this->socket, $bytes,$len))){
				return false;
			}
			$len = $len - $writen;
		}
		/*if(0 >= @socket_write($this->csock, $bytes,$len)){
				return false;
			}*/
		return $total_len;
	}

	public function myerror($errmsg){
		$this->log($errmsg);
	}

	public function mylog($errmsg){
		if($this->debug){
			echo '['.date('Ymd-H:i:s').']'.$errmsg.PHP_EOL;
		}else{
			//写入日志文件

		}
	}




}

//业务请求
function doRequest($socket,$clientObj){
	//用于测试
	$name = 'akhdkasghasldhaklhdasksaghkjdgasjkgdsajsakdhasgjk';

	$name = str_repeat($name, 10);

	//发送数据
	$len = strlen($name);
	$clientObj->mylog('Total_bytes: '.$len);

	$writen = @socket_send($socket, $name,$len,MSG_EOF);

	$clientObj->mylog('Send data finished: '.$writen);

}

function doResponse($socket,$clientObj){
	//接受响应数据
	$len = 60000;
	$read = @socket_read($socket, $len);
	$len = $len - strlen($read);
	$str = $read;
	$read = '';
	while($len > 0){
		$read = @socket_read($socket, $len);
		if($read === false){
			break;
		}
		$len = $len - strlen($read);
		$str .= $read;
		$read = 0;
	}
	//$read = @socket_read($ssock, $len);
	$clientObj->mylog('Read respone data: '.strlen($str));
}

function doRequest2($socket,$clientObj){
	//用于测试
	$name = '张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴张小娴';

	$name = str_repeat($name, 10);

	//发送数据
	$len = strlen($name);
	$clientObj->mylog('Total_bytes: '.$len);

	$writen = $clientObj->writeBytes($name);

	$clientObj->mylog('Send data finished: '.$writen);

}

function doResponse2($socket,$clientObj){
	//接受响应数据
	$len = 6000;
	$read = $clientObj->readBytes($len);
	//$read = @socket_read($ssock, $len);
	$clientObj->mylog('Read respone data: '.strlen($read));
}

//=============测试2===================

function doRequest3($socket,$clientObj){
	//用于测试
	//包头部分
	$header_hash_len = 0;  //占1个字节 ： 固定 32 40 字节长
	$header_hash_val = '';  //占32 |40字节 md5 sha1
	$header_content_unit = 1; //占1个字节 1 表示字节为单位
	$header_content_len = 0;  //占2个字节 max: 65536
	$body_content_json = '';  //占max 65536 unit字节长

	$data = array(
		'username' => '张小娴',
		'account' => 'zhangxiaoxian',
		'password' => 'zhangxiaoxian',
		'model' => 'Account',
		'action' => 'DoLogin',
		'extra' => '其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息其他的相关信息'
	);

	$body_content_json = json_encode($data);
	$header_content_len = strlen($body_content_json);
	$header_hash_val = md5($body_content_json);
	$header_hash_len = strlen($header_hash_val);

	//发送数据
	$clientObj->mylog('Staring request data.');

	$clientObj->writeChar($header_hash_len);
	$clientObj->writeString($header_hash_val);
	$clientObj->writeChar($header_content_unit);
	$clientObj->writeShort($header_content_len);
	$clientObj->writeString($body_content_json);

	$clientObj->mylog('Send data finished: ');

}

function doResponse3($socket,$clientObj){
	//接受响应数据
	$clientObj->mylog('Starting receive data.');
	$header_hash_len = $clientObj->readChar();
	$header_hash_val = $clientObj->readString($header_hash_len);
	$header_content_unit = $clientObj->readChar();
	$header_content_len = $clientObj->readShort();
	$body_content_json = $clientObj->readString($header_content_len);
	$clientObj->mylog('receive data finished.');

	$clientObj->mylog('Response data: '.var_export(json_decode($body_content_json,true)));

}

$server_client = new sockClient();

$server_client->run('doRequest','doResponse');






