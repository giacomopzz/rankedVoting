<?php
require_once("../utilities/connessione_mysql.php");
function login($user,$password){
	global $db_user_table;
	global $db_user_roles_table;
    global $mysqli;
    $escapedUser = mysqli_real_escape_string($mysqli, $user);
    if($escapedUser != $user){
		echo "<br>invalid username.<br>";
		return [-1, ""];
    }
	$query="select u.password, u.userIdx, r.role from ".$db_user_table." as u inner join $db_user_roles_table as r on u.roleIdx=r.roleIdx where username='".$user."'";
	$result=mysqli_query($mysqli, $query);
	$row=mysqli_fetch_array($result);
	if($row['password']==$password){
		return [$row['userIdx'], $row['role']];
	}
	else if(!$row){
		echo "invalid username.<br>";
		return [-1, ""];
	}
	else{
		return [-1, ""];
	}
}
?>
