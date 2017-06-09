<?php

include_once('init.php');

$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
	// receive wrong update, must not happen
	chatLog("err".print_r($content, true), 'error.txt');
	error_log("Received Bad Update!");
	exit;
}

if (isset($update["message"])) {
//	chatLog($content);
	processMessage($update["message"]);
}

function chatLog($message, $file = 'log.txt')
{
	$f = fopen($file, 'a');
	fwrite($f, $message."\n\r");
	fclose($f);
}

function secondsToTime($seconds) {
	//Warning: Does not account for leap years
	$years = floor($seconds / 31622400);
	$seconds -= ($years * 31622400);

	$days = floor($seconds / 86400);
	$seconds -= ($days * 86400);

	$hours = floor($seconds / 3600);
	$seconds -= ($hours * 3600);

	$minutes = floor($seconds / 60);
	$seconds -= ($minutes * 60);

	$values = array(
		'year'   => $years,
		'day'    => $days,
		'hour'   => $hours,
		'minute' => $minutes,
		'second' => $seconds
	);

	$parts = array();

	foreach ($values as $text => $value) {
		if ($value > 0) {
			$parts[] = $value . ' ' . $text . ($value > 1 ? 's' : '');
		}
	}

	return implode(' ', $parts);
}

// Updates the record for when "Luggage" was last triggered
function updateRecords($chat_id, $message_id){
	$db = new database();
	$conn = $db->connect();
	$query = "SELECT `id`, `date` FROM `".DB_SCHEMA."`.`luggage_counter` ORDER BY id;";
	$result = $conn->query($query);
	if($conn->errno){
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "*cough, cough* I'm not feeling well... :( @andeswolf [e02]",
				"reply_to_message_id" => $message_id
			)
		);
		throw new exception($conn->error);
	}
	$i = 0;
	while($row = $result->fetch_assoc()){
		$dates[$i] = $row;
		$i++;
	}

	$i = 0;
	foreach($dates as $date){

		if(!isset($dates[$i+1])){
			continue;
		}

		$date1 = new DateTime($date['date']);

		$date2 = new DateTime((isset($dates[$i+1]['date']) ? $dates[$i+1]['date'] : gmdate('Y-m-d H:i:s', time())));

		$diff = date_diff($date1, $date2);
		$format = "";
		if($diff->i > 0){
			$format = "%i minutes, and ".$format;
		}
		if($diff->h > 0){
			$format = "%h hours, ".$format;
		}
		if($diff->d > 0){
			$format = "%d days, ".$format;
		}
		if($diff->m > 0){
			$format = "%m months, ".$format;
		}
		if($diff->y > 0){
			$format = "%y years, ".$format;
		}
		$counter = $diff->format($format."%s seconds");
		$query = "SELECT timestampdiff(SECOND, '".$date['date']."','".$dates[$i+1]['date']."')";
		$result = $conn->query($query);
		$record = $result->fetch_row();
		$conn->query("UPDATE `".DB_SCHEMA."`.`luggage_counter` SET `record`='".$record[0]."' WHERE `id`='".$dates[$i+1]['id']."';");
		if($conn->errno){
			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "*cough, cough* I'm not feeling well... :( @andeswolf [e03]",
					"reply_to_message_id" => $message_id
				)
			);
			throw new exception($conn->error);
		}
		$i++;
	}


}


/**
 * This is where the magic happens.
 * Lacybot uses the Telegram Webhook API (https://core.telegram.org/bots/api#getting-updates)
 * Text from the chat gets pushed here, and we look for key strings to respond to.
 */
