<?php

//执行逻辑
error_reporting(E_ALL ^ E_NOTICE);
set_time_limit(0);
//ini_set('display_errors',0);
ob_implicit_flush();

//测试1
function clientHandler($msgsock,$servObj){
	socket_set_block($msgsock);
	$len = 90000;
	$servObj->log('Starting receive data.');
	$read_buffer = $servObj->readBytes($len);
	$servObj->log('TOTAL DATA: '.strlen($read_buffer));

	$servObj->log('Starting write data to socket.');
	if($read_buffer === false){
		$servObj->writeBytes('Socket server failed to receive data.');
	}else{
		$servObj->writeBytes($read_buffer);
	}
	$servObj->log('Write data finished.');
}

//测试2
function clientHandler2($msgsock,$servObj){
	socket_set_block($msgsock);

	$header_hash_len = 0;  //占1个字节 ： 固定 32 40 字节长
	$header_hash_val = '';  //占32 |40字节 md5 sha1
	$header_content_unit = 1; //占1个字节 1 表示1字节为单位 2 2字节为单位 n n字节为单位
	$header_content_len = 0;  //占2个字节 max: 65536
	$body_content_json = '';  //占max 65536 unit字节长

	$servObj->log('Starting receive data.');
	$header_hash_len = $servObj->readChar();
	$header_hash_val = $servObj->readString($header_hash_len);
	$header_content_unit = $servObj->readChar();
	$header_content_len = $servObj->readShort();
	$body_content_json = $servObj->readString($header_content_len);

	$data = json_decode($body_content_json,true);

	$servObj->log('JSON DATA:'.var_export($data));

	if($header_hash_val == md5($body_content_json)){
		//响应数据
		$servObj->log('Staring response data.');

		$servObj->writeChar($header_hash_len);
		$servObj->writeString($header_hash_val);
		$servObj->writeChar($header_hash_unit);
		$servObj->writeShort($header_content_len);
		$servObj->writeString($body_content_json);
	}else{
		$body_content_json = 'Socket server failed to receive data.';
		$header_content_len = strlen($body_content_json);

		//响应数据
		$servObj->log('Staring response data.');

		$servObj->writeChar($header_hash_len);
		$servObj->writeString($header_hash_val);
		$servObj->writeChar($header_hash_unit);
		$servObj->writeShort($header_content_len);
		$servObj->writeString($body_content_json);
	}


	$servObj->log('Write data finished.');
}

$socketServer = new sockServer('clientHandler2','192.168.3.211',1258);

$socketServer->run('daemon');
