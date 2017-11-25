<?php
global $CURUSER, $site_config, $cache, $lang;

if ($site_config['uploadapp_alert'] && $CURUSER['class'] >= UC_STAFF) {
    if (($newapp = $cache->get('new_uploadapp_')) === false) {
        $res_newapps = sql_query("SELECT count(id) FROM uploadapp WHERE status = 'pending'");
        list($newapp) = mysqli_fetch_row($res_newapps);
        $cache->set('new_uploadapp_', $newapp, $site_config['expires']['alerts']);
    }
    if ($newapp > 0) {
        $htmlout .= "
   <li>
   <a class='tooltip' href='staffpanel.php?tool=uploadapps&amp;action=app'><b class='button btn-warning is-small'>{$lang['gl_uploadapp_new']}</b>
   <span class='custom info alert alert-warning'><em>{$lang['gl_uploadapp_new']}</em>
   {$lang['gl_hey']} {$CURUSER['username']}!<br> $newapp {$lang['gl_uploadapp_ua']}" . ($newapp > 1 ? 's' : '') . " {$lang['gl_uploadapp_dealt']} 
   {$lang['gl_uploadapp_click']}</span></a></li>";
    }
}
