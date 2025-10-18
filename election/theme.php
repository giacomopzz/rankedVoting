<?php
    include_once("election_code.php");

    $theme = get_election_theme();
    if($theme !== null){
        echo $theme;
    }
?>
