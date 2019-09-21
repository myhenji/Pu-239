<?php

declare(strict_types = 1);

use DI\DependencyException;
use DI\NotFoundException;
use Pu239\Database;
use Pu239\Image;
use Spatie\Image\Exceptions\InvalidManipulation;

require_once __DIR__ . '/../include/bittorrent.php';
require_once INCL_DIR . 'function_users.php';
require_once INCL_DIR . 'function_bbcode.php';
require_once INCL_DIR . 'function_html.php';
require_once INCL_DIR . 'function_pager.php';
$user = check_user_status();
$lang = array_merge(load_language('global'), load_language('catalogue'));
global $container, $site_config;

/**
 * @param $text
 * @param $char
 * @param $link
 *
 * @throws NotFoundException
 * @throws \Envms\FluentPDO\Exception
 * @throws InvalidManipulation
 * @throws DependencyException
 *
 * @return mixed|string
 */
function readMore($text, $char, $link)
{
    global $lang;
    $text = strip_tags(format_comment($text));

    return strlen($text) > $char ? substr(format_comment($text), 0, $char - 1) . "...<br><a href='$link'><span class='has-text-primary'>{$lang['catol_read_more']}</span></a>" : format_comment($text);
}

/**
 * @param array $array
 * @param int   $class
 *
 * @throws DependencyException
 * @throws InvalidManipulation
 * @throws NotFoundException
 * @throws \Envms\FluentPDO\Exception
 *
 * @return string
 */
function peer_list(array $array, int $class)
{
    global $lang;

    $heading = "
        <tr>
            <th>{$lang['catol_user']}</th>";
    if (has_access($class, UC_STAFF, 'coder')) {
        $heading .= "
            <th>{$lang['catol_port']}&amp;{$lang['catol_ip']}</th>";
    }
    $heading .= "
            <th>{$lang['catol_ratio']}</th>
            <th>{$lang['catol_downloaded']}</th>
            <th>{$lang['catol_uploaded']}</th>
            <th>{$lang['catol_started']}</th>
            <th>{$lang['catol_finished']}</th>
        </tr>";
    $body = '';
    foreach ($array as $p) {
        $time = max(1, (TIME_NOW - $p['started']) - (TIME_NOW - $p['last_action']));
        $body .= '
        <tr>
            <td>' . format_username((int) $p['p_uid']) . '</td>';
        if (has_access($class, UC_STAFF, 'coder')) {
            $body .= '
            <td>' . (has_access($class, UC_STAFF, 'coder') ? format_comment($p['ip']) . ' : ' . (int) $p['port'] : 'xx.xx.xx.xx:xxxx') . '</td>';
        }
        $body .= '
            <td>' . ($p['downloaded'] > 0 ? number_format(($p['uploaded'] / $p['downloaded']), 2) : ($p['uploaded'] > 0 ? '&infin;' : '---')) . '</td>
            <td>' . ($p['downloaded'] > 0 ? mksize($p['downloaded']) . ' @' . (mksize(($p['downloaded'] - $p['downloadoffset']) / $time)) . 's' : '0kb') . '</td>
            <td>' . ($p['uploaded'] > 0 ? mksize($p['uploaded']) . ' @' . (mksize(($p['uploaded'] - $p['uploadoffset']) / $time)) . 's' : '0kb') . '</td>
            <td>' . (get_date((int) $p['started'], 'LONG', 0, 1)) . '</td>
            <td>' . (get_date((int) $p['finishedat'], 'LONG', 0, 1)) . '</td>
        </tr>';
    }

    return main_table($body, $heading);
}

$letter = (isset($_GET['letter']) ? htmlsafechars($_GET['letter']) : '');
$search = (isset($_GET['search']) ? htmlsafechars($_GET['search']) : '');
if (strlen($search) > 4) {
    $params = [
        ':name' => "%$search%",
    ];
    $p = 'search=' . $search . '&amp;';
} elseif (strlen($letter) == 1 && stripos('abcdefghijklmnopqrstuvwxyz', $letter) !== false) {
    $params = [
        ':name' => "$letter%",
    ];
    $p = 'letter=' . $letter . '&amp;';
} else {
    $params = [
        ':name' => 'a%',
    ];
    $p = 'letter=a&amp;';
    $letter = 'a';
}
$fluent = $container->get(Database::class);
$count = $fluent->from('torrents AS t')
                ->select(null)
                ->select('COUNT(t.id) AS count')
                ->where('t.name LIKE :name', $params);

if ($user['hidden'] === 0) {
    $count->where('c.hidden = 0')
          ->leftJoin('categories AS c ON t.category = c.id');
}
$count = $count->fetch('count');
$perpage = 10;
$pager = pager($perpage, $count, $_SERVER['PHP_SELF'] . '?' . $p);
$top = $bottom = '';
$rows = $tids = $peers = [];

$query = $fluent->from('torrents AS t')
                ->select(null)
                ->select('t.id')
                ->select('t.name')
                ->select('t.leechers')
                ->select('t.seeders')
                ->select('t.poster')
                ->select('t.times_completed AS snatched')
                ->select('t.owner')
                ->select('t.size')
                ->select('t.added')
                ->select('t.descr')
                ->select('t.imdb_id')
                ->select('t.anonymous')
                ->where('t.name LIKE :name', $params)
                ->limit($pager['pdo']['limit'])
                ->offset($pager['pdo']['offset']);

