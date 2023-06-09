<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
require realpath(dirname(__FILE__)) . '/vendor/autoload.php';
require realpath(dirname(__FILE__)) . '/connect.php';
include realpath(dirname(__FILE__)) . '/texts.php';
global $text;
//в канал только описание, кнопка к тексту привязана. По окончанию не пост а правка конкурса. Запланированный старт

$GLOBALS['token'] = '5419071590:AAEfKghJxBKz0L1_RldbnATDNrrLo_MzHgQ';
$GLOBALS['botId'] = '5419071590';
$GLOBALS['imageReply'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
	[
		[
			['callback_data' => 'btn_image', "text" => $text['btnTextYes']],
			['callback_data' => 'btn_clean', "text" => $text['btnTextNo']]
		]
	],
	false,
	true
);

$GLOBALS['mainMenu']= new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
	[
		[
			['callback_data' => 'btn_lotterynew', "text" => $text['createLottery']]
		],
		[
			['callback_data' => 'btn_lotterycreated', "text" => $text['createdLottery']],
			['callback_data' => 'btn_lotterystarted', "text" => $text['startedLottery']]
		]
	],
	false,
	true
);

function addLog($user, $text=null) {
	$fp = fopen('errors.txt','a');
	fwrite($fp, date("Y-m-d H:i:s").' '.$user.' '.$text.PHP_EOL);
	fclose($fp);
};

function my_curl($url) {
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_HEADER, false);
	return curl_exec($curl);
	curl_close($curl);
};

function setUdata($chatId, $data = array()) {
    global $mysqli;
    $data = json_encode($data, JSON_UNESCAPED_UNICODE);
	$data = mysqli_real_escape_string($mysqli, $data);
    $query = "UPDATE lottery_users SET mode='".$data."' WHERE userId = '".$chatId."'";
    $result = $mysqli->query($query);
};

function getUdata($chatId) {
    global $mysqli;
    $res = array();
    $query = "SELECT * FROM lottery_users WHERE userId = '".$chatId."'";
    $result = $mysqli->query($query);
    $arr = mysqli_fetch_assoc($result);
    if(isset($arr['mode'])) {
		$res = json_decode($arr['mode'], true);
    }
    return $res;
};

function clearUdata($chatId) {
    global $mysqli;
    $query = "UPDATE lottery_users SET mode='' WHERE userId = '".$chatId."'";
    $result = $mysqli->query($query);
};

function checkUser($userId) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	$query = "SELECT * FROM lottery_users WHERE userId='".$userId."' LIMIT 1";
	$result = $mysqli->query($query);
	$row = $result->num_rows;
	if ($row==0) {
		return false;
	} else {
		return true;
	}
};

function addUser($userId, $userName) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	$nameTest = '/^[A-Za-z0-9_-]+$/i';
	$userName = (preg_match($nameTest, $userName)) ? $userName : null ;
	$query ="INSERT INTO lottery_users (`userId`,`userName`,`userType`,`userRegister`) VALUES ('".$userId."', '".$userName."', '0', '".date("Y-m-d H:i:s")."')";
	addLog($userId, $query);
	$result = $mysqli->query($query);
	if ($result) {
		addLog($userId, 'added');
		return true;
	} else {
		return false;
	}
};

function checkAuthorize($userId) {
	$userId = (is_numeric($userId)) ? $userId : null;
	global $mysqli;
	$query = "SELECT type FROM lottery_users WHERE userId='".$userId."'";
	$result = $mysqli->query($query);
	$data = $result->fetch_assoc();
	if ($data['type'] < 1) {
		return false;
	} else {
		addLog($userId, 'authorized');
		return true;
	}
};

function authorizeUser($userId, $pass) {
	global $mysqli;
	$userId = (is_numeric($userId)) ? $userId : null;
	if ($pass == $GLOBALS['pass']) {
		$query = "UPDATE lottery_users SET `type`='1' WHERE userId='".$userId."'";
		$result = $mysqli->query($query);
		if (!$result) {
			return false;
		} else {
			return true;
		}
	}
};

