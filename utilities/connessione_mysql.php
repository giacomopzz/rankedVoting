<?php
	$db_host="localhost";
	$db_user="toaluce";
	$db_password="";
	$db_database="my_toaluce";

	// all db's tables
	$db_user_table="votingUsers";
	$db_user_roles_table="votingRoles";
	$db_options_table="votingOptions";
	$db_elections_table="votingElections";
	$db_ranks_table="votingRanks";
	$db_aliases_table="votingAliases";
    $db_series_table="votingSeries";
    $db_results_table="votingResults";
	

	//connection
	$mysqli=mysqli_connect($db_host, $db_user, $db_password, $db_database);
?>
