<?php
/*******************************************************************************
* Firby Application - Plugin                                                                      *
*                                                                              *
* Version: 1.0.0                                                                 *
* Date:    2016-04-16                                                          *
* Author:  Fabian Schenk   
* Copyright (c) 2016 Fabian Schenk <fabianschenk86@googlemail.com>, http://firby.lima-city.de                                         *
*******************************************************************************/

if(get('firby-username') and get('firby-password') ){
	header('Access-Control-Allow-Origin: *');
	header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
	header('Content-Type: application/json');	
	$json = array();
	if($user = site()->user(get('firby-username')) and $user->login(get('firby-password'))){
		if($user->hasPanelAccess()=='true' and get('firby-type')){
			if(get('firby-uri')){
				$currentpage = page(get('firby-uri'));
			}
			$currentuser = site()->user(get('firby-username'));
			function changeuserhistory($currentuser,$value){
				$history=array();
				if($currentuser->history()!='' && $currentuser->history()!= 'undefined'){
					$history=$currentuser->history();
				}
				if (in_array(get('firby-uri'), $history)) {
					$history = array_diff($history, array($value));
					array_unshift($history, $value);
				}else{
					array_unshift($history, $value);
				}
				if(count($history)>5){
					$remove = count($history)-5;
					for($i=0;$i<$remove;$i++){
						array_pop($history);
					}
				}
				return $history;
			}
			switch (get('firby-type')) {
				case "addwebsite":
					$json['title'] = (string)site()->title();
					$json['url'] = (string)site()->url();
					$json['username'] = (string)get('firby-username');
					$json['password'] = (string)get('firby-password');
					$json['modified'] = (string)site()->modified();
					$json['message'] = array('type'=>'success');			
					break;
				case "siteinfo":
					try {
					$json['locale'] = (string)site()->locale();
					if(site()->multilang()){
						$languages = array();
						foreach(site()->languages() as $language){
							$languages[] = array(
								'default'=>(string)$language->default(),
								'name'=>(string)$language->name(),
								'code'=>(string)$language->code(),
								'url'=>$language->url()
							);
						}
						$json['languages'] = $languages;
					}
					function subpages($uri){
						$subpage = array();
						foreach(site()->page($uri)->children() as $p){
							$subpage[] = array(
								'title'=>(string)$p->title(),
								'uri'=>(string)$p->uri(),
								'sortnumber'=>(string)$p->num(),
								'isInvisible'=>(string)$p->isInvisible(),
								'children'=>subpages($p->uri())
							);
						}	
						return $subpage;
					}
					$navigation = array();
					foreach(site()->pages() as $p){
						$navigation[] = array(
							'title'=>(string)$p->title(),
							'uri'=>(string)$p->uri(),
							'sortnumber'=>(string)$p->num(),
							'isInvisible'=>(string)$p->isInvisible(),
							'children'=>subpages($p->uri())
						);
					}						
					$json['navigation'] = $navigation;
                    if($avatar = $currentuser->avatar()){
						$imageData = base64_encode(file_get_contents($currentuser->avatarRoot()));
						$type = pathinfo($avatar, PATHINFO_EXTENSION);
						$avatar_base64 = 'data:image/'.$type.';base64,'.$imageData;		
					}else{
						$avatar_base64 ='';
					}
					if($currentuser->email()!= '' && $currentuser->username()!= ''){
						$history = array();
						if($histories = $currentuser->history()){
							foreach($histories as $value) {
								if($value!='' && page($value)!=''){
									$history[] = array(
										'title' => (string)page($value)->title(),
										'uri'=> (string)$value
									);
								}
							}
						}
						$admininfo = array(
							'username'=>(string)$currentuser->username(),
							'history'=> $history,
							'avatar'=> $avatar_base64
						);	
					}
					$json['admininfo'] = $admininfo;
					$templates = array();
					$tempfolder = kirby::instance()->roots()->blueprints().DS;
					foreach(glob($tempfolder."*.php")as $template) {
						if($template!=$tempfolder."error.php" && $template!=$tempfolder."home.php"){
							$tempvalue = str_replace(".php", "", $template);
							$tempvalue = str_replace($tempfolder, "", $tempvalue);
							$blueprints = data::read($template, 'yaml');
							$templates[] = array(
								'title'=>$blueprints['title'],
								'value'=>$tempvalue
							);
						}
					}
					$json['templates'] = $templates;
					$users = array();
					foreach(site()->users() as $singeluser){
						if($singeluser->username()!= ''){
							$users[] = (string)$singeluser->username();
						}
					}
					$json['users'] = $users;	
					$json['message'] = array('type'=>'success');
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "mainsitepages":
					try{
						$children = array();
						foreach (site()->children() as $child){
							$children[] = array(
								'title'=>(string)$child->title(), 
								'uri' =>(string)$child->uri(),
								'uid' =>(string)$child->uid(),
								'modified'=>(string)$child->modified('d/m/Y'),
								'num'=>(string)$child->num(),
								'isVisible'=>(string)$child->isVisible(),
								'isDeletable'=>(string)$child->isDeletable()
							);
						}
						$json['children'] = $children;
						$json['message'] = array('type'=>'success');
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "kirbymainsite":
					try{
						$blueprints = data::read(kirby::instance()->roots()->blueprints().DS.kirby()->site()->template().'.php', 'yaml');
						$fields = array();
						foreach ($blueprints['fields'] as $key=>$value) {
							if($value['type'] == 'structure'){
								$subfields = array();
								foreach($value['fields'] as $subkey=>$subfield){
									$subfields[$subkey] = $subfield;
								}
								$subblueprints = array(
									'label'=>$value['label'],
									'type'=>$value['type'],
									'entry'=>kirby()->site()->$key()->value(),
									'fields'=>$subfields,
								);
								$content = array();
								foreach(kirby()->site()->$key()->yaml() as $subvalue){
									$content[] =  array(
										'key'=>$key,
										'blueprint'=>$subblueprints,
										'content'=>$subvalue,
									);
								}
							}else{
								$content = (string)site()->content()->$key();
							}
							$fields[] = array(
								'key'=>$key,
								'blueprint'=>$value,
								'content'=>$content
							);
						};
						$json['content'] = $fields;		
						$json['message'] = array('type'=>'success');
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "currentpage":
					$json['title'] = (string)$currentpage->title();
					$json['modified'] = (string)$currentpage->modified();
					$json['isWritable'] = (string)$currentpage->isWritable();
					$json['isInvisible'] = (string)$currentpage->isInvisible();
					$json['isVisible'] = (string)$currentpage->isVisible();
					$json['isDeletable'] = (string)$currentpage->isDeletable();
					$json['num'] = (string)$currentpage->num();
					$json['templateData'] = $currentpage->templateData();
					$children = array();
					foreach ($currentpage->children() as $child){
						$children[] = array(
							'title'=>(string)$child->title(), 
							'uri' =>(string)$child->uri(),
							'uid' =>(string)$child->uid(),
							'modified'=>(string)$child->modified('d/m/Y'),
							'num'=>(string)$child->num(),
							'isVisible'=>(string)$child->isVisible(),
							'isDeletable'=>(string)$child->isDeletable()
						);
					}
					$json['children'] = $children;
					$json['parent'] = (string)$currentpage->parent()->uri();
					$parents = array();
					foreach ($currentpage->parents() as $parent){
						$parents[]= (string)$parent->uri();
					}
					$json['parents'] = $parents;
					$json['uid'] = (string)$currentpage->uid();
					$json['uri'] = (string)$currentpage->uri(get('firby-language'));
					$json['url'] = (string)$currentpage->url();
					$json['contentURL'] = (string)$currentpage->contentURL();
					$blueprints = data::read(kirby::instance()->roots()->blueprints().DS.$currentpage->template().'.php', 'yaml');
					$fields = array();
					foreach ($blueprints['fields'] as $key=>$value) {
						if($value['type'] == 'structure'){
							$subfields = array();
							foreach($value['fields'] as $subkey=>$subfield){
								$subfields[$subkey] = $subfield;
							}
							$entry = $value['entry'];
							$subblueprints = array(
								'label'=>$value['label'],
								'type'=>$value['type'],
								'entry'=>$entry,
								'fields'=>$subfields,
							);
							$content = array();
							foreach($currentpage->$key()->yaml() as $subvalue){
								$content[] =  array(
									'key'=>$key,
									'blueprint'=>$subblueprints,
									'content'=>$subvalue,
								);
							}
						}else{
							$content = (string)$currentpage->content(get('firby-language'))->$key();
						}
						$fields[] = array(
						'key'=>$key,
						'blueprint'=>$value,
						'content'=>$content
						);
					};
					$json['content'] = $fields;
					$files = array();
					foreach($currentpage->files() as $file){
						$files[] = array(
							'name'=>(string)$file->name(),
							'mime'=>(string)$file->mime(),
							'type'=>(string)$file->type(),
							'extension'=>(string)$file->extension(),
							'size'=>(string)$file->size(),
							'modified'=>(string)$file->modified('d/m/Y'),
							'filename'=>(string)$file->filename(),
							'dir'=>(string)$file->dir(),
							'url'=>(string)$file->url()
						);
					}
					$json['files'] = $files;
					break;
				case "updatemainsite":
					if(get('languagecode')){
						$languagecode = get('languagecode');
					}else{
						$languagecode = '';
					}
					try {
						$blueprints = data::read(kirby::instance()->roots()->blueprints().DS.'site.php', 'yaml');
						foreach($_POST as $key => $value) {
							if($key!='firby-username' && $key!='firby-password' &&  $key!='firby-type' && $key!='firby-uri' && $key!='languagecode'){
								if(is_array($value)){
									if($blueprints['fields'][$key]['type'] == 'checkboxes'){
										$newvalue = join(', ', $value);
										site()->update(array($key=>$newvalue),$languagecode);
									}
									if($blueprints['fields'][$key]['type'] == 'structure'){
										site()->update(array($key=>yaml::encode($value)),$languagecode);
									}
								}else{
									site()->update(array($key=>$value),$languagecode);
								}
							}
						}
 					 	$json['message'] = array('type'=>'success');
						$json['data'] = array();
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;	
				case "updatepage":
					if(get('languagecode')){
						$languagecode = get('languagecode');
					}else{
						$languagecode = '';
					}
					try {
						$blueprints = data::read(kirby::instance()->roots()->blueprints().DS.$currentpage->template().'.php', 'yaml');
						foreach($_POST as $key => $value) {
							if($key!='firby-username' && $key!='firby-password' &&  $key!='firby-type' && $key!='firby-uri' && $key!='languagecode'){
								if(is_array($value)){
									if($blueprints['fields'][$key]['type'] == 'checkboxes' || $blueprints['fields'][$key]['type'] == 'selector' || $blueprints['fields'][$key]['type'] ==  'multiselect'){
										$newvalue = join(', ', $value);
										$currentpage->update(array($key=>$newvalue),$languagecode);
									}
									if($blueprints['fields'][$key]['type'] == 'structure'){
										$currentpage->update(array($key=>yaml::encode($value)),$languagecode);
									}
								}else{
									$currentpage->update(array($key=>$value),$languagecode);
								}
							}
						}
 					 	$json['message'] = array('type'=>'success');
						$json['data'] = array('uri'=>get('firby-uri'),'title'=>(string)$currentpage->title());
						$currentuser->update(array('history'=>changeuserhistory($currentuser,get('firby-uri'))));
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					//$currentpage->move($uid); /*change the url(and folder)name bsp from hose3 to hose*/
					break;
				case "newpage":
					try {
						$template = get('template');
						$uri = get('firby-uri').'/'.get('uid');
						$data = array(
							'title'=> get('title')
						);
						site()->create($uri,$template,$data);
						$newpage = page($uri);
						$json['newchild'] =  array(
							'title'=>(string)$newpage->title(),
							'uri' =>(string)$newpage->uri(),
							'uid' =>(string)$newpage->uid(),
							'modified'=>(string)$newpage->modified('d/m/Y'),
							'num'=>'new',
							'isVisible'=>0,
							'isDeletable'=>(string)$newpage->isDeletable()
						);
						$json['message'] = array('type'=>'success');
						$currentuser->update(array('history'=>changeuserhistory($currentuser,$uri)));	
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;	
				case "editpage":
					try{
						if(get('uid')!='' && get('title')!='') {
							if(get('uid')!= $currentpage->uid()){
								$currentpage->move(get('uid'));
							}
							if(get('title')!= $currentpage->title()){
								$currentpage->update(array('title'=>get('title')));
							}
							$json['data'] =  array(
								'title'=>(string)$currentpage->title(),
								'uri' =>(string)$currentpage->uri(),
								'uid' =>(string)$currentpage->uid(),
								'modified'=>(string)$currentpage->modified('d/m/Y'),
							);
							$json['message'] = array('type'=>'success');
							$currentuser->update(array('history'=>changeuserhistory($currentuser,get('firby-uri'))));
						}else{
							throw new Exception();
						}
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}	
					break;
				case "deletepage":
					if($currentpage->isHomePage()){
						$json['message'] = array('type'=>'error','subject'=>'error-home');
					}else if($currentpage->isErrorPage()){
						$json['message'] = array('type'=>'error','subject'=>'error-error');
					}else if($currentpage->hasChildren()){
						$json['message'] = array('type'=>'error','subject'=>'error-children');
					}else {
						try {
							$parent = $currentpage->parent();
							$currentpage->delete();
							$count = 1;
							foreach ($parent->children()->visible() as $page){
								$page->sort($count);
								$count++;
							}
 					 		$json['message'] = array('type'=>'success');
						} catch(Exception $e) {
							$json['message'] = array('type'=>'error');
						}
					}
					break;
				case "hideshow":
					try{
						$sortnumber = get('firby-sortnumber');
						if($currentpage->isVisible()){
							$currentpage->hide();
							$count = 1;
							foreach ($currentpage->parent()->children()->visible() as $child){
								$child->sort($count);
								$count++;
							}
							$json['message'] = array('type'=>'success','status'=>'hide');
						}else {
							$currentpage->sort($sortnumber);
							$json['message'] = array('type'=>'success','status'=>'show');
						}
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "createuser":
					try {
  						$user = site()->users()->create(array(
    						'username' => get('username'),
							'firstname' => '',
							'lastname' => '',
    						'email' => get('email'),
    						'password' => get('password'),
    						'role' => get('role'),
    						'language' => get('language')
  						));
  						$json['message'] = array('type'=>'success');
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "deleteuser":
					try {
  						site()->user(get('deleteuser'))->delete();
 					 	$json['message'] = array('type'=>'success');
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "changeimage":
					try{
						/*$upload = upload(__DIR__ . DS . '{{safeName}}.jpg');*/
						$encoded = get('imagedata');
						$imgfile = $currentpage->root().'/'.get('filename');/*$currentpage->file(get('filename'))->url()*/
       					 // convert the image data from base64
        				$imgData = base64_decode(stripslashes(substr($encoded, 22)));
						$fp = fopen($imgfile, 'w+');
 						fwrite($fp, $imgData);
 						fclose($fp);
						$json['message'] = array('type'=>'success');
					} catch(Exception $e){
						$json['message'] = array('type'=>'error');
					}
					break;
				case "imageupload":
					if (get('imagedata') && get('imagepath')) {
						try{
							/*$upload = upload(__DIR__ . DS . '{{safeName}}.jpg');*/
							$encoded = get('imagedata');
							$imgfile = get('imagepath');
       						 // convert the image data from base64
        					$imgData = base64_decode(stripslashes(substr($encoded, 22)));
							$fp = fopen($imgfile, 'w+');
 							fwrite($fp, $imgData);
 							fclose($fp);
							$json['message'] = array('type'=>'success');
						} catch(Exception $e){
							$json['message'] = array('type'=>'error');
						}
					}
					break;
				case "fileupload":
					$filepath = $currentpage->root().'/'.$_FILES['file']['name'];
					$filename = $_FILES['file']['tmp_name'];
					try{
						move_uploaded_file($filename, $filepath);
						$currentpage->reset();
						$file = $currentpage->file($_FILES['file']['name']);
						if(get('newfilename') !='' && get('newfilename')!= $filename){
							$file->rename(get('newfilename',true));
						}
						$json['file'] = array(
							'name'=>$file->name(),
							'mime'=>(string)$file->mime(),
							'type'=>(string)$file->type(),
							'extension'=>(string)$file->extension(),
							'size'=>(string)$file->size(),
							'modified'=>(string)$file->modified('d/m/Y'),
							'filename'=>(string)$file->filename(),
							'dir'=>(string)$file->dir(),
							'url'=>(string)$file->url()
						);
						$json['data']=  array('uri'=>(string)$currentpage->uri(),'title'=>(string)$currentpage->title());
						$json['message'] = array('type'=>'success');
						$currentuser->update(array('history'=>changeuserhistory($currentuser,get('firby-uri'))));
					} catch(Exception $e){
						$json['message'] = array('type'=>'error');
					}
					break;
				case "deletefile":
					$file = $currentpage->file(get('firby-filename'));
					try {
  						$file->delete();
						$json['data']=  array('uri'=>(string)$currentpage->uri(),'title'=>(string)$currentpage->title());
						$json['message'] = array('type'=>'success');
						$currentuser->update(array('history'=>changeuserhistory($currentuser,get('firby-uri'))));
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "editfile":
					$file = $currentpage->file(get('firby-filename'));
					try {
						if($file->exists() && get('newname')!= ''){
							$file->rename(get('newname'));
							$currentpage->reset();
							$newfile = $currentpage->file(get('newname').'.'.$file->extension());
							if($newfile->exists()){
								$json['message'] = array('type'=>'success');
								$json['data'] = array(
									'name'=>(string)$newfile->name(),
									'filename'=>(string)$newfile->filename(),
									'url'=>(string)$newfile->url()
 								);
							}else{
								throw new Exception();
							}
						}else{
							throw new Exception();
						}
					} catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "sortorder":
					try {	
						$i='';
						for($i=0;$i<get('uri-counter');$i++){
							page(get('sort-uri'.$i))->sort($i+1);
						}
 					 	$json['message'] = array('type'=>'success');
					} 
					catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "userlist":
					try{
						$users = array();
						foreach(site()->users() as $singeluser){
                    		if($singeluser->avatar()){
								$imageData = base64_encode(file_get_contents($singeluser->avatarRoot()));
								$type = pathinfo($singeluser->avatar(), PATHINFO_EXTENSION);
								$avatar_base64 = 'data:image/'.$type.';base64,'.$imageData;
								$avatar_url = $singeluser->avatar()->url();
							}else {
								$avatar_base64 ='';
							}
							if($singeluser->email()!= '' && $singeluser->username()!= ''){
								$users[] = array(
									'firstName'=>(string)$singeluser->firstName(),
									'lastName'=>(string)$singeluser->lastName(),
									'username'=>(string)$singeluser->username(),
									'email'=>(string)$singeluser->email(),
									'role'=>(string)$singeluser->role(),
									'language'=>(string)$singeluser->language(),
									'avatar'=> $avatar_base64
								);
							}
						}
						$json['users'] = $users;
						$json['message'] = array('type'=>'success');	
					} 
					catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;	
				case "updateuser":
					try{
						$updateuser = site()->user(get('updateuser'));
						foreach($_POST as $key => $value) {
							if($key!='firby-username' && $key!='firby-password' &&  $key!='firby-type' && $key!='updateuser'){
								if($key == 'password' && $value==''){
								}else{
									$updateuser->update(array($key=>$value));
								}	
							}
						}
 					 	$json['message'] = array('type'=>'success');	
					} 
					catch(Exception $e) {
						$json['message'] = array('type'=>'error');
					}
					break;
				case "userinfo":
					$user = site()->user(get('info-username'));
						if($user->email()!= '' && $user->username()!= ''){
							$userinfo = array(
								'firstName'=>(string)$user->firstName(),
								'lastName'=>(string)$user->lastName(),
								'username'=>(string)$user->username(),
								'email'=>(string)$user->email(),
								'role'=>(string)$user->role(),
								'language'=>(string)$user->language(),
							);
						}
					$json['user'] = $userinfo;
					break;
				case "getimagedata":
					/*$imageData = base64_encode(file_get_contents($currentuser->avatarRoot()));
						$type = pathinfo($avatar, PATHINFO_EXTENSION);
						$avatar_base64 = 'data:image/'.$type.';base64,'.$imageData;*/	
						$currentfile = $currentpage->file(get('filename'));
					$json['imagedata'] = 'data:'.$currentfile->type().'/'.$currentfile->extension().';base64,'.$currentfile->base64();
					$json['message'] = array('type'=>'success');
					break;
				default:
					$json['message'] = array(
						'type'=>'error',
						'subject'=>'wrongtype',
						'message'=>'Type value not exist or is wrong'
					);
			}// end switch -> type
		}else {
			$json['message'] = array(
				'type'=>'noPanelAccess',
				'subject'=>'noPanelAccess',
			);
		}// end check Panel Access
		$user->logout();
	}else {
		$json['message'] = array(
			'type'=>'noUser',
			'subject'=>'errorLogin',
		);
	}// ende login
	echo json_encode($json);
	exit;
}
?>