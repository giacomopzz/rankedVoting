<?php
function set_log(){
	if(!isset($_SESSION['logged'])){
		$_SESSION['logged']=false;
	}
}
function is_logged(){
	$log_cookie=(isset($_COOKIE['logged']) && $_COOKIE['logged']=="true");
	$log_session=(isset($_SESSION['logged']) && $_SESSION['logged']);
	if(!$log_session && $log_cookie){
		$_SESSION['logged']==true;
		$_SESSION['user']==$_COOKIE['user'];
		$_SESSION['user_idx']=$_COOKIE['user_idx'];
		$_SESSION['user_admin']=$_COOKIE['user_admin'];
		$_SESSION['user_voter']=$_COOKIE['user_voter'];
	}
	$log_session=(isset($_SESSION['logged']) && $_SESSION['logged']);
	if($log_session || $log_cookie){
		return true;
	}
	else{
		return false;
	}
}

// start the session and init session variables
session_start();
set_log();
?>