function sendLotteryView($userId, $bot) {
	if (checkUser($userId)) {
		addLog($userId, 'checked');
		global $mysqli;
		global $text;
		$data = getUdata($userId);
		$reply = $text['title'].$data['title'].'
'.$text['description'].$data['description'].'
'.$text['dateFinal'].$data['date'].'
'.$text['winnersCount'].$data['countWinners'];
		$lotteryId = writeLotteryToBase($userId);
		addLog($userId, 'loto: '.$lotteryId);
		if ($lotteryId > 0) {
			$userChannels = array();
			$userChannels = [
								[
									['callback_data' => 'btn_addchannel-'.$lotteryId, "text" => $text['addChannels'].$text['toLottery'].$lotteryId],
									['callback_data' => 'btn_lotterydel-'.$lotteryId, "text" => $text['delLottery'].$lotteryId]
								]
							];
			$query = "SELECT * FROM lottery_channels WHERE userId='".$userId."'";
			$result = $mysqli->query($query);
			foreach ($result as $key=>$value) {
				$userChannels[] = [['callback_data' => 'btn_connectchannel-'.$value['id'].'!'.$lotteryId, "text" => $value['chanName']]];
			}

			$GLOBALS['lotterymanage'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
				$userChannels,
				false,
				true
			);
			if (isset($data['file'])) {
				addLog($userId, 'file: '.$data['file']);
				addLog($userId, 'reply: '.$reply);
				$media = [];
				array_push($media, ['type' => 'photo', 'media' => $data['file']]);
				$document = new \CURLFile($data['file']);
				$bot->sendPhoto($userId, $document, $reply);
			} else {
				$bot->sendMessage($userId, $reply);
			}
			$bot->sendMessage($userId, $text['continueLottery'], null, false, false, $GLOBALS['lotterymanage']);
			clearUdata($userId);
		} else {
			$bot->sendMessage($userId, $text['createLotteryError']);
		}
	}
};

function writeLotteryToBase($userId) {
	if (checkUser($userId)) {
		addLog('write check');
		global $mysqli;
		$data = getUdata($userId);
		$query = "INSERT INTO lottery_list (`title`, `description`, `image`, `dateStart`, `dateStop`, `countWinners`, `finished`, `author`)
VALUES ('".$data['title']."', '".$data['description']."', '".$data['file']."', '".date("Y-m-d H:i:s",strtotime($data['datestart']))."', '".date("Y-m-d H:i:s",strtotime($data['dateend']))."', '".$data['countWinners']."', '0', '".$userId."')";
addLog($query);
		$result = $mysqli->query($query);
		addLog(serialize($result));
		$id = $mysqli->insert_id;
		addLog($userId, 'new lottery id: '.$id);
		if ($id > 0) {
			return $id;
		} else {
			return false;
		}
	}
};

function createNewLottery($userId, $bot) {
	if (checkUser($userId)) {
		global $text;
		clearUdata($userId);
		$bot->sendMessage($userId, $text['newQuest']);
		$data = getUdata($userId);
		if (!isset($data['step1'])) {
			addLog($userId, 'new lottery');
			$uData = array('step1' => 'started');
			setUdata($userId, $uData);
			$bot->sendMessage($userId, $text['newLottery']);
		}
	} else {
		if (addUser($userId, $userName)) {
			$bot->sendMessage($userId, $text['hello']);
		} else {
			$bot->sendMessage($userId, $text['textAddError']);
		}
	}
};

function channelConnect($userId, $lotteryId, $bot) {
	if (checkUser($userId)) {
		global $text;
		global $mysqli;
		addLog($userId, 'connecting channels to lottery #'.$lotteryId);
		clearUdata($userId);
		$uData = array('chan1' => 'connecting', 'lotteryId' => $lotteryId);
		setUdata($userId, $uData);
		$bot->sendMessage($userId, $text['connectToChannel']);
	}
};

function checkForward($userId, $message, $bot) {
	if (checkUser($userId)) {
		addLog($userId, 'check forward message');
		$chanId = $message->getForwardFromChat()->getId();
		addLog($userId, 'chanId: '.$message->getForwardFromChat()->getId());
		if (isset($chanId)) {
			$data = getUdata($userId);
			$uData = array('chan2' => $chanId);
			$data = array_merge($uData, $data);
			setUdata($userId, $data);
			addLog($userId, 'chan2 set');
		}
	}
};

function checkIsBotChanAdmin($chanId, $bot) {
	global $mysqli;
	$isAdmin = $bot->getChatMember($chanId, $GLOBALS['botId'])->getStatus();
	addLog('bot is chan admin ', $chanId.' '.$isAdmin);
	return $isAdmin;
};

