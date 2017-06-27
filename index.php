<?php

include_once('init.php');

define('CRLF', "\r\n");
define('CFG_PATH_CHATLOG', 'log.txt');
define('CFG_PATH_ERRORLOG', 'error.txt');
define('STR_ERR_UPDATE', 'Received Bad Update!');
define('STR_ERR_DATABASE', '*cough, cough* I\'m not feeling well... :( @andeswolf [%s]');
define('STR_ERR_METHODNAME', 'Method name must be a string'.CRLF);
define('STR_ERR_PARAMETERS', 'Parameters must be an array'.CRLF);
define('STR_MSG_RECORD', 'The all time record is %s.');
define('STR_MSG_VIOLATIONS', '@%s holds their head low, as they have %d violations.');
define('STR_MSG_SHEPPFOX', '(@Sheppfox is a cheater and has %s shames.)');
define('STR_MSG_LUGGAGE', 'It has been: %s since the last violation.');
define('STR_MSG_BLAME', 'You can blame @%s for the last violation.');
define('STR_MSG_HIGHSIERRA','This is the luggage @Fursuiting recommends: '.HIGH_SIERRA);
define('STR_MSG_WHO', '@%s is %s.');
define('STR_MSG_DONTKNOW','I don\'t know; Who is %s?');
define('STR_MSG_DEFAULTWHAT','mysterious');
define('STR_MSG_WHAT','@%s is %s.');
define('STR_MSG_SETWHO','Ok. @%s is %s.');
define('STR_MSG_AP1','Hey!');
define('STR_MSG_AP2','We don\'t talk about action packers around these parts.');
define('STR_MSG_HS1','I love High Sierras!');
define('STR_MSG_HS2','Uh, excuse me, it\'s \'Sierra\'.');
define('STR_MSG_PC','Ugh.');
define('STR_MSG_DEFAULT','I\'m sorry, I don\'t know what you just asked me :(');
define('STR_MSG_CUB','@KovaWolf is a cub.');
define('SQL_GET_LUGGAGE_RECORD', 'SELECT `id`, `date` FROM `'.DB_SCHEMA.'`.`luggage_counter` ORDER BY `date`;');
define('SQL_GET_LUGGAGE_VIOLATIONS', 'SELECT `username`, COUNT(username) as `count` FROM `'.DB_SCHEMA.'`.`luggage_counter` WHERE `username` IS NOT NULL GROUP BY `username` ORDER BY `count` DESC LIMIT 2;');
define('SQL_GET_LUGGAGE_LAST', 'SELECT `date` FROM `'.DB_SCHEMA.'`.`luggage_counter` ORDER BY `date` DESC LIMIT 1;');
define('SQL_GET_BLAME', 'SELECT `username` FROM `'.DB_SCHEMA.'`.`luggage_counter` ORDER BY `id` DESC LIMIT 1;');
define('SQL_GET_WHO', 'SELECT `key` FROM `'.DB_SCHEMA.'`.`lacy_bot` WHERE `setting` = \'who\' and `value` = \'%s\' LIMIT 1;');
define('SQL_GET_WHAT', 'SELECT * FROM `'.DB_SCHEMA.'`.`lacy_bot` WHERE `setting` = \'who\' and `key` = \'%s\' GROUP BY value;');
define('SQL_GET_SETWHO','SELECT * FROM `'.DB_SCHEMA.'`.`lacy_bot` WHERE `key` IS NULL AND `setting` = \'%s\' LIMIT 1;');
define('SQL_INS_LUGGAGE', 'INSERT INTO `'.DB_SCHEMA.'`.`luggage_counter` SET `date` = \'%s\', `message_id` = %d, `user_id` = %d, `username` = \'%s\';');
define('SQL_INS_WHO', 'INSERT INTO `'.DB_SCHEMA.'`.`lacy_bot` SET `setting` = \'%s\', `key` = NULL, `value` = \'%s\';');
define('SQL_INS_SETWHO', 'UPDATE `'.DB_SCHEMA.'`.`lacy_bot` SET `setting` = \'who\', `key` = \'%s\' WHERE `id` = %d;');
define('REG_LUGGAGE', '/\b(luggage|tote|bag)\b.*\?$/i');
define('REG_CUB', '/^who.*\bcub\b.*\?$/i');
define('REG_WHO', '/(who.*)\\s.*(\\ba\\b|\\ban\\b|\\bgot\\b|\\bhas\\b|\\bthe\\b)\\s(.*)(\\?$)/i');
define('REG_WHAT', '/^who\s+is\s+@([^\b]+)\?$/i');
define('REG_LASTCOMMA', '/,\s([^,]+)$/');
define('REG_USER', '/^@([^\b]+)/i');
define('REG_AP','/action\s?packers?/i');
define('REG_HS1','/high\s+sierra/i');
define('REG_HS2','/high\s+seirra/i');
define('REG_PC','/pelican/i');
define('REG_BOT','/@lacys_bot/i');

