<?php
namespace App\Http\Controllers;

class MailSmsController extends Controller {

	var $data = array();
	var $panelInit ;
	var $layout = 'dashboard';

	public function __construct(){
		if(app('request')->header('Authorization') != ""){
			$this->middleware('jwt.auth');
		}else{
			$this->middleware('authApplication');
		}

		$this->panelInit = new \DashboardInit();
		$this->data['panelInit'] = $this->panelInit;
		$this->data['breadcrumb']['Settings'] = \URL::to('/dashboard/languages');
		$this->data['users'] = $this->panelInit->getAuthUser();
		if(!isset($this->data['users']->id)){
			return \Redirect::to('/');
		}
		if($this->data['users']->role == "student" || $this->data['users']->role == "parent") exit;
		if($this->data['users']->role == "teacher" AND $this->data['users']->allowTeachersMailSMS == 'none') exit;

		if(!$this->panelInit->hasThePerm('mailsms')){
			exit;
		}
	}

	public function listAll($page = 1)
	{
		$return = array();
		$mailsms = \mailsms::orderBy('id','desc')->orderBy('id','DESC');
		$return['totalItems'] = $mailsms->count();

		$mailsms = $mailsms->take('20')->skip(20* ($page - 1) )->get()->toArray();
		$return['items'] = array();
		foreach ($mailsms as $value) {
			if(is_numeric($value['messageDate'])){
				$value['messageDate'] = $this->panelInit->unix_to_date($value['messageDate']);
			}
			$value['messageData'] = htmlspecialchars_decode($value['messageData'],ENT_QUOTES);
			if(strlen($value['mailTo']) > 10){
				$value['mailTo'] = "Custom users list";
			}
			$return['items'][] = $value;
		}
		return $return;
	}

	public function delete($id){
		if ( $postDelete = \mailsms::where('id', $id)->first() )
		{
			$postDelete->delete();
			return $this->panelInit->apiOutput(true,"Delete item","Item deleted successfully");
		}else{
			return $this->panelInit->apiOutput(false,"Delete item","Item not exist");
		}
	}

	public function templates(){
		return \mailsms_templates::select('id','templateTitle','templateMail','templateSMS')->get();
	}

	public function create(){
		$mailsms = new \mailsms();

		if($this->data['users']->role == "teacher" AND \Input::get('userType') != "students"){
			exit;
		}

		if(\Input::get('userType') == "users"){
			$mailsms->mailTo = json_encode(\Input::get('selectedUsers'));
		}else{
			$mailsms->mailTo = \Input::get('userType');
		}

		$mailsms->mailType = \Input::get('sendForm');

		$messageData = " ";
		if(\Input::get('messageTitle') != ""){
			$messageData .= \Input::get('messageTitle');
		}
		if(\Input::get('messageContent') != ""){
			if(\Input::get('sendForm') == "email"){
				$messageData .= htmlspecialchars(\Input::get('messageContentMail'),ENT_QUOTES);
			}else{
				$messageData .= htmlspecialchars(\Input::get('messageContentSms'),ENT_QUOTES);
			}
		}
		$mailsms->messageData = $messageData;

		$mailsms->messageDate = time();
		$mailsms->messageSender = $this->data['users']->fullName . " [ " . $this->data['users']->id . " ] ";
		$mailsms->save();

		if(\Input::get('userType') == "admins"){
			$sedList = \User::where('role','admin')->get();
		}
		if(\Input::get('userType') == "teachers"){
			$sedList = \User::where('role','teacher')->get();
		}
		if(\Input::get('userType') == "students"){
			$sedList = \User::where('role','student')->whereIn('studentClass',\Input::get('classId'));
			if(\Input::has('sectionId')){
				$sedList = $sedList->whereIn('studentSection',\Input::get('sectionId'));
			}
			$sedList = $sedList->get();
		}
		if(\Input::get('userType') == "parents"){
			$stdInClassIds = \User::where('role','student')->whereIn('studentClass',\Input::get('classId'))->select('id');
			if($this->panelInit->settingsArray['enableSections'] == true){
				$stdInClassIds = $stdInClassIds->whereIn('studentClass',\Input::get('sectionId'));
			}
			$stdInClassIds = $stdInClassIds->get()->toArray();

			$sedList = \User::where('role','parent')->where(function ($query) use ($stdInClassIds) {
								while (list(, $value) = each($stdInClassIds)) {
									$query = $query->orWhere('parentOf', 'like', '%"'.$value['id'].'"%');
								}
								})->get();
		}
		if(\Input::get('userType') == "users"){
			$usersList = array();
			$selectedUsers = \Input::get('selectedUsers');
			foreach ($selectedUsers as $user) {
				$usersList[] = $user['id'];
			}

			$sedList = \User::whereIn('id',$usersList)->get();
		}

		$SmsHandler = new \MailSmsHandler();

		if(\Input::get('sendForm') == "email"){
			$sendMessage = \Input::get('messageContentMail');
		}else{
			$sendMessage = \Input::get('messageContentSms');
		}

		if(\Input::get('sendForm') == "email"){
			foreach ($sedList as $user) {
				if (strpos($user->comVia, 'mail') !== false) {
					$SmsHandler->mail($user->email,\Input::get('messageTitle'),$sendMessage,$user->fullName);
				}
			}
		}

		if(\Input::get('sendForm') == "sms"){
			foreach ($sedList as $user) {
				if($user->mobileNo != "" AND strpos($user->comVia, 'sms') !== false){
					$SmsHandler->sms($user->mobileNo,strip_tags( trim(str_replace(PHP_EOL,' ',strip_tags($sendMessage))) ));
				}
			}
		}

		return $this->listAll();
	}


}