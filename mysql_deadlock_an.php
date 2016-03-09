
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
	<title>MySQL Deadlock Analyzer</title>
</head>


SHOW ENGINE INNODB STATUS の結果を入れてね<br />
<form method="POST">
	<textarea name="deadlock_string"></textarea>
	<br />
	<input type="submit" value="翻訳" />
</form>


<?php

if (isset($_POST['deadlock_string'])) {
	$status_arr = explode("\n", $_POST['deadlock_string']);		// 配列に展開
	
	// デッドロック用のセクションを見つけるためのキーワードを定義
	$keywords = array(
		'*** (1) TRANSACTION:', 
		'*** (1) WAITING FOR THIS LOCK TO BE GRANTED:', 
		'*** (2) TRANSACTION:', 
		'*** (2) HOLDS THE LOCK(S):', 
		'*** (2) WAITING FOR THIS LOCK TO BE GRANTED:',
		'*** WE ROLL BACK',
	);
	
	// キーワードから各セクションの位置を調べる
	$pos_arr = array();
	for ($i = 0; $i <= count($status_arr); $i++) {
		if ($keywords[count($pos_arr)] == substr(trim($status_arr[$i]), 0, strlen($keywords[count($pos_arr)]))) {
			// キーワードが見つかったので位置を記録
			$pos_arr[] = $i;
			
			// 終了判定
			if (count($keywords) == count($pos_arr)) {
				break;
			}
		}
	}
	
	echo '-------------------------------------------- <br />';
	echo 'トランザクションその1<br />';
	echo 'トランザクション2のロックによりこのクエリが実行待ちとなっていた <br />';
	echo '-------------------------------------------- <br />';
	transactionAn($status_arr, $pos_arr[0]);

	echo '<br />';
	echo '-------------------------------------------- <br />';
	echo 'トランザクションその1が欲しかったロック<br />';
	echo '-------------------------------------------- <br />';
	lockGrantAn($status_arr, $pos_arr[1]);
	
	echo '<br />';
	echo '-------------------------------------------- <br />';
	echo 'トランザクションその2<br />';
	echo 'このクエリを実行しようとした際にデッドロックを検出した <br />';
	echo '-------------------------------------------- <br />';
	transactionAn($status_arr, $pos_arr[2]);
	
	echo '<br />';
	echo '-------------------------------------------- <br />';
	echo 'トランザクションその1を待たせたロック<br />';
	echo '-------------------------------------------- <br />';
	lockGrantAn($status_arr, $pos_arr[3]);
	
	echo '<br />';
	echo '-------------------------------------------- <br />';
	echo 'トランザクションその2が欲しかったロック<br />';
	echo '-------------------------------------------- <br />';
	lockGrantAn($status_arr, $pos_arr[4]);}

function transactionAn($status_arr, $pos)
{
	$match_arr =array();
	preg_match('/TRANSACTION ([0-9A-Z]+), ACTIVE ([0-9]+)/i', $status_arr[$pos + 1], $match_arr);
	$transaction_id = $match_arr[1];
	$transaction_sec = $match_arr[2];
	
	$transaction_query = $status_arr[$pos + 5];
	
	echo "トランザクションID: {$transaction_id} <br />";
	echo "トランザクション時間: {$transaction_sec}秒 <br />";
	echo "クエリ: {$transaction_query}<br />";
}

function lockGrantAn($status_arr, $pos)
{
	$match_arr =array();
	preg_match('/RECORD LOCKS .* index `(.*)` of table `.*`.`(.*)`.* trx id [0-9A-Z]+ (lock_mode|lock mode) ([A-Z]+)(.*)/i', $status_arr[$pos + 1], $match_arr);
	$lock_index = $match_arr[1];
	$lock_table = $match_arr[2];
	$lock_mode_base = $match_arr[4];
	$lock_type_base = $match_arr[5];
	
	$lock_mode = '';
	if		('X' == $lock_mode_base) $lock_mode = '排他ロック';
	elseif	('S' == $lock_mode_base) $lock_mode = '共有ロック';
	
	$lock_type = 'ネクストキーロック';
	if		(preg_match('/^ locks gap before rec/i', $lock_type_base)) 		$lock_type = 'キャップロック';
	elseif	(preg_match('/^ locks rec but not gap/i', $lock_type_base)) 		$lock_type = 'レコードロック';
	elseif	(preg_match('/^ locks insert intention/i', $lock_type_base)) 	$lock_type = '挿入インテンションキャップロック';
	
	echo "ロックされていたテーブル: {$lock_table} <br />";
	echo "ロックされていたインデックス: {$lock_index} <br />";
	echo "ロックモード: {$lock_mode} <br />";
	echo "ロックタイプ: {$lock_type} <br />";
}

?>