// Create our database connection object
$db = new database();
$conn = $db->connect();

$content = file_get_contents("php://input");
$update = json_decode($content, true);
if (isset($update["message"])) {
	$message_id = $update['message']['message_id'];
	$chat_id = $update['message']['chat']['id'];
	$message_username = $update['message']['from']['username'];
	$message_user_id = $update['message']['from']['id'];
	$message_date = $update['message']['date'];
	$message_string = $update['message']['text'];
//	chatLog($content);
	processMessage();
} else {
	exit;	
}

// Received wrong update, must not happen
if (!$update) {
	chatLog("err".print_r($content, true),CFG_PATH_ERRORLOG);
	error_log(STR_ERR_UPDATE);
	exit;
}

// write a message to a file on the server
// default: log.txt
function chatLog($message, $file = CFG_PATH_CHATLOG)
{
	$f = fopen($file, 'a');
	fwrite($f, $message.CRLF);
	fclose($f);
	return true;
}

// Report an error to the chat
function sendErrorMessage($error_id)
{
	global $chat_id;
	global $message_id;
	apiRequest("sendMessage",
		array(
			'chat_id' => $chat_id,
			'text' => sprintf(STR_ERR_DATABASE, $error_id),
			'reply_to_message_id' => $message_id
		)
	);
	return true;
}

function sendReply($message)
{
	global $chat_id;
	global $message_id;
	apiRequest("sendMessage",
		array(
			'chat_id' => $chat_id,
			'text' => $message,
			'reply_to_message_id' => $message_id
		)
	);
	return true;
}

// Construct a string containing a human readable span of time
function constructTimeSpan($diff)
{
	$format = "";
	if ($diff->i > 0) $format = "%i minutes, and ".$format;
	if ($diff->h > 0) $format = "%h hours, ".$format;
	if ($diff->d > 0) $format = "%d days, ".$format;
	if ($diff->m > 0) $format = "%m months, ".$format;
	if ($diff->y > 0) $format = "%y years, ".$format;
	return $diff->format($format."%s seconds");
}

// Gets the record for when "Luggage" was last triggered
function getRecord()
{
	global $conn;
	$result = $conn->query(SQL_GET_LUGGAGE_RECORD);
	if ($conn->errno) {
		sendErrorMessage('e07');
		throw new exception($conn->error);
	}
	$lastoccurance = false;
	$longestspan = 0;
	while ($row = $result->fetch_assoc()) {
		$thisoccurance = date_create_from_format('Y-m-d H:i:s',$row['date']);
		if (!$lastoccurance) {
			$lastoccurance = $thisoccurance;
			continue;
		}
		$span = $lastoccurance->getTimeStamp() - $thisoccurance->getTimeStamp();
		if ($span > $longestspan) {
			$longestspan = $span;
			$diff = $thisoccurance->diff($lastoccurance);
		}
	}
	$record = constructTimeSpan($diff);
	return sprintf(STR_MSG_RECORD,$record);
}

// Gets the person who triggered "Luggage?" the most
function getShame()
{
	global $conn;
	$result = $conn->query(SQL_GET_LUGGAGE_VIOLATIONS);
	if ($conn->errno) {
		sendErrorMessage('e01');
		throw new exception($conn->error);
	}
	$violators = array();
	while ($row = $result->fetch_assoc()) {
		$violators[] = $row;
	}
	// hard-coded @sheppfox into the response,
	// because he learned you could private-message the bot
	// and artificially increase the violation count
	if (strtolower($violators[0]['username']) == 'sheppfox'){
		$violator_string = sprintf(STR_MSG_VIOLATIONS,$violators[1]['username'],$violators[1]['count']);
		$violator_string .= CRLF.sprintf(STR_MSG_SHEPPFOX,$violators[0]['count']);
	} else {
		$violator_string = sprintf(STR_MSG_VIOLATIONS,$violators[0]['username'],$violators[0]['count']);	
	}
	return $violator_string;
}

