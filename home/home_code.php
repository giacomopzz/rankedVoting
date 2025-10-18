<?php
require_once('election/election_code.php');

function ArrayToText($arr){
	$idx=1;
	$text=''.$arr[0];
	while(@$arr[$idx]){
		$text=$text.','.$arr[$idx];
		$idx++;
	}
	$text=$text.';';
	return $text;
}

function ordinalString(int $value){
    if($value%10 == 1 && $value%100 !=11)
        return "st";
    if($value%10 == 2 && $value%100 != 12)
        return "nd";
    if($value%10 == 3 && $value%100 != 13)
        return "rd";
    return "th";
}

function printBallotSummary(Ballot $ballot, $options, int $highlightOptionIdx = -1)
{
    // $options is in the same format of the output of get_all_candidates()
    // So an array of [optionIdx, optionName]
    echo "<ol>";
    $vetos = array();
    foreach($ballot->votes as $vote){
        $selectedOptionIdx = 0;
        for($oIdx = 0; $oIdx < count($options); $oIdx++){
            if($options[$oIdx][0] == $vote->optionIdx){
                $selectedOptionIdx = $oIdx;
                break;
            }
        }
        if($vote->rank != -1){
            $optionName = $options[$selectedOptionIdx][1];
            if($options[$selectedOptionIdx][0] == $highlightOptionIdx)
                $optionName = "<b>".$optionName."</b>";
            echo "<li>".$optionName."</li>";
        }
        else{
            $vetos[] = $selectedOptionIdx;
        }
    }
    foreach($vetos as $vetoed){
        $optionName = $options[$vetoed][1];
        if($options[$vetoed][0] == $highlightOptionIdx)
            $optionName = "<b>".$optionName."</b>";
        echo "<li><s>".$optionName."</s></li>";
    }
    echo "</ol>";
}
?>
