<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'bittorrent.php';
check_user_status();
$lang = array_merge(load_language('global'));
global $CURUSER, $cache, $site_config;

$sid = 1;
if ($sid > 0 && $sid != $CURUSER['id']) {
    sql_query('UPDATE users SET stylesheet = ' . sqlesc($sid) . ' WHERE id = ' . sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);
}
$cache->update_row('MyUser_' . $CURUSER['id'], [
    'stylesheet' => $sid,
], $site_config['expires']['curuser']);
$cache->update_row('user' . $CURUSER['id'], [
    'stylesheet' => $sid,
], $site_config['expires']['user_cache']);
header("Location: {$site_config['baseurl']}/index.php");