// Gets how long its last been since someone triggered "luggage?"
function getLuggage()
{
    global $conn;
	$result = $conn->query(SQL_GET_LUGGAGE_LAST);
	if ($conn->errno){
		sendErrorMessage('e01');
		throw new exception($conn->error);
	}
	$row = $result->fetch_row();
	$date1 = new DateTime($row[0]);
	$date2 = new DateTime(gmdate('Y-m-d H:i:s', time()));
	$diff = date_diff($date1, $date2);
	$counter = constructTimeSpan($diff);
	return sprintf(STR_MSG_LUGGAGE,$counter);
}

// Get the last person to trigger "luggage?"?
function getBlame()
{
	global $conn;
	$result = $conn->query(SQL_GET_BLAME);
	if ($conn->errno) {
		sendErrorMessage('e04');
		throw new exception($conn->error);
	}
	$row = $result->fetch_row();
	return sprintf(STR_MSG_BLAME,$row[0]);
}

function setLuggage()
{
	global $conn;
	global $message_id;
	global $message_date;
	global $message_user_id;
	global $message_username;
	$time = gmdate('Y-m-d H:i:s', $message_date);
	$query = sprintf(
		SQL_INS_LUGGAGE,
		$time,
		$message_id,
		$message_user_id,
		$conn->real_escape_string($message_username)
	);
	$conn->query($query);
	if ($conn->errno) {
		sendErrorMessage('e08');
		throw new exception($conn->error);
	}
	return true;
}

function askWho($descriptor)
{
	global $conn;
	global $message_username;
	$result = $conn->query(sprintf(SQL_GET_WHO,$conn->real_escape_string($descriptor)));
	chatLog(sprintf(SQL_GET_WHO,$conn->real_escape_string($descriptor)));
	if ($conn->errno) {
		sendErrorMessage('e04');
		throw new exception($conn->error);
	}
	if ($result->num_rows == 1) {
		$row = $result->fetch_row();
		return $row[0];
	}
	$query = sprintf(
		SQL_INS_WHO,
		$conn->escape_string($message_username),
		$conn->escape_string($descriptor)
	);
	chatLog(print_r($query,true));
	$result = $conn->query($query);
	if($conn->errno){
		sendErrorMessage('e09');
		chatLog(print_r($conn->error, true));
		throw new exception($conn->error);
	}
	return false;
}

function getWhat($who)
{
	global $conn;
	$query = sprintf(SQL_GET_WHAT,$conn->real_escape_string($who));
	$result = $conn->query($query);
	if ($conn->errno) {
		sendErrorMessage('e06');
		throw new exception($conn->error);
	}
	$what_string = '';
	$what_count = 0;
	while ($data = $result->fetch_array()) {
		$what_string .= $data['value'] . ', ';
		$what_count++;
	}
	$what_string = (strlen($what_string) > 0)
		? substr($what_string, 0, -2)
		: STR_MSG_DEFAULTWHAT;
	$oxford = ($what_count == 2) ? '' : ',';
	$what_string = preg_replace(REG_LASTCOMMA,$oxford.' and ${1}',$what_string);
	return $what_string;
}

function setWho($who)
{
	global $conn;
	global $message_username;
	global $message_id;
	global $chat_id;

	$query = sprintf(SQL_GET_SETWHO,$conn->real_escape_string($message_username));
	chatLog($query);
	$result = $conn->query($query);
	if($conn->errno){
		sendErrorMessage('e10');
		throw new exception($conn->error);
	}
	//nothing in the "who's a queue for this user
	if($result->num_rows != 1){
	    exit;
	}
	$row = $result->fetch_assoc();
	$query = sprintf(SQL_INS_SETWHO,$conn->real_escape_string($who),$row['id']);
	$result = $conn->query($query);
	if($conn->errno){
		sendErrorMessage('e05');
		throw new exception($conn->error);
	}
	$what_string = sprintf(STR_MSG_SETWHO,$who,$row['value']);
	return $what_string;
}

/**
 * This is where the magic happens.
 * Lacybot uses the Telegram Webhook API (https://core.telegram.org/bots/api#getting-updates)
 * Text from the chat gets pushed here, and we look for key strings to respond to.
 */
