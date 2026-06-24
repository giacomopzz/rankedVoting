<?php
    include_once("election_code.php");

    $theme = get_election_theme();
    if($theme !== null){
        echo $theme;
    }
    $options = get_all_candidates();
    foreach($options as $option){
        echo ";".$option[1];
    }
?>
