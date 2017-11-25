<?php
//== iphistory
if ($user['paranoia'] < 2 || $CURUSER['id'] == $id) {
    if (($iphistory = $cache->get('ip_history_' . $id)) === false) {
        $ipto = sql_query("SELECT COUNT(id),enabled FROM `users` AS iplist WHERE `ip` = '" . $user['ip'] . "' GROUP BY enabled") or sqlerr(__FILE__, __LINE__);
        $row12 = mysqli_fetch_row($ipto);
        $row13 = mysqli_fetch_row($ipto);
        $ipuse[ $row12[1] ] = $row12[0];
        $ipuse[ $row13[1] ] = $row13[0];
        if (($ipuse['yes'] == 1 && $ipuse['no'] == 0) || ($ipuse['no'] == 1 && $ipuse['yes'] == 0)) {
            $use = '';
        } else {
            $ipcheck = $user['ip'];
            $enbl = $ipuse['yes'] ? $ipuse['yes'] . ' enabled ' : '';
            $dbl = $ipuse['no'] ? $ipuse['no'] . ' disabled ' : '';
            $mid = $enbl && $dbl ? 'and' : '';
            $iphistory['use'] = "<b>(<font color='red'>{$lang['userdetails_ip_warn']}</font> <a href='staffpanel.php?tool=usersearch&amp;action=usersearch&amp;ip=$ipcheck'>{$lang['userdetails_ip_used']}$enbl $mid $dbl{$lang['userdetails_ip_users']}']}</a>)</b>";
        }
        $resip = sql_query('SELECT ip FROM ips WHERE userid = ' . sqlesc($id) . ' GROUP BY ip') or sqlerr(__FILE__, __LINE__);
        $iphistory['ips'] = mysqli_num_rows($resip);
        $cache->set('ip_history_' . $id, $iphistory, $site_config['expires']['iphistory']);
    }
    if (isset($addr)) {
        if ($CURUSER['id'] == $id || $CURUSER['class'] >= UC_STAFF) {
            $HTMLOUT .= "<tr><td class='rowhead'>{$lang['userdetails_address']}</td><td>{$addr}{$iphistory['use']}&#160;(<a class='altlink' href='staffpanel.php?tool=iphistory&amp;action=iphistory&amp;id=" . (int)$user['id'] . "'><b>{$lang['userdetails_ip_hist']}</b></a>)&#160;(<a class='altlink' href='staffpanel.php?tool=iphistory&amp;action=iplist&amp;id=" . (int)$user['id'] . "'><b>{$lang['userdetails_ip_list']}</b></a>)</td></tr>\n";
        }
    }
    if ($CURUSER['class'] >= UC_STAFF && $iphistory['ips'] > 0) {
        $HTMLOUT .= "<tr><td class='rowhead'>{$lang['userdetails_ip_history']}</td><td>{$lang['userdetails_ip_earlier']}<b><a href='{$site_config['baseurl']}/staffpanel.php?tool=iphistory&amp;action=iphistory&amp;id=" . (int)$user['id'] . "'>{$iphistory['ips']} {$lang['userdetails_ip_different']}</a></b></td></tr>\n";
    }
}
