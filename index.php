<?php 
//Bittrex trading bot

//Bittrex Info - Apikey and api secret from account
$apikey = 'REMOVED';
$apisecret = 'REMOVED';
$nonce = time();

//MySql database and connection informatiom.
$user = 'bittrade';
$pass = 'lovetotrade';
$database = 'crypto_dat';
$con = mysqli_connect('127.0.0.1', $user, $pass);
// $uri = 'https://bittrex.com/api/v1.1/market/getopenorders?apikey='.$apikey.'&nonce='.$nonce;
// $sign = hash_hmac('sha512',$uri,$apisecret);
// $ch = curl_init($uri);
// curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:'.$sign));
// $execResult = curl_exec($ch);
// $obj = json_decode($execResult);

/**
	This function checks that a connection can be made to mysql as well as
	the right database can be accessed.
*/
function mysql_con() {
	//check the connection
	if(!$con) {
		echo 'Not Connected to MySql.';
		return false;
	}
	else {
		return true;
	}
	//check the database
	if(!mysqli_select_db($con, '$database')) {
		echo 'Not Connected to MySql database.';
		$chg_db = "USE ".$database;
		if(!mysqli_query($con,$chg_db)){
			echo 'Still couldnt connect to database.';
			return false;
		}
	}
	else {
		return true;
	}
}

/**
	Save all data for a currency inside a mysql database table. This means that bitcoin, litecoin, ethereun, etc. would each have its own table. This function also checks to make sure the table exists and creates it if need be.
*/
function save_data($symbol, $cost, $hr_chg, $day_chg, $week_chg, $prev_24_vol) {
	if (mysql_con()) {
		$test_tab = "SELECT 1 FROM rec_info_".$symbol." LIMIT 1";
		$now = date("Y-m-d H:i:s");
		
		if($test_tab){
			//record info in the table
			$record = "INSERT INTO rec_info_".$symbol." (cost, hr_chg, day_chg, week_chg, time, prev_24_vol) VALUES ('$cost','$hr_chg', '$day_chg', '$week_chg', '$now','$prev_24_vol')";
			if(!mysqli_query($con,$record)){
				echo 'Query not successful.';
			}
		}
		else {
			//create the table
			$create = "CREATE TABLE rec_info_".$symbol." ( rec_".$symbol."_ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY, cost DOUBLE, hr_chg DOUBLE, day_chg DOUBLE, week_chg DOUBLE, time DATE, prev_24_vol DOUBLE)";
			if(!mysqli_query($con,$create)){
				echo 'Query not successful. Table not created';
			}
			//record info in the table
			$record = "INSERT INTO rec_info_".$symbol." (cost, hr_chg, day_chg, week_chg, time, prev_24_vol) VALUES ('$cost','$hr_chg', '$day_chg', '$week_chg', '$now','$prev_24_vol')";
			if(!mysqli_query($con,$record)){
				echo 'Query not successful.';
			}
		}
	}
}

/**
	This function pulls the 10 most recent records from the database table based on selected currency.
	The purpose here is to determine trends.
*/
function check_hist($symbol, $opt){
	if(mysql_con()) {
	//Options for getting info from mysql database	
		$query_list = "SELECT * FROM (SELECT * FROM rec_info_".$symbol." ORDER BY rec_".$symbol."_ID DESC LIMIT 10) sub ORDER BY rec_".$symbol."_ID ASC";
		echo 'Info Retrieved: '.$query_list;
		if($opt = 'list') {
		//pull last 10 records from mysql database
			
		}
		elseif ($opt = 'A') {
			
		}
		else {

		}
	}
}

/**
	This is an API call to Bittrex to determine the current balances of available wallets or accounts.
*/
function bitt_balance($apikey, $apisecret){
	$nonce = time();
	$uri = 'https://bittrex.com/api/v1.1/account/getbalance?apikey='.$apikey.'&currency=BTC&nonce='.$nonce;
	$sign = hash_hmac('sha512',$uri,$apisecret);
	$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:'.$sign));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$execResult = curl_exec($ch);
	$obj = json_decode($execResult, true);
	$balance = $obj["result"]["Available"];
	return $balance;
}

/**
	This is an API call to Bittrex using the api key and secret to make a purchase of a specific currency. This function would be crucial for the actual automatic buying.
*/
function bitt_buy($apiket, $apisecret, $symbol, $quant, $rate) {
	$nonce = time();
	$uri = 'https://bittrex.com/api/v1.1/account/buylimit?apikey='.$apikey.'&market=BTC-'.$symbol.'&quantity='.$quant.'&rate='.$rate.'&nonce='.$nonce;
	$sign = hash_hmac('sha512', $uri, $apisecret);
	$ch = curl_init($uri);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('apisign:'.$sign));
	$execResult = curl_exec($ch);
	$obj = json_decode($execResult, true);
	return $obj;
}

/**
	This is the main part of the program that runs the check with coin market cap to save the values
	for  cryptocurrencies, determine which of those is doing well within parameters, and then make
	a buy or sell depending on some math.
*/
$cmkt = "https://api.coinmarketcap.com/v1/ticker/?limit=50";
$fgc = json_decode(file_get_contents($cmkt), true);
$counter = 0;
for($i=0;$i<50; $i++) {
	$symbol = $fgc[$i]["symbol"];
	$perc_chg_h = $fgc[$i]["percent_change_1h"];
	$perc_chg_d = $fgc[$i]["percent_change_24h"];
	$perc_chg_w = $fgc[$i]["percent_change_7d"];
	$vol = $fgc[$i]["24h_volume_usd"];
	$cost = $fgc[$i]["price_btc"];
	$balance = bitt_balance($apikey, $apisecret);
	$fifth_bal = $balance / 5;
	save_data($symbol, $cost, $perc_chg_h, $perc_chg_d, $perc_chg_w, $vol)
	if($counter < 4) {
		if ($perc_chg < 4 && $perc_chg > -4) {
			$coin_hist = check_hist($symbol, 'list')
			if ($balance > 0) {
				$am_to_buy = $fifth_bal / $cost;
				$buy = bitt_buy($apikey, $apisecret, $symbol, $am_to_buy, $cost);
				echo "<br>Balance is: ".$balance;
				$counter++;
			}
		}
	}
}

?>