function getResponse($message){
	$message_id = $message['message_id'];
	$chat_id = $message['chat']['id'];
	$str = $message['text'];


    // "Record" is the longest we've gone since triggering "luggage?"
    // unfortunately the record is currently atrifically long, because there
    // was a period where the bot was broken :(
	if(strpos($str, "/record") === 0){
		$db = new database();
		$conn = $db->connect();
		$query = "SELECT `record` FROM `".DB_SCHEMA."`.`luggage_counter` WHERE username IS NOT NULL ORDER BY record DESC LIMIT 1";
		$result = $conn->query($query);
		$row = $result->fetch_row();
		$record = secondsToTime($row[0]);
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "The all time record is ".$record,
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}

    // "Shame" looks for the person who triggered "Luggage?" the most
	if(strpos($str, "/shame") === 0){
		$db = new database();
		$conn = $db->connect();
		$query = "SELECT username, COUNT(username) as `count` FROM ".DB_SCHEMA.".luggage_counter WHERE username IS NOT NULL GROUP BY username ORDER BY `count` DESC LIMIT 2;";
		$result = $conn->query($query);
		while($row = $result->fetch_assoc()){
			$data[] = $row;
		}
        // hard-coded @sheppfox into the response,
        // because he learned you could private-message the bot
        // and artificially increase the violation count
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "@".$data[1]['username']." holds their head low, as they have ".$data[1]['count']." violations.\n\r(@Sheppfox is a cheater and has ".$data[0]['count']." shames.)",
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}

    //Checks to see how long its last been since
    //someone triggered "luggage?"
	if (strpos($str, "/luggage") === 0) {
		apiRequest('sendChatAction',
			array(
				'chat_id' => $chat_id,
				'action' => 'typing'
			)
		);
		$db = new database();
		$conn = $db->connect();
		$query = "SELECT `date` FROM `".DB_SCHEMA."`.`luggage_counter` ORDER BY id DESC LIMIT 1;";
		$result = $conn->query($query);

		if($conn->errno){
			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "*cough, cough* I'm not feeling well... :( @andeswolf [e01]",
					"reply_to_message_id" => $message_id
				)
			);
			throw new exception($conn->error);
		}

		$row = $result->fetch_row();
		$date1 = new DateTime($row[0]);
		$date2 = new DateTime(gmdate('Y-m-d H:i:s', time()));
		$diff = date_diff($date1, $date2);
		$format = "";
		if($diff->i > 0){
			$format = "%i minutes, and ".$format;
		}
		if($diff->h > 0){
			$format = "%h hours, ".$format;
		}
		if($diff->d > 0){
			$format = "%d days, ".$format;
		}
		if($diff->m > 0){
			$format = "%m months, ".$format;
		}
		if($diff->y > 0){
			$format = "%y years, ".$format;
		}
		$counter = $diff->format($format."%s seconds");

		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "It has been: ".$counter." since the last violation.",
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}

    // Who was the last person to trigger "luggage?"?
	if(strpos($str, "/blame") === 0){
		$db = new database();
		$conn = $db->connect();
		$query = "SELECT `username` FROM `".DB_SCHEMA."`.`luggage_counter` ORDER BY id DESC LIMIT 1;";
		$result = $conn->query($query);

		if($conn->errno){
			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "*cough, cough* I'm not feeling well... :( @andeswolf [e04]",
					"reply_to_message_id" => $message_id
				)
			);
			throw new exception($conn->error);
		}
		$row = $result->fetch_row();

		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "You can blame @".$row[0]." for the last violation.",
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}

	// Look for Luggage questions
	$re = "/\\b(luggage|tote|bag)\\b.*(\\?)$/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])&&($matches[2])){

		$time = gmdate('Y-m-d H:i:s', $message['date']);


			$db = new database();
			$conn = $db->connect();
			$query = "INSERT INTO `".DB_SCHEMA."`.`luggage_counter` (`date`, `message_id`, `user_id`, `username`) VALUES ('".$time."', '".$message_id."', '".$message['from']['id']."', '".$message['from']['username']."');";
			$conn->query($query);

			if($conn->errno){
				throw new exception($conn->error);
			}

		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "This is the ".$matches[1]." @Fursuiting recommends: ".HIGH_SIERRA,
				"reply_to_message_id" => $message_id
			)
		);


		updateRecords($chat_id, $message_id);

		return;
	}

    //HARD CODED
    //do not remove, do not change!
	//who's a cub -> kova is
	$re = "/(who).*(\\bcub\\b).*(\\?$)/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])&&($matches[2])&&($matches[3])){
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "@KovaWolf is a cub.",
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}

	//who's a ... title?
	$re = "/(who.*)\\s.*(\\ba\\b|\\ban\\b|\\bgot\\b|\\bhas\\b|\\bthe\\b)\\s(.*)(\\?$)/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])&&($matches[2])&&($matches[4])){
		$db = new database();
		$conn = $db->connect();

		$query = "SELECT * FROM `".DB_SCHEMA."`.`lacy_bot` WHERE setting = 'who' and `value` = '".$conn->escape_string($matches[2]." ".$matches[3])."'";
		$result = $conn->query($query);

		if($result->num_rows >= 1){
			$row = $result->fetch_row();
			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "@".$row[2]." is ".stripslashes($row[3]).".",
					"reply_to_message_id" => $message_id
				)
			);
			return;
		}

		$query = "INSERT INTO `".DB_SCHEMA."`.`lacy_bot` (`setting`, `key`, `value`) VALUES ('".$conn->escape_string($message['from']['username'])."', '".$conn->escape_string($matches[2]." ".$matches[3])."', NULL);";
		$result = $conn->query($query);
		if($conn->errno){
			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "*cough, cough* I'm not feeling well... :( @andeswolf [e02]",
					"reply_to_message_id" => $message_id
				)
			);
			throw new exception($conn->error);
		}

		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "I don't know; ".$matches[1]." ".$matches[2]." ".$matches[3]."?",
				"reply_to_message_id" => $message_id
			)
		);
	}


	//"who is @name?"
	$re = "/(who)('s|s|\\sis?)\\s*@(.*)\\?$/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])&&($matches[2])&&($matches[3])){
		$db = new database();
		$conn = $db->connect();

		$query = "SELECT * FROM `".DB_SCHEMA."`.`lacy_bot` WHERE setting = 'who' and `key` = '".$conn->escape_string($matches[3])."' GROUP BY value;";
		$result = $conn->query($query);

		if($conn->errno){
			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "*cough, cough* I'm not feeling well... :( @andeswolf [e06]",
					"reply_to_message_id" => $message_id
				)
			);
			throw new exception($conn->error);
		}

		if($result->num_rows >= 1){
			$row = array();
			while($data = $result->fetch_array()){
				$row[] = $data['value'];
			}

			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "@".$matches[3]." is a ".stripslashes(implode(', a ', $row)).".",
					"reply_to_message_id" => $message_id
				)
			);
			return;
		}

	}

	//sets title to name
	$re = "/^\\@(.+?)(\\s|$).*/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])){
		$db = new database();
		$conn = $db->connect();
		$query = "SELECT * FROM ".DB_SCHEMA.".lacy_bot WHERE `value` IS NULL AND setting = '".$message['from']['username']."' LIMIT 1";
		$result = $conn->query($query);
		if($conn->errno){
			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "*cough, cough* I'm not feeling well... :( @andeswolf [e02]",
					"reply_to_message_id" => $message_id
				)
			);
			throw new exception($conn->error);
		}

		if($result->num_rows == 1){
			$row = $result->fetch_row();
			if($row[1] != $message['from']['username']){
				apiRequest("sendMessage",
					array(
						'chat_id' => $chat_id,
						"text" => "Sorry, I was expecting an answer from @".$row[1].".",
						"reply_to_message_id" => $message_id
					)
				);
				return;
			}

			$query = "UPDATE `".DB_SCHEMA."`.`lacy_bot` SET `setting`='who', `key`='".$conn->escape_string($matches[1])."', `value`='".$conn->escape_string($row[2])."' WHERE `id`='".$row[0]."';";
			$result = $conn->query($query);
			if($conn->errno){
				apiRequest("sendMessage",
					array(
						'chat_id' => $chat_id,
						"text" => "*cough, cough* I'm not feeling well... :( @andeswolf [e05]",
						"reply_to_message_id" => $message_id
					)
				);
				throw new exception($conn->error);
			}

			apiRequest("sendMessage",
				array(
					'chat_id' => $chat_id,
					"text" => "Ok. @".$matches[1]." is ".stripslashes($row[2])."",
					"reply_to_message_id" => $message_id
				)
			);

		}

	}

    //Looks for the phrase "action packer" and mis-spellings
	$re = "/(action packer|actionpacker|action packers|actionpackers)/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])){
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "Hey!",
				"reply_to_message_id" => $message_id
			)
		);
        // FUN FACT:
        //you can create artificial "typing" delays!
		apiRequest('sendChatAction',
			array(
				'chat_id' => $chat_id,
				'action' => 'typing'
			)
		);
		sleep(3);
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "We don't talk about ".$matches[1]." around these parts.",
			)
		);
		return;

	}
    //looks for high sierra mentions
	$re = "/(high sierra)/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])){
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "I love High Sierra's!",
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}

    //looks for a mis-spelling, but I don't think
    // this has ever been triggered
	$re = "/(seirra)/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])){
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "Uh, excuse me, it's 'Sierra'.",
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}

    //looks for anyone talking about a pelican
    //Here is an example of unpredicability:
    // you could be having a conversation about birds,
    // and accidentally trigger the bot!
	$re = "/(pelican)/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])){
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "Ugh.",
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}

    //if you directly ask the bot a question,
    //she will respond, but does nothing.
    //could be some potential here
	$re = "/.*(@lacys_bot).*(\\?$)/i";
	preg_match($re, $str, $matches);
	if(isset($matches[1])){
		apiRequest("sendMessage",
			array(
				'chat_id' => $chat_id,
				"text" => "I'm sorry, I don't know what you just asked me :(",
				"reply_to_message_id" => $message_id
			)
		);
		return;
	}
