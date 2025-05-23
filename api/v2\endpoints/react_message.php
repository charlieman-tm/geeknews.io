<?php
$reactions_types = array_keys($wo['reactions_types']);
if (!empty($_POST['id']) && is_numeric($_POST['id']) && $_POST['id'] > 0 && !empty($_POST['reaction']) && in_array($_POST['reaction'], $reactions_types)) {
	$message_id = Wo_Secure($_POST['id']);
	$is_reacted = $db->where('user_id',$wo['user']['user_id'])->where('message_id',$message_id)->getValue(T_REACTIONS,'COUNT(*)');
	if ($is_reacted > 0) {
		$db->where('user_id',$wo['user']['user_id'])->where('message_id',$message_id)->delete(T_REACTIONS);
		$db->insert(T_REACTIONS,array('user_id' => $wo['user']['id'],
	                                   'message_id' => $message_id,
	                                   'reaction' => Wo_Secure($_POST['reaction'])));
		$response_data = array(
			                    'api_status' => 200,
			                    'message' => 'reaction removed'
			                );
	}
	else{
		$db->insert(T_REACTIONS,array('user_id' => $wo['user']['id'],
	                                   'message_id' => $message_id,
	                                   'reaction' => Wo_Secure($_POST['reaction'])));
		$response_data = array(
			                    'api_status' => 200,
			                    'message' => 'message reacted'
			                );
	}
}
else{
	$error_code    = 5;
    $error_message = 'id , reaction can not be empty.';
}