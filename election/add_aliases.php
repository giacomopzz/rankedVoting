<?php
    include_once("election_code.php");

    $electionIdx = get_open_election()[0];
    $options = get_all_candidates($electionIdx);
    $query = "replace into ".$db_aliases_table." (`electionIdx`,`optionIdx`,`alias`) values ";
    $isFirst = true;
    for($idx = 0; $idx < count($options); $idx++){
        $option = $options[$idx];
        $optionKey = str_replace(" ","_",$option[1]);
        if(isset($_GET[$optionKey])){
            $alias = mysqli_real_escape_string($mysqli, $_GET[$optionKey]);
            $query = $query.($isFirst ? "" : ",")."('".$electionIdx."','".$option[0]."','".$alias."')";
            $isFirst = false;
            echo $option[1]." → ".$alias."<br>";
        }
    }
    if($isFirst){
        // no alias to add
        echo "Nothing to do";
    }
    else{
        $success = mysqli_query($mysqli, $query);
    }
?>