function getResponse()
{
	global $chat_id;
	global $message_string;

	// "Record" is the longest we've gone since triggering "luggage?"
	// unfortunately the record is currently atrifically long, because there
	// was a period where the bot was broken :(
	if (strpos($message_string, '/record') === 0) {
		$record_string = getRecord();
		sendReply($record_string);
		return true;
	}

	// "Shame" looks for the person who triggered "Luggage?" the most
	if (strpos($message_string, '/shame') === 0) {
		$violator_string = getShame();
		sendReply($violator_string);
		return true;
	}

    	// Checks to see how long its last been since someone triggered "luggage?"
	if (strpos($message_string, '/luggage') === 0) {
		$counter_string = getLuggage();
		sendReply($counter_string);
		return true;
	}

    	// Who was the last person to trigger "luggage?"?
	if (strpos($message_string, '/blame') === 0) {
		$blame_string = getBlame();
		sendReply($blame_string);
		return true;
	}

	// Look for Luggage questions
	if (preg_match(REG_LUGGAGE, $message_string, $matches)) {
		setLuggage();
		sendReply(STR_MSG_HIGHSIERRA);
		return true;
	}

	// HARD CODED -- do not remove, do not change!
	// who's a cub -> kova is
	if (preg_match(REG_CUB, $message_string)) {
		sendReply(STR_MSG_CUB);
		return true;
	}

	// who's a ... title?
	if (preg_match(REG_WHO, $message_string, $matches)) {
		$descriptor = trim($matches[2]." ".$matches[3]);
		$who = askWho(str_replace('@', '', $descriptor));
		if ($who !== false) {
			sendReply(sprintf(STR_MSG_WHO,$who,$descriptor));
			return true;
		}
		sendReply(sprintf(STR_MSG_DONTKNOW,$descriptor));
		return true;
	}

	// "who is @name?"
	if (preg_match(REG_WHAT, $message_string, $matches)) {
		$who = trim($matches[1]);
		$what_string = getWhat($who);
		sendReply(sprintf(STR_MSG_WHAT,$who,$what_string));
		return true;
	}

	// Sets title to name
	if (preg_match(REG_USER, $message_string, $matches)) {
		$who = trim($matches[1]);
		$what_string = setWho($who);
		sendReply($what_string);
	}

    	//Looks for the phrase "action packer" and mis-spellings
	if (preg_match(REG_AP, $message_string)) {
		sendReply(STR_MSG_AP1);
        	// FUN FACT:
        	//you can create artificial "typing" delays!
		apiRequest('sendChatAction', array('chat_id' => $chat_id, 'action' => 'typing'));
		sleep(3);
		sendReply(STR_MSG_AP2);
		return true;
	}
	
    	//looks for high sierra mentions
	if (preg_match(REG_HS1, $message_string)) {;
		sendReply(STR_MSG_HS1);
		return true;
	}

    	//looks for a mis-spelling, but I don't think
    	// this has ever been triggered
	if (preg_match(REG_HS2, $message_string)) {;
		sendReply(STR_MSG_HS2);
		return true;
	}

    	//looks for anyone talking about a pelican
    	//Here is an example of unpredicability:
    	// you could be having a conversation about birds,
    	// and accidentally trigger the bot!
	if (preg_match(REG_PC, $message_string)) {;
		sendReply(STR_MSG_PC);
		return true;
	}

    	//if you directly ask the bot a question,
    	//she will respond, but does nothing.
    	//could be some potential here
	if (preg_match(REG_BOT, $message_string)) {;
		sendReply(STR_MSG_DEFAULT);
		return true;
	}
}

function apiRequestWebhook($method, $parameters)
{
	if (!is_string($method)) {
		error_log(STR_ERR_METHODNAME);
		sendErrorMessage("e22");
		return false;
	}
	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log(STR_ERR_PARAMETERS);
		sendErrorMessage("e23");
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
		error_log(STR_ERR_METHODNAME);
		return false;
	}
	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log(STR_ERR_PARAMETERS);
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
		error_log(STR_ERR_METHODNAME);
		return false;
	}
	if (!$parameters) {
		$parameters = array();
	} else if (!is_array($parameters)) {
		error_log(STR_ERR_PARAMETERS);
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

function processMessage() {
	// process incoming message
	// Currently lacybot ignores anything that's not text
	getResponse();
	return true;
}
