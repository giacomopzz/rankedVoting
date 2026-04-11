function textToArray(text){
	idx=0;
	testo='';
	arr=[];
	while(text[idx]!=';'){
		if(text[idx]==','){
			arr.push(testo);
			testo='';
		}
		else{
			testo=testo+text[idx];
		}
		idx++;
	}
	if(testo){
		arr.push(testo);
	}
	return arr;
}
function new_ajax(){
	if(window.XMLHttpRequest){
		ajax= new XMLHttpRequest();
	}
	else{
		ajax= new ActiveXObject("Microsoft.XMLHTTP");
	}
	return ajax;
}
function send_request(user_idx, element_id){
	ajax_request=new_ajax();
	ajax_request.onreadystatechange=function ajax_state_change(){
		if(ajax_request.readyState==4 && ajax_request.status==200){
			// do stuff
			valutation_array=textToArray(ajax_request.responseText);
		}
	}
	ajax_request.open("post",loca+"valutation/get_valutation.php",true);
	ajax_request.setRequestHeader("content-type","application/x-www-form-urlencoded");
	ajax_request.send("user_idx="+user_idx);
}

function controlBallot(numOptions){
	let selectedOptions = [];
	let selectedRanks = [];
	for(let rIdx = 0; rIdx <= numOptions; rIdx++){
		for(let oIdx = 0; oIdx < numOptions; oIdx++){
			if(oIdx == 0)
				selectedRanks[rIdx] = false; // add new column
			if(rIdx == 0)
				selectedOptions[oIdx] = false; // add new row
			let currentId = "op"+(oIdx+1)+"_"+(rIdx == numOptions ? "veto" : rIdx+1);
            let checkbox = document.getElementById(currentId);
            if(checkbox != null){
                let isChecked = checkbox.checked;
                selectedOptions[oIdx] |= isChecked;
                selectedRanks[rIdx] |= isChecked;
            }
		}
	}
    let notComingCheckbox = document.getElementById("op_no_vote");
    let isNotComing = true;
    if(notComingCheckbox != null)
        isNotComing = notComingCheckbox.checked;

	// disable/enable all rows and columns that have been selected
	for(let rIdx = 0; rIdx <= numOptions; rIdx++){
		for(let oIdx = 0; oIdx < numOptions; oIdx++){
			let currentId = "op"+(oIdx+1)+"_"+(rIdx == numOptions ? "veto" : rIdx+1);
			let checkbox = document.getElementById(currentId);
            if(checkbox == null)
                continue;
            if(isNotComing){
    			checkbox.disabled = true;
                continue;
            }
			if(checkbox.checked){
                checkbox.disabled = false;
				continue; // checked boxes are always enabled
            }
			let canCheck = !selectedOptions[oIdx] && (!selectedRanks[rIdx] || rIdx == numOptions);
			checkbox.disabled = !canCheck;
		}
	}
}

function showControl(controlId){
    let controls = document.getElementsByClassName('hiddable_control');
    Array.from(controls).forEach( (control) => {
        control.hidden = control.id != controlId;
        let tabButton = document.getElementById(control.id+"_button");
        tabButton.disabled = !control.hidden;
    });
}

function initPage(numOptions,initialTab){
    showControl(initialTab);
    controlBallot(numOptions);
}

function redirect(address){
    window.location.href = address;
}
