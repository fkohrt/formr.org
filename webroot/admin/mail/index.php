<?php
require_once '../../../define_root.php';
require_once INCLUDE_ROOT . "View/admin_header.php";

require_once INCLUDE_ROOT . "Model/EmailAccount.php";

if(!empty($_POST) AND isset($_POST['create'])) {
	$acc = new EmailAccount($fdb, null, $user->id);
	if( 
		$acc->create()
	)
	{
		alert('<strong>Success!</strong> You added a new email account!','alert-success');
		redirect_to("admin/mail/edit/?account_id=".$acc->id);
	}
	else {
		alert(implode($acc->errors),'alert-error');
	}
}

require_once INCLUDE_ROOT . "View/header.php";
require_once INCLUDE_ROOT . "View/acp_nav.php";

?>
<form id="list_email_accounts" name="list_email_accounts" method="post" action="<?=WEBROOT?>admin/mail/">
<div class="span5">
<h3>Email accounts</h3>
<ul class="nav nav-pills nav-stacked">
	<li>
		<input class="btn btn-info" name="create" type="submit" value="<?php echo _("Create new account"); ?>">
	</li>
<?php
$accs = $user->getEmailAccounts();
if($accs) {
  echo '
	  ';
  foreach($accs as $account) {
    echo "<li>
		<a href='".WEBROOT."admin/mail/edit/?account_id=".$account['id']."'>".$account['from']."</a>
	</li>";
  }
}
?>
</ul>
</div>
</form>

<?php
require_once INCLUDE_ROOT . "View/footer.php";