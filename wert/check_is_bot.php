<?php 



	
	
	$remote_cloaker = 'http://rutyer.ru/inc/mods/cloaka/remote.php'; // адрес Remote Cloaker Script
	$key = '12345'; // укажите кей, с которым разрешено удаленно подключаться к модулю
	
	error_reporting(0);
	
	$is_bot = is_bot($remote_cloaker, $key);
	
	function is_bot($remote_cloaker, $key) {
		if(!function_exists('getUserIP')){
			// определение ip адреса
			function getUserIP() {
				$array = array('HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR', 'HTTP_X_REMOTECLIENT_IP');
				foreach($array as $key)
					if(filter_var($_SERVER[$key], FILTER_VALIDATE_IP)) return $_SERVER[$key];
				return false;
			}
		}
		
		$userIP = getUserIP(); // IP юзера
		//$userIP = '66.249.79.191'; // IP бота для теста
		$uagent = getenv('HTTP_USER_AGENT'); // юзер агент

		// отправляем запрос и принимаем ответ
		$response = @file_get_contents($remote_cloaker.'?data='.base64_encode($_SERVER["HTTP_HOST"]."||$userIP||$uagent||$key"));
		
		if ($response) {
			$resp = json_decode($response, 1);
			
			// если бан или неверный кей, то 404
			if ($resp['is'] == 'banned_bot' || !empty($resp['error'])){
				header('HTTP/1.1 404 Not Found');
				header("Status: 404 Not Found");
				die('404 Not Found');
			}
			
			// если используется JS Cloaker Script, то установим cookie для юзера
			if (!empty($resp['js']) and $resp['is'] == 'user') setcookie('c', 1);
			
			if ($resp['is'] == 'user') unset($resp['is']);
			
			// результат
			return $resp['is'];
		}
		
		return true;
	}
	
	
?>