//	else {
//		return;
//		apiRequest("sendMessage",
//			array(
//				'chat_id' => $chat_id,
//				"text" => "I don't understand."
//			)
//		);
//	}

}

function apiRequestWebhook($method, $parameters)
{
	if (!is_string($method)) {
		error_log("Method name must be a string\n");
		return false;
	}

	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log("Parameters must be an array\n");
		return false;
	}

	$parameters["method"] = $method;

	header("Content-Type: application/json");
	echo json_encode($parameters);
	return true;
}

function exec_curl_request($handle)
{
	$response = curl_exec($handle);

	if ($response === false) {
		$errno = curl_errno($handle);
		$error = curl_error($handle);
		error_log("Curl returned error $errno: $error\n");
		curl_close($handle);
		return false;
	}

	$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
	curl_close($handle);

	if ($http_code >= 500) {
		// do not wat to DDOS server if something goes wrong
		sleep(10);
		return false;
	} else if ($http_code != 200) {
		$response = json_decode($response, true);
		error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
		if ($http_code == 401) {
			throw new Exception('Invalid access token provided');
		}
		return false;
	} else {
		$response = json_decode($response, true);
		if (isset($response['description'])) {
			error_log("Request was successful: {$response['description']}\n");
		}
		$response = $response['result'];
	}

	return $response;
}

function apiRequest($method, $parameters)
{
	if (!is_string($method)) {
		error_log("Method name must be a string\n");
		return false;
	}

	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log("Parameters must be an array\n");
		return false;
	}

	foreach ($parameters as $key => &$val) {
		// encoding to JSON array parameters, for example reply_markup
		if (!is_numeric($val) && !is_string($val)) {
			$val = json_encode($val);
		}
	}
	$url = API_URL . $method . '?' . http_build_query($parameters);

	$handle = curl_init($url);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);

	return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters)
{
	if (!is_string($method)) {
		error_log("Method name must be a string\n");
		return false;
	}

	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log("Parameters must be an array\n");
		return false;
	}

	$parameters["method"] = $method;

	$handle = curl_init(API_URL);
	curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($handle, CURLOPT_TIMEOUT, 60);
	curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
	curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

	return exec_curl_request($handle);
}

function processMessage($message)
{
	// process incoming message
	$message_id = $message['message_id'];
	$chat_id = $message['chat']['id'];

	if (isset($message['text'])) {
		// incoming text message
		getResponse($message);

	}
	else {
		// Type of incoming message was not a text message
		// Currently lacybot ignores anything that's not text
	}
}