function checkIsAuthorChanAdmin($chanId, $authorId, $bot) {
	global $mysqli;
	$isAdmin = $bot->getChatMember($chanId, $authorId)->getStatus();
	return $isAdmin;
};

function sendLotteryToChannel($lotteryId, $bot) {
	global $mysqli;
	global $text;
	addLog('start sending lottery #', $lotteryId);
	$query = "SELECT * FROM lottery_list WHERE id='".$lotteryId."' AND finished='0' AND del='0' AND started='0'";
	$result = $mysqli->query($query)->fetch_assoc();
	if ($result) {
		$reply = $result['title'].'
'.$result['description'];
		$keyboard = ['inline_keyboard' =>
			[
				[
					['callback_data' => 'btn_lotteryagree-'.$lotteryId, "text" => $text['agree']]
				]
			]
		];
		$encodedKeyboard = json_encode($keyboard);
		if (($result['image']) != '') {
			addLog($result['author'], 'file: '.$result['image']);
			addLog($result['author'], 'reply: '.$reply);
			$document = new \CURLFile($result['image']);
			$mId = sendPhotoGetMessageId($result['connectedChannels'], $result['image'], $reply, $encodedKeyboard);
			$query = "UPDATE lottery_list set mId='".$mId."' WHERE id='".$lotteryId."'";
			$mysqli->query($query);
			addLog($result['author'], 'lottery sended to channel, mId: '.$mId);
		} else {
			$bot->sendMessage($result['connectedChannels'], $reply, false, null, null, $GLOBALS['startLottery']);
		}
		$query = "UPDATE lottery_list SET started='1' WHERE id='".$lotteryId."'";
		$mysqli->query($query);
		clearUdata($result['author']);
		return true;
	} else {
		return false;
	}
};

function sendPhotoGetMessageId($chanId, $image, $reply, $encodedKeyboard) {
	$bot_url = "https://api.telegram.org/bot".$GLOBALS['token']."/";
	$url = $bot_url."sendPhoto?chat_id=".$chanId;
	$post_fields = array('chat_id' => $chanId,
	'photo' => new CURLFile($image),
	'caption' => $reply,
	'reply_markup' => $encodedKeyboard);

	$ch = curl_init(); 
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		"Content-Type:multipart/form-data"
	));
	curl_setopt($ch, CURLOPT_URL, $url); 
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields); 
	$output = curl_exec($ch);
	$res = json_decode($output, true);
	curl_close($ch);
	return $res['result']['message_id'];
};

function isUserInChannel($userId, $chanId, $bot) {
	$status = $bot->getChatMember($chanId, $userId)->getStatus();
	if ($status === 'member') {
		return true;
	} else {
		return false;
	}
};

function isUserInLottery($userId, $lotteryId) {
	global $mysqli;
	$query = "SELECT * FROM lottery_members WHERE lotteryId='".$lotteryId."' AND userId='".$userId."'";
	$result = $mysqli->query($query);
	$row = $result->num_rows;
	if ($row==0) {
		return false;
	} else {
		return true;
	}
};

function addUserToLottery($userId, $name, $firstName, $lastName, $lotteryId, $bot) {
	global $mysqli;
	$query = "SELECT * FROM lottery_list WHERE id='".$lotteryId."' AND del='0'";
	addLog('query user to lottery: ', $query);
	$result = $mysqli->query($query)->fetch_assoc();
	$chanId = $result['connectedChannels'];
	if (isUserInChannel($userId, $chanId, $bot)) {
		if (isUserInLottery($userId, $lotteryId)) {
			return false;
		} else {
			$query = "INSERT INTO lottery_members (`lotteryId`, `userId`, `login`, `firstName`, `lastName`, `channel`) VALUES 
			('".$lotteryId."', '".$userId."', '".$name."', '".$firstName."', '".$lastName."', '".$chanId."')";
			addLog($userId, $query);
			$result = $mysqli->query($query);
			if ($result) {
				addLog('add to members userId: ', $userId);
				return true;
			} else {
				addLog('not add to members userId: ', $userId);
				return false;
			}
		}
	} else {
		addLog('not in channel userId: ', $userId);
		return false;
	}
	addLog('end lotteryId: ', $lotteryId);
};

