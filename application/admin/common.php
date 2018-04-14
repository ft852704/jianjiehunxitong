<?php
function is_power($action, $power){
	if(in_array(strtolower($action), $power)){
		return true;
	}else{
		return false;
	}
}
// 获取当前用户的Token
function get_token($token = NULL) {
	$stoken = session ( 'token' );
	
	$reset = false;
	if ($token !== NULL && $token != '-1') {
		session ( 'token', $token );
		$reset = true;
	} elseif (! empty ( $_REQUEST ['token'] ) && $_REQUEST ['token'] != '-1') {
		session ( 'token', $_REQUEST ['token'] );
		$reset = true;
	} elseif (! empty ( $_REQUEST ['publicid'] )) {
		$publicid = I ( 'publicid' );
		$token = D ( 'Common/Public' )->getInfo ( $publicid, 'token' );
		$token && session ( 'token', $token );
		$reset = true;
	}
	$token = session ( 'token' );
	if (! empty ( $token ) && $token != '-1' && $stoken != $token && $GLOBALS ['is_wap']) {
		session ( 'mid', null );
	}
	$GLOBALS['is_wap'] = true;
	//加校验，防止使用无权限的公众号
	if(!$GLOBALS['is_wap'] && $reset){
		if(empty($GLOBALS['myinfo'])) $token = -1;
		else{
			$sql = 'SELECT public_id FROM `'.C('DB_PREFIX').'public_link` as l LEFT JOIN '.C('DB_PREFIX').'public as p on l.mp_id=p.id WHERE l.uid='.$GLOBALS['mid'];
			$list = M()->query($sql);
			$flat = false;
			foreach ($list as $value) {
				if($value['public_id']==$token){
					$flat = true;
				}
			}

			if(!$flat) $token = -1;
		}
	}
	
	if (empty ( $token ) ) {
		$token = -1;
	}
	
	return $token;
}
// 通过openid获取微信用户基本信息,此功能只有认证的服务号才能用
function getWeixinUserInfo($openid) {
	if (! C ( 'USER_BASE_INFO' )) {
		return array ();
	}
	$access_token = get_access_token ();
	if (empty ( $access_token )) {
		return array ();
	}
	
	$param2 ['access_token'] = $access_token;
	$param2 ['openid'] = $openid;
	$param2 ['lang'] = 'zh_CN';
	
	$url = 'https://api.weixin.qq.com/cgi-bin/user/info?' . http_build_query ( $param2 );
	$content = get_data ( $url );
	$content = json_decode ( $content, true );
	return $content;
}
// 获取access_token，自动带缓存功能
function get_access_token($token = '', $update = false) {
	empty ( $token ) && $token = get_token ();
	
	$info = get_token_appinfo ( $token );
	
	// 微信开放平台一键绑定
	if ($token == 'gh_3c884a361561' || $info ['is_bind']) {
		$access_token = get_authorizer_access_token ( $info ['appid'], $info ['authorizer_refresh_token'], $update );
	} else {
		$access_token = get_access_token_by_apppid ( $info ['appid'], $info ['secret'], $update );
	}
	
	// 自动判断access_token是否已失效，如失效自动获取新的
	if ($update == false) {
		$url = 'https://api.weixin.qq.com/cgi-bin/getcallbackip?access_token=' . $access_token;
		$res = wp_file_get_contents ( $url );
		$res = json_decode ( $res, true );
		if ($res ['errcode'] == '40001') {
			$access_token = get_access_token ( $token, true );
		}
	}
	
	return $access_token;
}
//获取openid
function GetOpenid()
	{
		//通过code获得openid
		if (!isset($_GET['code'])){
			//触发微信返回code码
			$http = isset ( $_SERVER ['HTTPS'] ) && $_SERVER ['HTTPS'] == 'on' ? 'https://' : 'http://';
			$baseUrl = urlencode($http.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].$_SERVER['QUERY_STRING']);
			$url = $this->__CreateOauthUrlForCode($baseUrl);
			Header("Location: $url");
			exit();
		} else {
			//获取code码，以获取openid
		    $code = $_GET['code'];
			$openid = $this->getOpenidFromMp($code);
			return $openid;
		}
	}
//获取openid 子方法
 function __CreateOauthUrlForCode($redirectUrl)
	{
		$urlObj["appid"] = 'wxbc51b257e7c1b8b3';
		$urlObj["redirect_uri"] = "$redirectUrl";
		$urlObj["response_type"] = "code";
		$urlObj["scope"] = "snsapi_base";
		$urlObj["state"] = "STATE"."#wechat_redirect";
		$bizString = $this->ToUrlParams($urlObj);
		return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
	}
//将产生转换为字符串
	function ToUrlParams($urlObj)
	{
		$buff = "";
		foreach ($urlObj as $k => $v)
		{
			if($k != "sign"){
				$buff .= $k . "=" . $v . "&";
			}
		}
		
		$buff = trim($buff, "&");
		return $buff;
	}
?>