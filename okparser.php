<?php

ini_set('display_errors',1);
include('csv_utils.php');
include('oklib.php');


/*$users = get_users_login_password('users1.csv');
users_join_to_group($users);
die("1");*/
/*$b = strpos($str, "Пользователь не принимает приглашения в группы");
if($b !== FALSE)
			{
				parser_error("Invite user:user is locked");
				return "LOCKED";
			}
die("--");*/
$g_counter = 0;
while(1)
{
debug("START");

//create a pull with account that was loaded from the file
$pull = create_accounts_pull('users1.csv');
$param = 0;

//For all accounts in the pool
//	Get source group and target group and invite users from the source to the target
for_all_acs($pull, function($c, &$param)
					{
						global $g_counter;
						$source_group = $c->find_group("nizhneebe");
						$target_group = $c->find_group("chudogroup");
						if($g_counter++ > 2)
						{
							echo("--");
							$g_counter = 0;
							return FALSE;
						}
						
						//1 - is count of invitations for one account
						if($c->parse_and_invite_my_group($source_group, $target_group, 1) == 0)
						{
							debug("LOCKED");
							return FALSE;
						}
						$param++;
						return TRUE;
					}, $param
			);
debug("Invited - $param users");
/*$param = '/profile/552923536713';
$rslt = for_nth_acc($pull, function($c, &$profile)
					{
						
						
						$user = $c->create_user_by_profile($profile);
						if($user === FALSE)
						{
							return "FAIL";
						}
						
						$source_group = $c->find_group("53845920907273");
						$target_group = $c->find_group("51131281768586");
						
						$rslt = $c->invite_user_to_group($source_group, $target_group, $user);
						print_r($rslt);
						
						return $rslt;
					},0, $param
			);
			
debug($rslt);
*/
debug("END");
sleep(60*20);
}
?>