function isLotteryActive($lotteryId) {
	global $mysqli;
	$query = "SELECT * FROM lottery_list WHERE id='".$lotteryId."' AND started='1' AND finished='0' AND del='0'";
	$result = $mysqli->query($query);
	$row = $result->num_rows;
	if ($row==0) {
		return false;
	} else {
		return true;
	}
};

function getWinner($lotteryId, $bot) {
	$drop = 0;
	global $mysqli;
	$query = "SELECT * FROM lottery_members WHERE lotteryId='".$lotteryId."'";
	$result = $mysqli->query($query);
	$row = $result->num_rows;
	if ($row != 0) {
		$i = -1;
		$query = "SELECT countWinners FROM lottery_list WHERE id='".$lotteryId."'";
		
		$winCount = $mysqli->query($query)->fetch_assoc()['countWinners'];
		addLog('wincount:', $winCount);
		$winnerId = 0;
		$hasWinner = false;
		$winners = array();
		addLog('Lottery: '.$lotteryId, 'has '.$row.' users');
		do {
			addLog($i, $row);
			$query = "SELECT * FROM lottery_members WHERE lotteryId='".$lotteryId."' AND login!='' ORDER BY rand() LIMIT 1";
			$result = $mysqli->query($query)->fetch_assoc();
			$status = $bot->getChatMember($result['channel'], $result['userId'])->getStatus();
			addLog($result['userId'], 'current status: '.$status);
			if ($status === 'member') {
				addLog($result['userId'], 'lottery member');
				if (!in_array($result['userId'], $winners)) {
					addLog($result['userId'], 'not in winners');
					$winners[] = $result['userId'];
					if (count($winners) == $winCount) {
						addLog('same counts');
						$hasWinner = true;
					} else {
						addLog('not same counts');
					}
				} else {
					addLog($result['userId'], 'in array winners');
				}
			} else {
				addLog($result['userId'], 'not lottery member');
			}
			$i++;
			$drop++;
			addLog('drop: '.$drop);
			if ($drop > 10) {
				die;
			}
		} while (($hasWinner === false));
		if ($hasWinner === true) {
			$query = "UPDATE lottery_list SET finished='1' WHERE id = '".$lotteryId."'";
			$mysqli->query($query);
			addLog('has winner(s):', serialize($winners));
			return $winners;
		} else {
			addLog('no winner found');
			return 0;
		}
	} else {
		return 0;
	}
};

