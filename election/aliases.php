<?php
    include_once("election_code.php");

    $theme = get_election_theme();
    echo "Theme: ".($theme === null ? "none" : $theme)."<br>";
    echo "Aliases:<br>";
    $electionIdx = get_open_election()[0];
    $options = get_all_candidates($electionIdx);
    foreach($options as $option){
        echo $option[1]." → ".$option[2]."<br>";
    }
?>
