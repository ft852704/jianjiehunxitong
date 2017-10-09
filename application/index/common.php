<?php
function is_power($action, $power){
	if(in_array(strtolower($action), $power)){
		return true;
	}else{
		return false;
	}
}
?>