function showStartedLotteries($userId, $bot) {
	if (checkUser($userId)) {
		global $mysqli;
		global $text;
		$query = "SELECT * FROM lottery_list WHERE author='".$userId."' AND started='1' AND finished='0' AND del='0'";
		$result = $mysqli->query($query);
		$row = $result->num_rows;
		if ($row > 0) {
			foreach ($result as $key=>$value) {
				$GLOBALS['endLottery'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
					[
						[
							['callback_data' => 'btn_endlottery-'.$value['id'], "text" => $text['stopLottery']]
						]
					],
					false,
					true
				);
				$bot->sendMessage($userId, $value['title'].' #'.$value['id'].'
	Участников: '.getMembersCount($value['id']).'
	Дата завершения: '.$value['dateStop'], false, null, null, $GLOBALS['endLottery']);
			}
		} else {
			$bot->sendMessage($userId, $text['noActiveLottery'], false, null, null, $GLOBALS['mainMenu']);
		}
	}
};

function getMembersCount($lotteryId) {
	global $mysqli;
	$row = 0;
	$query = "SELECT * FROM lottery_members WHERE lotteryId='".$lotteryId."'";
	$result = $mysqli->query($query);
	$row = $result->num_rows;
	return $row;
};

function lotteryDelete($userId, $lotteryId) {
	global $mysqli;
	$query = "UPDATE lottery_list SET del='1' WHERE id='".$lotteryId."' AND author='".$userId."'";
	$result = $mysqli->query($query);
	if ($result) {
		return true;
	} else {
		return false;
	}
};

function checkBotAndUserAccessToChannel($channelId, $userId, $bot) {
	if (checkIsBotChanAdmin($channelId, $bot) === 'administrator') {
	addLog($userId, 'bot is channel admin');
		if ((checkIsAuthorChanAdmin($channelId, $userId, $bot) === 'administrator') || (checkIsAuthorChanAdmin($channelId, $userId, $bot) === 'creator')) {
			addLog($userId, 'author is channel admin');
			return true;
		} else {
			addLog($userId, 'author not channel admin');
			return false;
		}
	} else {
		addLog($userId, 'bot not channel admin');
		return false;
	}
};


try {
	$bot = new \TelegramBot\Api\Client($GLOBALS['token']);
	
	$bot->command('testll', function ($message) use ($bot) {
		$userId = $message->getChat()->getId();
		addLog($userId, 'testll');
		sendLotteryView($userId, $bot);
	});
	
	$bot->command('menu', function ($message) use ($bot) {
		$userId = $message->getChat()->getId();
		addLog($userId, 'menu button');
		if (checkUser($userId)) {
			global $text;
			$bot->sendMessage($userId, $text['mainMenu'], false, null, null, $GLOBALS['mainMenu']);	
		} else {
			addLog($userId, 'not registered');
		}
	});
	
	$bot->command('start', function ($message) use ($bot){
		global $text;
		$userId = $message->getChat()->getId();
		$userName = $message->getChat()->getUsername();
		addLog($userId, $userName);
		if (!checkUser($userId)) {
			if (addUser($userId, $userName)) {
				$bot->sendMessage($userId, $text['hello']);
			} else {
				$bot->sendMessage($userId, $text['textAddError']);
			}
		} else {
			$bot->sendMessage($userId, $text['userInBase']);
		}
	});
	
	
	$name='';
	
	$bot->callbackQuery(function ($callbackQuery) use ($bot) {
		global $text;
		global $mysqli;
		$message = $callbackQuery->getMessage();
		$userId = $message->getChat()->getId();
		$messageId = $message->getMessageId();
		$params = $callbackQuery->getData();
		addLog('callback: '.$userId, $params);
		$data = getUdata($userId);
		if ($params === 'btn_image') {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$uData = array('image' => 'file');
			$data = array_merge($uData, $data);
			setUdata($userId, $data);
			$bot->sendMessage($userId, $text['sendPicture']);
		} elseif ($params === 'btn_clean') {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$uData = array('image' => 'nofile');
			$data = array_merge($uData, $data);
			setUdata($userId, $data);
			$bot->sendMessage($userId, $text['countWinners']);
		} elseif ($params === 'btn_lotterynew') {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			createNewLottery($userId, $bot);
		} elseif (strpos($params, 'btn_lotteryagree-') === 0) {
			addLog($callbackQuery->getFrom()->getId(), 'agree pressed');
			$lotteryId = substr($params, strpos($params, '-')+1, strlen($params));
			$name = $callbackQuery->getFrom()->getUsername();
			$firstName = $callbackQuery->getFrom()->getFirstName();
			$lastName = $callbackQuery->getFrom()->getLastName();
			addLog($callbackQuery->getFrom()->getId().' '.$name.' '.$firstName.' '.$lastName.' '.$lotteryId);
			if (addUserToLottery($callbackQuery->getFrom()->getId(), $name, $firstName, $lastName, $lotteryId, $bot)) {
				$bot->answerCallbackQuery($callbackQuery->getId(), $text['inLottery']);
			} else {
				$bot->answerCallbackQuery($callbackQuery->getId(), $text['alreadyInLottery']);
			}
		} elseif (strpos($params, 'btn_addchannel-') === 0) {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$lotteryId = substr($params, strpos($params, '-')+1, strlen($params));
			channelConnect($userId, $lotteryId, $bot);
		} elseif (strpos($params, 'btn_botaddedtochan-') === 0) {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$getUser = substr($params, strpos($params, '-')+1, strlen($params));
			if (checkBotAndUserAccessToChannel($data['chan2'], $userId, $bot) === false) {
				//////////////
				addLog($userId, 'bot/user is not channel admin');
				$GLOBALS['botaddtochan'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
					[
						[
							['callback_data' => 'btn_botaddedtochan-'.$userId, "text" => $text['btnReady']]
						]
					],
					false,
					true
				);
				$bot->sendMessage($userId, $text['userIsNotAdmin']);
				$bot->sendMessage($userId, $text['addBotToChanAdmin'], false, null, null, $GLOBALS['botaddtochan']);
			} else {
				$query = "UPDATE lottery_list SET connectedChannels='".$data['chan2']."' WHERE id='".$data['lotteryId']."'";
				$result = $mysqli->query($query);
				if ($result) {
					$GLOBALS['startLottery'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
					[
						[
							['callback_data' => 'btn_startlottery-'.$data['lotteryId'], "text" => $text['enableLottery'].$data['lotteryId']]
						]
					],
					false,
					true
					);
					$GLOBALS['anotherChannel'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
					[
						[
							['callback_data' => 'btn_anotherchannel-'.$data['lotteryId'], "text" => "Да"],
							['callback_data' => 'btn_removechanadd-'.$data['lotteryId'], "text" => "Нет"]
						]
					],
					false,
					true
					);
					$bot->sendMessage($userId, $text['botIsAdmin'], false, null, null, $GLOBALS['startLottery']);
				}
				//insert into lottery_channels
				$res = $bot->getChat($data['chan2'])->getTitle();
				$chatName = $bot->getChat($data['chan2'])->getUserName();
				addlog('chanData (name)', $chatName);
				$query = "INSERT INTO lottery_channels (`userId`, `chanName`, `chanId`, `chatName`) VALUES ('".$userId."', '".$res."', '".$data['chan2']."', '".$chatName."')";
				$mysqli->query($query);
			}
		} elseif (strpos($params, 'btn_startlottery-') === 0) {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$lotteryId = substr($params, strpos($params, '-')+1, strlen($params));
			addLog($userId, 'send lottery '.$lotteryId.' to channel');
			if (sendLotteryToChannel($lotteryId, $bot)) {
				$GLOBALS['sednLotteryToChan'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
					[
						[
							['callback_data' => 'btn_endlottery-'.$lotteryId, "text" => $text['stopLottery']]
						]
					],
					false,
					true
				);
				$bot->sendMessage($userId, $text['lotteryToChanSended'].' #'.$lotteryId, false, null, null, $GLOBALS['sednLotteryToChan']);
				$bot->sendMessage($userId, $text['mainMenu'], false, null, null, $GLOBALS['mainMenu']);
			} else {
				$bot->sendMessage($userId, $text['lotteryNotActive']);
				$bot->sendMessage($userId, $text['mainMenu'], false, null, null, $GLOBALS['mainMenu']);
			}
		} elseif (strpos($params, 'btn_endlottery-') === 0) {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$lotteryId = substr($params, strpos($params, '-')+1, strlen($params));
			if (isLotteryActive($lotteryId)) {
				//end lottery
				$winners = getWinner($lotteryId, $bot);
				addLog('winner Id:', serialize($winners));
				if (is_array($winners)) {
					addLog('array winners');
					$winText = '';
					foreach ($winners as $key) {
						addLog('winner: ',$key);
						$query = "SELECT * FROM lottery_members WHERE lotteryId='".$lotteryId."' AND userId='".$key."'";
						$resultUser = $mysqli->query($query)->fetch_assoc();
						$winnerLogin = $resultUser['login'];
						$query = "SELECT * FROM lottery_list WHERE id='".$lotteryId."' AND del='0'";
						$result = $mysqli->query($query)->fetch_assoc();
						
						$mId = $result['mId'];
						$descr = $result['description'];
						$chanId = $result['connectedChannels'];
						$channelName = $result['connectedChannels'];
						$queryChanName = "SELECT * FROM lottery_channels WHERE chanId='".$channelName."'";
						$resChan = $mysqli->query($queryChanName)->fetch_assoc();
						$channelName = $resChan['chatName'];
						
						$winUrl = 'https://t.me/'.$channelName.'/'.$mId;
						addLog('mId: ', $mId);
						addLog('descr: ', $descr);
						addLog('chanId: ', $chanId);
						if ($winnerLogin != '') {
							$winnerName = '@'.$winnerLogin;
						} elseif (($resultUser['firstName'] != '') || ($resultUser['lastName'] != '')) {
							$winnerName = $resultUser['firstName'].' '.$resultUser['lastName'];
						} else {
							$winnerName = 'id: '.$key;
						}
						$winText .= ' '.$winnerName;
						addLog('winner name: ', $winnerName);
					}
					try {
						$bot->editMessageCaption($chanId, $mId, $descr.'
					побеждает: '.$winText);
					}
					catch (\TelegramBot\Api\Exception $e) {
						
					}
					try {
						$bot->editMessageText($chanId, $mId, $descr.'
побеждает: '.$winText);
					} catch (\TelegramBot\Api\Exception $e) {
						
					}
					
					$bot->sendMessage($chanId, 'Конкурс завершен!
Победитель(и): '.$winText.'
'.$winUrl);
					
					$bot->sendMessage($userId, $text['lotteryFinished'], false, null, null, $GLOBALS['mainMenu']);
				} else {
					addLog('winner: ', $winnerId.' from user: '.$userId);
					$bot->sendMessage($userId, $text['notFoundWinner']);
				}
			} else {
				$bot->sendMessage($userId, $text['lotteryNotActive']);
				$bot->sendMessage($userId, $text['mainMenu'], false, null, null, $GLOBALS['mainMenu']);
			}
		} elseif (strpos($params, 'btn_lotterystarted') === 0) {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			addLog($userId, 'list started lotteries');
			showStartedLotteries($userId, $bot);
		} elseif (strpos($params, 'btn_lotterycreated') === 0) {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$bot->sendMessage($userId, 'В разработке', false, null, null, $GLOBALS['mainMenu']);
			//list not started lotteries
			
		} elseif (strpos($params, 'btn_lotterydel') === 0) {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$lotteryId = substr($params, strpos($params, '-')+1, strlen($params));
			if (lotteryDelete($userId, $lotteryId)) {
				$bot->sendMessage($userId, $text['deleteCurrentLottery'], false, null, null, $GLOBALS['mainMenu']);
			} else {
				$bot->sendMessage($userId, 'lottery not deleted');
			}
		} elseif (strpos($params, 'btn_removechanadd-') === 0) {
			addLog($userId, $messageId.' id to remove');
			
		} elseif (strpos($params, 'btn_connectchannel') === 0) {
			$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
			$lotteryId = substr($params, strpos($params, '!')+1, strlen($params));
			addLog($userId. ' lotteryId: ', $lotteryId);
			$channelId = substr($params, strpos($params, '-')+1, strlen($params));
			$channelId = str_replace('!'.$lotteryId, '', $channelId);
			addLog($userId. ' channelId: ', $channelId);
			$query = "SELECT chanId, chanName FROM lottery_channels WHERE userId='".$userId."' AND id='".$channelId."'";
			$result = $mysqli->query($query)->fetch_assoc();
			$currentChannel = $result['chanName'];
			if (checkBotAndUserAccessToChannel($result['chanId'], $userId, $bot) === true) {
				$query = "UPDATE lottery_list SET connectedChannels='".$result['chanId']."' WHERE id='".$lotteryId."'";
				$result = $mysqli->query($query);
				if ($result) {
					$GLOBALS['startLottery'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
					[
						[
							['callback_data' => 'btn_startlottery-'.$lotteryId, "text" => $text['enableLottery'].$lotteryId]
						]
					],
					false,
					true
					);
					$bot->sendMessage($userId, $currentChannel.': '.$text['botIsAdmin'], false, null, null, $GLOBALS['startLottery']);
				}
			} else {
				addLog($userId, 'bot is not admin');
				$GLOBALS['botaddtochan'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
					[
						[
							['callback_data' => 'btn_botaddedtochan-'.$userId, "text" => $text['btnReady']]
						]
					],
					false,
					true
				);
				$bot->sendMessage($userId, $text['userIsNotAdmin']);
				$bot->sendMessage($userId, $text['addBotToChanAdmin'], false, null, null, $GLOBALS['botaddtochan']);
			}
			
		} else {
			//$bot->answerCallbackQuery($callbackQuery->getId(), $text['callbackText']);
		}
	});
	
	$bot->on(function ($update) use ($bot) {
		global $text;
		/*
		step1 - create lottery
		step2 - title
		step3 - description
		step4 - image
		step5 - countWinners
		chan1 - send message from chat
		chan2 - check is chat admin
		*/
		$message = $update->getMessage();
		$userId = $message->getChat()->getId();
		$postText=$message->getText();
		$userName = $message->getChat()->getUsername();
		$data = getUdata($userId);
		if (checkUser($userId)) {
			addLog($userId, serialize($data));
			if ((isset($data['step1'])) && (!isset($data['title']))) {
				//started, wait title
				$bot->sendMessage($userId, $text['getDescription']);
				$uData = array('title' => $postText);
				$data = array_merge($uData, $data);
				setUdata($userId, $data);
			} elseif ((isset($data['title'])) && (!isset($data['description']))) {
				//started, wait description
				$bot->sendMessage($userId, $text['getImage'], false, null, null, $GLOBALS['imageReply']);
				$uData = array('description' => $postText);
				$data = array_merge($uData, $data);
				setUdata($userId, $data);
			} elseif (($message->getDocument()) && ((isset($data['description'])) && (isset($data['image'])) && (!isset($data['file'])))) {
				$accept = array('image/png', 'image/jpeg', 'image/gif', 'image/bmp', 'image/tiff', 'image/tiff');
				if (in_array(strtolower($message->getDocument()->getMimeType()), $accept)) {
					//file sended uncompressed image
					addLog($userId, 'image mime correct');
					$file = $message->getDocument();
					$file_id = $file->getFileId();
					$url = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/getFile?file_id='.$file_id;
					$str = my_curl($url);
					$strj = json_decode($str,true);
					$file_path = $strj['result']['file_path'];
					$link = 'https://api.telegram.org/file/bot'.$GLOBALS['token'].'/'.$file_path;
					addLog($userId, 'link: '.$file_path);
					$uploaddir = './files/';
					$uploadfile = $uploaddir.$userId.'_'.basename($link);
					addLog($userId, 'upload:'.$uploadfile);
					$uData = array('file' => $uploadfile);
					$data = array_merge($uData, $data);
					setUdata($userId, $data);
					copy($link, $uploadfile);
					$bot->sendMessage($userId, $text['countWinners']);
				} else {
					$bot->sendMessage($userId, $text['errorImgFormat']);
				}
			} elseif (($message->getPhoto()) && ((isset($data['description'])) && (isset($data['image'])) && (!isset($data['file'])))) {
				//file sended compressed image
				$fileC = [];
				$fileC = $message->getPhoto();
				$file_id = $fileC[count($fileC)-1]->getFileId();
				$url = 'https://api.telegram.org/bot'.$GLOBALS['token'].'/getFile?file_id='.$file_id;
				$str = my_curl($url);
				$strj = json_decode($str,true);
				$file_path = $strj['result']['file_path'];
				$link = 'https://api.telegram.org/file/bot'.$GLOBALS['token'].'/'.$file_path;
				$uploaddir = './files/';
				$uploadfile = $uploaddir.$userId.'_'.basename($link);
				$uData = array('file' => $uploadfile);
				$data = array_merge($uData, $data);
				setUdata($userId, $data);
				copy($link, $uploadfile);
				$bot->sendMessage($userId, $text['countWinners']);
			} elseif ((isset($data['image'])) && (!isset($data['countWinners']))) {
				if (preg_match("/^[0-9]{1,10}/i", $postText)) {
					$uData = array('countWinners' => $postText);
					$data = array_merge($uData, $data);
					setUdata($userId, $data);
					$bot->sendMessage($userId, $text['lotteryReady']);
					sendLotteryView($userId, $bot);
				} else {
					$bot->sendMessage($userId, $text['countWinnersError']);
				}
			} elseif ((isset($data['chan1'])) && (!isset($data['chan2']))){
				//gen chan2
				$GLOBALS['botaddtochan'] = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup (
					[
						[
							['callback_data' => 'btn_botaddedtochan-'.$userId, "text" => $text['btnReady']]
						]
					],
					false,
					true
				);
				$bot->sendMessage($userId, $text['addBotToChanAdmin'], false, null, null, $GLOBALS['botaddtochan']);
				addLog($userId, 'forward analyze');
				checkForward($userId, $message, $bot);
			}
		} else {
			if (addUser($userId, $userName)) {
				$bot->sendMessage($userId, $text['hello']);
			} else {
				$bot->sendMessage($userId, $text['textAddError']);
			}
		}
	}, function($message) use ($name) {
		return true;
		}
	);
	$bot->run();
	}
catch (\TelegramBot\Api\Exception $e) {
	file_put_contents('errors.txt', sprintf("[TelegramAPI]\t[%s]\t%s\n", date('Y-m-d H:i:s'), $e->getMessage()), FILE_APPEND);
	return;
}
?>