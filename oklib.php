<?php

	$CONNECTIONS_COUNT = 0;
	
	$counter = 0;
	
	function debug($text)
	{
		echo "[DBG][".time()."] ".$text."\n";
	}
	
	function parser_error($text)
	{
		echo "[ERR][".time()."] ".$text."\n";
	}
	
	function parser_die($text)
	{
		die("[DIE][".time()."] ".$text."\n");
	}
	
	//$id - number of proxy in the list
	function get_proxy($id)
	{
		return "";
		$proxies = array(
			"91.215.221.35:3128",
			"194.125.255.72:3128",
			"93.100.3.158:8080",
			"91.218.84.195:80",
			"212.5.106.24:3128",
			"80.85.145.207:8080",
			"212.109.1.45:8080",
			""
		);
		debug("use proxy:'".$proxies[(int)$id % count($proxies)]."'");
		return $proxies[(int)$id % count($proxies)];
	}
	
	//represent one account(connection) with odnoklassniki
	class OK
	{
		const BASE_URL = "http://www.odnoklassniki.ru";
		
		//Location of the parser
		const BASE_PATH = 'd:\\serg\\parser\\'; 
		const USER_AGENT = "Mozilla/5.0 (Windows; U; Windows NT 6.0; en; rv:1.9.0.1) Gecko/2008070208 Firefox/3.0.1";
		
		//if errors occur while it's sending a request to the server, we will retry sending of the request MAX_RETRY times
		const MAX_RETRY = 3;
		//Login of the account
		private $m_email;
		private $m_passwd;
		
		//some tokens,keys from html pages
		private $m_main_token;
		private $m_p_sId;
		private $m_requested;
		
		//account's profile (like 5345354343 for /profile/5345354343)
		private $my_profile_id;
		
		//is initialized (init() was called)
		public $ready;
		
		//numer of this account in pool
		public $_id;
		
		public function __construct($email, $passwd)
		{
			$this->m_email = $email;
			$this->m_passwd = $passwd;
			
			//each connection has it's own unique autoincrement(starts from 0) id
			global $CONNECTIONS_COUNT;
			$_id = $CONNECTIONS_COUNT++;
						
			$this->ready = FALSE;
		}
		
		//Post request to the server to get right block with information (to emulate a browser)
		private function _require_async_content()
		{
			debug("Try to get AsyncBlockContent");
			$url = OK::BASE_URL . '/?cmd=AsyncBlockContent&gwt.requested='.$this->m_requested.'&st.cmd=userMain&renderBlockId=FrOln4thCol&_fo4cl=1';
			$counter = OK::MAX_RETRY;
			
			while($counter-- > 0)
			{
				$page = $this->_get_page($url, OK::BASE_URL, 
				' ');
				
				if($page == "")
				{
					parser_error("Couldn't get async content, retry");
				}
				else
				{
					return;
				}
			}
			
			parser_die("Couldn't get async content, retry");
		}
		
		//Post request to get p_sId - an identifier that is required for many other requests
		private function _require_psid()
		{
			$this->_require_async_content();
			debug("Try to get p_sId");
			
			$url = 'http://www.odnoklassniki.ru/push?cmd=PeriodicManager&gwt.requested=' . $this->m_requested .'&st.cmd=userMain&p_sId=0';
			
			$counter = OK::MAX_RETRY;
			$ok = FALSE;
			while($counter-- > 0)
			{
				$page = $this->_get_page($url, OK::BASE_URL, 
				'tlb.act=news&&c.nlct=' . (int)microtime(TRUE). '915&c.nclct='.(int)microtime(TRUE).'914&c.ca=&tlb.act=news&cpLCT=0&cpD=&cpNMCD=&action=news&jsp.nct=0&jsp.act=0&v_action=news&f_action=f_news&tlb.act=news&tlb.act=news&blocks=TM,GCnf,TFC,FPush,MakeFriends,TD,TN,VC,FO,TP,TDP,&p_NLP=0');
			
				$r;
				if(!preg_match_all('/"p_sId":"(.+?)"/', $page, $r))
				{
					parser_error("Couldn't get p_sId");
				}
				else
				{
					$ok = TRUE;
					break;
				}
			}
			if(!$ok)
			{
				parser_die("Couldn't get p_sId:\n$page\n");
			}
			$this->m_p_sId = $r[1][0];
			debug("p_sId - ".$this->m_p_sId);
		}
		
		//Post request to get 'requested'/gwHash - an identifier that is required for many other requests
		private function init_requested($page)
		{
				debug("Try to get gwtHash");
				$m;
				$c = preg_match_all("/gwtHash:\"(.*?)\"/", $page, $m);
				if($c === FALSE)
				{
					parser_die("Couldn't find gwtHash");
				}
				$this->m_requested = $m[1][0];
				debug("gwtHash - ".$m[1][0]);
				return;
		}
		
		//Try to get the main page to check if we are logged in
		public function is_loged()
		{
			debug("Check if we are already logged in\n");
			$counter = OK::MAX_RETRY;
			$ok = FALSE;
			while($counter-- > 0)
			{
				//get main paige
				$page = $this->_get_page(OK::BASE_URL);
				
				//check if it has PopLayerViewFriendPhotoSticky it's our profile page else it's logon page
				if(strpos($page, 'PopLayerViewFriendPhotoSticky') !== FALSE)
				{
					debug("We are logged in");
					//init one token
					$this->init_requested($page);
					$groups;
					
					debug("Try to get my profile");
					if(!preg_match_all("/href=\"\/profile\/([0-9]+?)\?st.cmd=userMain/", $page, $groups))
					{
						parser_error("Couldn't get my profile url");
						continue;
					}
				
					$this->my_profile_id = $groups[1][0];
					return TRUE;
				}
				
				//if we are logged out get token from logon page
				debug("We are logged out");
				$pos = strpos($page, '<form ');
				if($pos === FALSE)
				{
					parser_error("Couldn't find token");
					continue;
				}
			
				$pos = strpos($page, 'tkn=', $pos);
			
				if($pos === FALSE)
				{
					parser_error("Couldn't find token");
					continue;
				}
				$ss = substr($page, $pos + 4, 4);
				$this->m_main_token = $ss;
				return FALSE;
			}
			
			parser_die("Couldn't find token");
		}
		
		//Try to login
		public function login()
		{
			debug("Try to login login");
			$LOGIN = $this->m_email;
			$PASSWORD = $this->m_passwd;
			$token = $this->m_main_token;
			
			debug("login-$LOGIN,password-$PASSWORD\n");
			$url_new = OK::BASE_URL . "/dk?cmd=AnonymLogin&st.cmd=anonymLogin&tkn=$token";
			
			$counter = OK::MAX_RETRY;
			$ok = FALSE;
			while($counter-- > 0)
			{
				//send login/password and some tokens
				$page = $this->_get_page($url_new, OK::BASE_URL, "st.redirect=&st.asr=&st.posted=set&st.email={$LOGIN}&st.password={$PASSWORD}&st.remember=off&st.fJS=disabled&st.st.screenSize=1280+x+1024&st.st.browserSize=432&st.st.flashVer=&button_go=%D0%92%D0%BE%D0%B9%D1%82%D0%B8");
				
				//try to find 'requested' token
				$this->init_requested($page);
				//send request to get p_sId token
				$this->_require_psid();
				$groups;
				//try to find account's profile
				if(!preg_match_all("/href=\"\/profile\/([0-9]+?)\"/", $page, $groups))
				{
					parser_error("Couldn't get my groups url");
					continue;
				}
				
				$this->my_profile_id = $groups[1][0];
				return;
			}
			
			parser_die("Couldn't get my profile url");
		}
		
		//Return array of account's groups like array("name" => "agnes.ru", "id" => "ab343434acfa3435acdda3345334")
		//id could change the value from session to session
		public function get_my_groups()
		{
			debug("Try to find my groups");
			$counter = OK::MAX_RETRY;
			$ok = FALSE;
			while($counter-- > 0)
			{
				$groups_url = OK::BASE_URL . "/profile/" . $this->my_profile_id . "/groups";
				$page = $this->_get_page($groups_url, OK::BASE_URL);
				$groups;
				if(!preg_match_all('/<a class="altGroupLink" href="\/group\/([0-9]+).*?groupId=(.+?);/', $page, $groups))
				{
					parser_error("Couldn't get my groups");
					continue;
				}
				
				$i = count($groups[1]);
				
				debug("Found $i groups");
				$result = array();
				while($i > 0)
				{
					$result[] = array("name" => $groups[1][$i - 1], "id" => str_replace('&amp', '', $groups[2][$i - 1]));
					print_r(array("name" => $groups[1][$i - 1], "id" => str_replace('&amp', '', $groups[2][$i - 1])));
					$i--;
				}
				
				return $result;
			}
			parser_die("Couldn't get my profile url");
		}
		
		//Return group id by group name (for this account's groups only)
		public function get_my_group_id($name)
		{
			debug("Try to find group name:$name");
			$groups = $this->get_my_groups();
			foreach($groups as $g)
			{
				if($g["name"] == $name)
				{
					debug("Found");
					return $g["id"];
				}
			}
			
			debug("Couldn't find");
			return FALSE;
		}
		
		//Start to parse $source group and invite one user to $target group
		//DO NOT USE this function, it's for future purpose and has a bug
		public function parse_and_invite_one_user($source, $target)
		{
			debug("Start parsing and inviting from group ".implode(',', $source). " to " .  implode(',', $target) . " one user");
			$all_users = array();
			$file_name = "group_{$source['name']}_invited_users.csv";
			if(file_exists($file_name))
			{
				$all_users = $this->load_users($file_name);
			}
			
			
		}
		
		//Start to parse group $source and invite it to $target, invited users are stored in group_{$source['name']}_invited_users.csv"
		//to avoid double invitations in the future
		//$limit - maximum number of invitations (stop after limit reaching)
		public function parse_and_invite_my_group($source, $target, $limit = 5)
		{ 
			debug("For account " . $this->my_profile_id);
			debug("Start parsing and inviting from group ".implode(',', $source). " to " .  implode(',', $target));
			
			//already invited users
			$all_users = array();
			
			//save invited users to this file
			$file_name = "group_{$source['name']}_invited_users.csv";
			
			//start parsing from this page
			$page = 1;
			//unique number, that determinates our results (members on pages)
			$loader = '4568344390' + $this->_id;
			while($limit > 0)
			{
				debug("Next turn");
				if(file_exists($file_name))
				{
					$all_users = $this->load_users($file_name);
				}
				if($page == 1)
				{
					debug("Parse first page");
					$users = $this->parse_group_members($source, $all_users);
				}
				else
				{
					debug("Parse {$page} page");
					$users = $this->parse_group_members_next($source, $loader, $page);
				}
				
				//if there are no users stop parsing
				if($users == FALSE || count($users) == 0)
				{
					debug("No users in group {$source['name']} on page $page for user-{$this->m_email}");
					break;
				}
				$page++;
				
				debug("Try to find new users, all users - ".count($users));
				
				//go throw users
				foreach($users as $u)
				{
					//if we has the user $u in our file and it has a field invited=1, than skip this user
					if(array_key_exists($u["profile"], $all_users))
					{
						debug("User {$u["profile"]} already in file");
						if($all_users[$u["profile"]]["invited"] == 1)
						{
							debug("Skip");
							continue;
						}
					}
					
					
					debug("Save users");
					
					//add new user to the array
					$u["invited"] = 1;
					$all_users[$u["profile"]] = $u;
					
					//invite the user to the group
					$rslt = $this->invite_user_to_group($source, $target, $u);
					
					//if invitation is restricted (we've got the limit)
					if($rslt == "FORBIDEN")
					{
						//0 - invitation is restricted
						return 0;
					}
					
					//if this user doesn't accept invitations - skip
					if($rslt == "LOCKED")
					{
						continue;
					}
					
					//Save the invited users
					$this->save_users($file_name, $all_users);
					
					//check if we've got our limit (we still could invite, odnoklassniki's limit isn't reached) 
					if($limit-- == 0)
					{
						//1 - our limit
						debug("Limit for this turn");
						return 1;
					}
					//return 0;
				}
			}
			
			return 1;
		}
		
		public function get_first_not_invited($group, $all_users)
		{
		}
		
		//parse and return group members, $all_users is not used yet (first page)
		public function parse_group_members($group, &$all_users)
		{
			debug("Parse group(".implode(',', $group).") members");
			$url = OK::BASE_URL . "/group/" . $group["name"] . "/members";
			$counter = OK::MAX_RETRY;
			$ok = FALSE;
			while($counter-- > 0)
			{	
				$page = $this->_get_page($url, OK::BASE_URL . "/profile/" . $this->my_profile_id . "/groups");
				$users;//[^"<>\?]
					
				//try to find user's name,profile and friendId
				if(!preg_match_all('/<a href="(\/profile[^"]*?)\?st\.cmd=friendMain&amp;st\.friendId=([^&;]*?)&amp;st\._aid=GroupMembers_VisitMember" class="o">([^<]*?)<\/a>/', $page, $users))
				{
					parser_error("Couldn't get users");
					continue;
				}
				$i = count($users[1]);
				$result = array();
				
				//create an array of users (like array( array("name"=>"", "profile"=>"", "friendId" => "", "invited" => 0), 
				//										array("name"=>"", "profile"=>"", "friendId" => "", "invited" => 0), ... )
				while($i > 0)
				{
					$line = array("profile" => $users[1][$i - 1], "friendId" => $users[2][$i - 1], "name" => $users[3][$i - 1], "invited" => 0);
					
					$result[] = $line;
					print_r(array("profile" => $users[1][$i - 1], "friendId" => $users[2][$i - 1], "name" => $users[3][$i - 1]));
					$i--;
				}
				
				debug("Found " . count($result) . " users");
				return $result;
			}
			
			parser_die("Couldn't get users");
		}
		
		//Save users to file (csv)
		public function save_users($file, $users)
		{
			debug("Save users to {$file}");
			$d = array();
			foreach($users as $key => $value)
			{
				$d[] = $value;
			}
			
			write_csv($file, $d, array_keys($d[0]));
		}
		
		//Load users from file (csv)
		static function load_users($file)
		{
			debug("Load users from {$file}");
			$d = read_csv($file);
			$result = array();
			foreach($d as $user)
			{
				$result[$user['profile']] = $user;
			}
			
			return $result;
		}
		
		//parse and return group members, $loader is a number to identify this viewing
		//$page - number of the required page (not for the first page)
		public function parse_group_members_next($group, $loader, $page)
		{
			debug("Parse group(".implode(',', $group).") members, loader-$loader,page-$page");
			$url = OK::BASE_URL . '/'.$group["name"].'/members?cmd=GroupMembersResultsBlock&gwt.requested=' . $this->m_requested . 
				'&st.cmd=altGroupMembers&st.directLink=on&st.referenceName='.$group["name"].'&st.groupId='.$group["id"].'&';
			$post = '&fetch=true&st.page='.$page.'&st.loaderid='.$loader;
			
			$counter = OK::MAX_RETRY;
			$ok = FALSE;
			while($counter-- > 0)
			{	
				$page = $this->_get_page($url, OK::BASE_URL . OK::BASE_URL . '/'.$group["name"].'/members' . $this->my_profile_id . "/groups", $post);
				if($page == "")
				{
					return FALSE;
				}
				else
				{
					$users;//[^"<>\?]
					
					if(!preg_match_all('/<a href="(\/profile[^"]*?)\?st\.cmd=friendMain&amp;st\.friendId=([^&;]*?)&amp;st\._aid=GroupMembers_VisitMember" class="o">([^<]*?)<\/a>/', $page, $users))
					{
						parser_error("Couldn't get users");
						continue;
					}
					$i = count($users[1]);
					$result = array();
					
					while($i > 0)
					{
						$b = 0;
						$line = array("profile" => $users[1][$i - 1], "friendId" => $users[2][$i - 1], "name" => $users[3][$i - 1], "invited" => 0);
						$result[] = $line;
						//$result[] = array("profile" => $users[1][$i - 1], "friendId" => $users[2][$i - 1], "name" => $users[3][$i - 1], "invited" => 0);
						print_r(array("profile" => $users[1][$i - 1], "friendId" => $users[2][$i - 1], "name" => $users[3][$i - 1]));
						$i--;
					}
					
					return $result;
				}
			}
			
			parser_die("Couldn't get users");
		}
		
		//Parse users from all the group's pages and save it to group_{$group["name"]}_members.csv
		public function parse_all_group_members($group)
		{
			$result = array();
			$init = $this->parse_group_members($group);
			
			foreach($init as $i)
			{
					$result[$i["profile"]] = $i;
			}
			
			$loader = '4568344390';
			
			$page = 2;
			while(($temp=$this->parse_group_members_next($group, $loader, $page++)) !== FALSE && $page < 10000)
			{
				foreach($temp as $i)
				{
						$result[$i["profile"]] = $i;
				}
				$this->save_users("group_{$group["name"]}_members.csv", $result);
				usleep(1000000);
			}
			
			return $result;
		}
		
		//Load a html page
		protected function _get_page($url, $refer = "", $post = "")
		{
			if($refer == "")
			{
					$refer = $url;
			}
			debug("Get page\n\t url - '$url',\n\t refer-'$refer'\n\t post - '$post'");
			
			$proxy = get_proxy($this->_id);
			if($proxy == "")
			{
				$proxy = null;
			}
			$ch = curl_init($url);
			curl_setopt ($ch, CURLOPT_USERAGENT, OK::USER_AGENT); 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_ENCODING,'gzip,deflate');
			curl_setopt($ch, CURLOPT_REFERER, $refer);
			curl_setopt($ch, CURLOPT_PROXY, $proxy);
			if($post != "")
			{
					if($post == " ")
					{
						$post = "";
					}

					curl_setopt($ch, CURLOPT_POST, 1);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
			}
			//curl_setopt($ch, CURLOPT_COOKIESESSION, TRUE);
			curl_setopt($ch, CURLOPT_COOKIEFILE, OK::BASE_PATH."cookiefile_".$this->m_email);
			curl_setopt($ch, CURLOPT_COOKIEJAR, OK::BASE_PATH."cookiefile_".$this->m_email);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION,true);

			$content = curl_exec($ch);
			
			curl_close($ch);
			global $counter;
			
			//You could comment this saving,it's store all the servers responses to html files
			$f=fopen("result_{$counter}.html", "w");
			fwrite($f, $url."\n");
			fwrite($f, $post."\n");
			fwrite($f, $content);
			fclose($f);
			$counter++;
			usleep(300000);
			return $content;
		}
		
		//Init connection
		public function init()
		{
			if(!$this->is_loged())
			{
				$this->login();
			}
			
			$this->_require_psid();
			$this->ready = TRUE;
		}
		
		//Inite user's that was parsed from group $source_group to $target_group ($limit - maximum number of invitations)
		function invite_users($source_group, $target_group, $users, $limit=5)
		{
			//go throw the users and invite them
			//save invited users to group_{$source_group["name"]}_members.csv
			//skip if user doesn't accept invitations (LOCKED)
			//stop if we'be got the limit (FORBIDEN) 
			foreach($users as $profile=> &$user)
			{
				if(--$limit == 0)
				{
					break;
				}
				
				//already invited user
				if($user['invited'] == 1)
				{
					continue;
				}
				
				$rslt = $this->invite_user_to_group($source_group, $target_group, $user);
				if($rslt == "FORBIDEN")
				{
					return;
				}
				
				if($rslt == "LOCKED")
				{
					continue;
				}
				//mark user as invited and save all users
				$user["invited"] = 1;
				$this->save_users("group_{$source_group["name"]}_members.csv", $users);
			}
		}
		
		//Invite user $user from group $source_group to $target_group
		function invite_user_to_group($source_group, $target_group, $user)
		{
			debug("Invite user - '".implode(',', $user).".', from group-'".implode(',',$source_group)."', to - '" . implode(',', $target_group));
			
			//if we doesn't set required params return FAIL
			if(!$source_group || !$target_group || !$user)
			{
				parser_error("Invite user:Wrong params");
				return "FAIL";
			}
			
			//post request to get invite window
			$url = OK::BASE_URL.'/group/' . $source_group["name"] . 
				'/members?cmd=PopLayer&st.cmd=altGroupMembers&st.directLink=on&st.groupId='.
				$source_group["id"].'&st.layer.cmd=InviteUserToGroupsOuter&st.layer._bw=1280&st.layer._bh=443&st.layer.friendId='.
				$user["friendId"].'&st._aid=SM_AltGroup_Invite&gwt.requested='.$this->m_requested.'&p_sId='. $this->m_p_sId;
			
			debug("Try to get invite window");
			$page = $this->_get_page($url, OK::BASE_URL.'/group/' . $source_group["name"] . '/members', ' ');
			
			//We didn't get invited window - FAIL 
			if($page == "")
			{
				parser_error("Invite user:Couldn't get invite window");
				return "FAIL";
			}
			
			//if server respons doesn't contain gtg_invite_All - user doesn't accept invitations
			$b = strpos($page, "gtg_invite_All");
			
			if($b === FALSE)
			{
				parser_error("Invite user:user is locked");
				return "LOCKED";
			}
			
			$invite_url;
			if(!preg_match_all('/<form action="([^"]*?)"/', $page, $invite_url))
			{
				parser_error("Couldn't get invite url");
				return "FAIL";
			}
			
			debug("Try to invite user");
			$url = $invite_url[1][0];
			$url=str_replace('&amp;', '&', $url);
			
			$url = OK::BASE_URL . $url;
			$url = $url . '&gwt.requested=' . $this->m_requested . '&p_sId=' . $this->m_p_sId;
						
			$post = 'gwt.requested=' .  $this->m_requested . '&st.layer.posted=set&selid=' . $target_group["id"] .'&button_invite=clickOverGWT';
			
			$resp = $this->_get_page($url, OK::BASE_URL.'/group/' . $source_group["name"] . '/members', $post);
			debug("Response:'$resp'");
			
			//if response contains alreadymember - useralready in group, skip, process the same way if user doesn't accept invitations
			if(strpos($resp, "alreadymember") !== FALSE)
			{
				return "LOCKED";
			}
			
			//for all other errors act as we have the limit (invitation is temporary restricted)
			if(strpos($resp, "error") !== FALSE)
			{
				return "FORBIDEN";
			}
			
			
			sleep(2);
			
			return "OK";
		}
		
		//get user (from the array with name key and profile key) 
		function get_user_by_profile($profile, $users)
		{
			debug("Try to find the user with profile-'$profile'");
			foreach($users as $u)
			{
				if($u["profile"] == $profile)
				{
					debug("Found");
					return $u;
				}
			}
			debug("Couldn't find");
			return FALSE;
		}
		
		//get group (from the array with name key and id key)
		function get_group_by_name($name, $groups)
		{
			debug("Try to find the group with name-'$name'");
			foreach($groups as $g)
			{
				if($g["name"] == $name)
				{
					debug("Found");
					return $g;
				}
			}
			
			debug("Couldn't find");
			return FALSE;
		}
		
		//Return an user (array with an profile key and friendId key - it's unique for account and could be changed from session to session)
		function create_user_by_profile($p)
		{
			//"href="/profile/517769404029?st.cmd=friendMain&amp;st.friendId=ocywxzhffwgmwtqmw0qjzfbgkrmcrfbayft&amp";
			debug("Find user with profile '$p'");
			$page = $this->_get_page(OK::BASE_URL . $p);
			$id;
			
			if(!preg_match_all('/id="action_menu_write_message_a" href="\/profile\/\d*\?st\.cmd=friendMain&amp;st\.friendId=(.+?)&/', $page, $id))
			{
				parser_error("Couldn't find link with friendId");
				return FALSE;
			}
			
			print_r($id);
			
			$friend_id = $id[1][0];
			debug("friendId - $friend_id");
			return array("friendId" => $friend_id, "profile" => $p);
		}
			
		//Find information about the group by it's name and returns an array for this group with keys:name and id that is unique for account and could be changed from session to session 
		function find_group($name)
		{
			debug("Try to find group name:'$name'");
			$url = OK::BASE_URL.'/group/' . $name . '/members';
			
			//<a class="mctc_navMenuSec mctc_navMenuActiveSec" href="/bestautoru/members" hrefattrs="st.cmd=altGroupMembers&st.directLink=on&st.referenceName=bestautoru&st.groupId=coqjdxqrenyyntetjzo0rdynqaszhnjaeofwik&st._aid=NavMenu_AltGroup_Members">
			$counter = OK::MAX_RETRY;
			$ok = FALSE;
			while($counter-- > 0)
			{
				$page = $this->_get_page($url, OK::BASE_URL);
				if($page == "")
				{
					parser_error("Couldn't find group:'$name', retry");
					continue;
				}
				
				$groups;
				
				if(!preg_match_all('/st\.groupId=(.*?)&/', $page, $groups))
				{
					parse_error("Couldn't find group");
					continue;
				}
				else
				{
					if(count($groups < 1) && count($groups[1]) < 1)
					{
						continue;
					}
					return array("name" => $name, "id" => $groups[1][0]);
				}
			}
			
			parser_die("Couldn't get members of group:'$name'");
		}
		
		//Join to $group
		function join_to_group($group)
		{
			//.'&p_sId='. 
			$url = 'http://www.odnoklassniki.ru/group/'.$group["name"].'?cmd=LeftColumnTopCardAltGroup&st.cmd=altGroupMain&st.groupId='.
			$group["id"].'&st.altGroup.action=groupJoin&st._aid=AltGroupTopCardButtonsJoin&gwt.requested='.$this->m_requested.
			'&p_sId='.$this->m_p_sId;
			
			$page = $this->_get_page($url, OK::BASE_URL,  ' ');
			
			if($page == "")
			{
				parser_error("Couldn't join to group " . $group['name'] . " (login:".$this->m_email.")");
				return FALSE;
			}
			
			return TRUE;
		}
		
		//Send a message $msg from this account to the user with a profile - $profile
		function send_message_to_user($profile, $msg)
		{
			debug("Send to user - $profile, message - '$msg'");
			$page = $this->_get_page(OK::BASE_URL . $profile);
			$id;
			
			if(!preg_match_all('/id="action_menu_write_message_a" href="\/profile\/\d*\?st\.cmd=friendMain&amp;st\.friendId=(.+?)&/', $page, $id))
			{
				parser_error("Couldn't url to send message");
				return;
			}
			
			print_r($id);
			
			$friend_id = $id[1][0];
			debug("friendId - $friend_id");
			
			$url = OK::BASE_URL . $profile .'?cmd=ToolbarMessages&gwt.requested='
			.$this->m_requested.'&st.cmd=friendMain&st.friendId='.$friend_id.'&p_sId='.$this->m_p_sId;
			$convertedText = mb_convert_encoding($msg, 'utf-8', 'cp1251');
			$pst = 'tlb.act=act.send.msg&d.fr.id='.$friend_id.'&d.msg=%3Cp%3E'.rawurlencode($convertedText).'%3C%2Fp%3E&d.dleot='. (int)microtime(TRUE). '915&refId=msg-send-comment-'.((int)microtime(TRUE) - 30).'837';
			
			$page = $this->_get_page($url, OK::BASE_URL . $profile, $pst);
			print_r($page);
		}
	}
	
	//return an array with login/password for all the users in the file $file_name
	function get_users_login_password($file_name)
	{
			$data = read_csv($file_name);
			return $data;
	}
	
	//Check if this account valid (if we could get logged in - account is valid)
	function validate_users($users)
	{
		debug("");
		$result = array();
		foreach($users as $u)
		{
			$o = new OK($u['login'], $u['password']);
			$o->init();
			if(!$o->is_loged())
			{
				$result[] = $u;
			}
		}
		
		return $result;
	}
	
	//users from $users array joins to group
	function users_join_to_group($users)
	{
		$b = 0;
		foreach($users as $u)
		{
			if($b == 0)
			{
				$b++;
				continue;
			}
			$o = new OK($u['login'], $u['password']);
			$o->init();
			$group = $o->find_group("chudogroup");
			$o->join_to_group($group);
		}
	}
	
	//send message for users $users from $connections pull
	function send_message_to_users($connections, $users)
	{
		$index = 0;
		/*$used = array();
		foreach($connections as $c)
		{
			$counter=10;
			while(--$counter > 0)
			{
				if(!$c->ready)
				{
					debug("Init");
					$c->init();
				}
				$c->send_message_to_user($users[$index++]["profile"], 'Новый интернет магазин нижнего белья http://www.agnes.ru (Скидка на весь ассортимент 10% - введите промокод PL1209)');
			}
		}*/
		
		foreach($users as $profile=>$value)
		{
			$connections[($index++) % count($connections)]->send_message_to_user(
				$profile,
				'Новый интернет магазин нижнего белья http://www.agnes.ru (Скидка на весь ассортимент 10% - введите промокод PL1209)'
			);
			if($index > 530)
			{
				break;
			}
		}
	}
	
	//Call $calback for all the acounst in $pool
	function for_all_acs($pull, $callback, &$param)
	{
		foreach($pull as $c)
		{
			if(!$c->ready)
			{
				$c->init();
			}
			
			if($callback($c, $param) === FALSE)
			{
				return FALSE;
			}
		}
		
		return TRUE;
	}
	//Call $calback for $n th acount in $pool
	function for_nth_acc($pull, $callback, $n, &$param)
	{
		$counter = 0;
		foreach($pull as $c)
		{
			
			if($counter++ == $n)
			{
				if(!$c->ready)
				{
					$c->init();
				}
				return $callback($c, $param);
			}
		}
	}
	
	//create an accounts pool (an array with connections for all the account)
	function create_accounts_pull($file_name)
	{
		$users = get_users_login_password($file_name);
		
		if(count($users) == 0)
		{
			parser_die("No users in $file_name");
		}
		
		foreach($users as $u)
		{
			$o = new OK($u['login'], $u['password']);
			$pull[] = $o;
		}
		
		return $pull;
	}
?>