<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();
$url = required_param('url', PARAM_TEXT);
$fs = get_file_storage();
$temp = local_reactforum_movedrafttotemp($fs, $url);
echo $temp->get_id();
