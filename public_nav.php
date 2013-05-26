<nav>
<ul class="nav nav-tabs">
    <li <?=endsWith($_SERVER['PHP_SELF'],'index.php')?' class="active"':''?>><a href="<?=WEBROOT?>index.php"><?php echo _("home"); ?></a></li>   

<?php

if($user->isAdmin()):
   ?>
    <li><a href="<?=WEBROOT?>acp/index"><?php echo _("admin control panel"); ?></a></li>   
   <?php
endif;
if($user->loggedIn()):
?>
	<li <?=endsWith($_SERVER['PHP_SELF'],'edit_user.php')?' class="active"':''?>><a href="<?=WEBROOT?>edit_user"><?php echo _("settings"); ?></a></li>
	<li><a href="<?=WEBROOT?>logout.php"><?php echo _("logout"); ?></a></li>
<?php
else:
?>
	<li <?=endsWith($_SERVER['PHP_SELF'],'login.php')?' class="active"':''?>><a href="<?=WEBROOT?>login"><?php echo _("login"); ?></a></li>
	<li <?=endsWith($_SERVER['PHP_SELF'],'register.php')?' class="active"':''?>><a href="<?=WEBROOT?>register"><?php echo _("sign up") ?></a></li>
<?php
endif;
?>
</ul>
</nav>

<?php 
echo $site->renderAlerts();
?>