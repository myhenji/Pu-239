<?php

require_once dirname(__FILE__, 3) . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'bittorrent.php';
global $site_config, $fluent, $cache, $session;

$lang = load_language('bookmark');
extract($_POST);

header('content-type: application/json');
if (!$session->validateToken($csrf)) {
    echo json_encode(['fail' => 'csrf']);
    die();
}

if (empty($tid)) {
    echo json_encode(['fail' => 'invalid']);
    die();
}

$current_user = $session->get('userID');
if (empty($current_user)) {
    echo json_encode(['fail' => 'csrf']);
    die();
}

$bookmark = $fluent->from('bookmarks')
    ->select(null)
    ->select('id')
    ->where('torrentid = ?', $tid)
    ->where('userid = ?', $current_user)
    ->fetch('id');

if (!empty($bookmark)) {
    $fluent->delete('bookmarks')
        ->where('id = ?', $bookmark)
        ->execute();
    $cache->delete('bookmm_' . $current_user);
    echo json_encode([
        'content' => 'deleted',
        'text' => $lang['bookmark_add'],
        'tid' => $tid,
        'remove' => $remove,
    ]);
    die();
} else {
    $values = [
        'userid' => $current_user,
        'torrentid' => $tid,
    ];
    $fluent->insertInto('bookmarks')
        ->values($values)
        ->execute();
    $cache->delete('bookmm_' . $current_user);
    echo json_encode([
        'content' => 'added',
        'text' => $lang['bookmarks_del'],
        'tid' => $tid,
        'remove' => $remove,
    ]);
    die();
}