if ($user['hidden'] === 0) {
    $query->where('c.hidden = 0')
          ->leftJoin('categories AS c ON t.category = c.id');
}

foreach ($query as $ta) {
    $rows[] = $ta;
    $tids[] = $ta['id'];
}

foreach ($tids as $tid) {
    if (!empty($tid)) {
        $query = $fluent->from('peers')
                        ->select(null)
                        ->select('id')
                        ->select('torrent AS tid')
                        ->select('seeder')
                        ->select('finishedat')
                        ->select('downloadoffset')
                        ->select('uploadoffset')
                        ->select('uploaded')
                        ->select('downloaded')
                        ->select('started')
                        ->select('last_action')
                        ->select('userid AS p_uid')
                        ->select('INET6_NTOA(ip) AS ip')
                        ->select('port')
                        ->where('torrent', $tid)
                        ->where('seeder = "yes"')
                        ->where('to_go = 0')
                        ->orderBy('uploaded DESC')
                        ->limit(5);

        foreach ($query as $pa) {
            $peers[$pa['tid']][] = $pa;
        }
    }
}

$htmlout = "
    <h1 class='has-text-centered'>Torrent Catalog</h1>";
$div = "
    <h2 class='has-text-centered'>{$lang['catol_search']}</h2>
    <form  action='" . $_SERVER['PHP_SELF'] . "' method='get' class='has-text-centered' enctype='multipart/form-data' accept-charset='utf-8'>
        <input type='text' name='search' class='w-50' placeholder='{$lang['catol_search_for_tor']}' value='$search'><br>
        <input type='submit' value='search!' class='button is-small margin20'>
    </form>
    <div class='tabs is-centered is-small'>
        <ul>";
for ($i = 97; $i < 123; ++$i) {
    $active = !empty($letter) && $letter == chr($i) ? "class='active'" : '';
    $div .= "
            <li>
                <a href='{$site_config['paths']['baseurl']}/catalog.php?letter=" . chr($i) . "' {$active}>" . chr($i - 32) . '</a>
            </li>';
}
$div .= '
        </ul>
    </div>';

$htmlout .= main_div($div);

if (!empty($rows)) {
    $images_class = $container->get(Image::class);
    foreach ($rows as $row) {
        if (empty($row['poster']) && !empty($row['imdb_id'])) {
            $row['poster'] = $images_class->find_images($row['imdb_id']);
        }
        if ($row['anonymous'] === '1' && (!has_access($user['class'], UC_STAFF, 'coder') || $row['owner'] === $user['id'])) {
            $uploader = get_anonymous_name();
        } else {
            $uploader = format_username((int) $row['owner']);
        }

        $div = "
        <div class='columns'>
            <div class='column is-2 has-text-centered'>
                <div class='bottom10'>{$lang['catol_upper']}: $uploader</div>
                <div>" . ($row['poster'] ? "
                    <img src='" . url_proxy($row['poster'], true, 250) . "' alt='Poster' class='tooltip-poster'>
                </div>" : "
                    <img src='{$site_config['paths']['images_baseurl']}noposter.png' alt='{$lang['catol_no_poster']}' class='tooltip-poster'>
                </div>") . "
            </div  >
            <div class='column'>";
        $heading = "
                    <tr>
                        <th>Name</th>
                        <th>{$lang['catol_added']}</th>
                        <th>{$lang['catol_size']}</th>
                        <th>{$lang['catol_snatched']}</th>
                        <th>S.</th>
                        <th>L.</th>
                    </tr>";
        $body = "
                    <tr>
                        <td class='w-50'><a href='{$site_config['paths']['baseurl']}/details.php?id=" . (int) $row['id'] . "&amp;hit=1'><div class='torrent-name min-150'>" . format_comment($row['name']) . '</div></a></td>
                        <td>' . get_date((int) $row['added'], 'LONG', 0, 1) . "</td>
                        <td nowrap='nowrap'>" . (mksize($row['size'])) . "</td>
                        <td nowrap='nowrap'>" . ($row['snatched'] > 0 ? ($row['snatched'] == 1 ? (int) $row['snatched'] . ' time' : (int) $row['snatched'] . ' times') : 0) . '</td>
                        <td>' . (int) $row['seeders'] . '</td>
                        <td>' . (int) $row['leechers'] . '</td>
                    </tr>';
        $div .= main_table($body, $heading, 'top20');
        $heading = "
                <tr>
                    <th>{$lang['catol_info']}.</th>
                </tr>";
        $body = "
                <tr>
                    <td><div class='readmore'>" . format_comment($row['descr'], true, true, false) . '</div></td>
                </tr>';
        $div .= main_table($body, $heading, 'top20');
        $div .= "
            </div>
        </div>
        <div class='w-100'>
            <h2 class='has-text-centered'>{$lang['catol_seeder_info']}</h2>
            " . (isset($peers[$row['id']]) ? peer_list($peers[$row['id']], $user['class']) : main_div("
            <p class='has-text-centered'>{$lang['catol_no_info_show']}</p>", '', 'padding20')) . '
        </div>';
        $htmlout .= main_div($div, 'top20', 'padding20');
    }
    $htmlout .= "
        <div>
            {$bottom}
        </div>";
} else {
    $htmlout .= main_div("
        <p class='has-text-centered'>{$lang['catol_nothing_found']}!</p>", 'top20', 'padding20');
}

echo stdhead($lang['catol_std_head']) . wrapper($htmlout) . stdfoot();
