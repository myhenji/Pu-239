<?php
/**
 * @param $data
 */
function trivia_update($data)
{
    global $site_config, $queries, $cache;
    set_time_limit(1200);
    ignore_user_abort(true);

    // update trivia to get next question
    $sql = 'SELECT gamenum FROM triviasettings WHERE gameon = 1';
    $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
    $result = mysqli_fetch_assoc($res);
    $gamenum = $result['gamenum'];

    if (!empty($gamenum)) {
        if (($qids = $cache->get('triviaquestions_')) === false) {
            $sql = 'SELECT qid FROM triviaq WHERE asked = 0 AND current = 0';
            $res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
            while ($qidarray = mysqli_fetch_assoc($res)) {
                $qids[] = $qidarray['qid'];
            }
            $cache->set('triviaquestions_', $qids, 0);
        }
        for ($x = 0; $x <= 10; $x++) {
            shuffle($qids);
        }
        $qid = array_pop($qids);
        $cache->replace('triviaquestions_', $qids, 0);
        if (count($qids) <= 1) {
            $cache->delete('triviaquestions_');
        }

        // cache for current question
        $cache->set('trivia_current_qid_', (int)$qid, 360);
        $cache->deleteMulti([
            'trivia_gamenum_',
            'trivia_remaining_',
            'trivia_current_question_',
            'trivia_correct_answer_',
        ]);

        // clear previous question
        sql_query('UPDATE triviaq SET current = 0 WHERE current = 1') or sqlerr(__FILE__, __LINE__);
        // set current question
        sql_query('UPDATE triviaq SET asked = 1, current = 1 WHERE qid = ' . sqlesc($qid)) or sqlerr(__FILE__, __LINE__);
    }

    if ($data['clean_log'] && $queries > 0) {
        write_log("Trivia Questions Cleanup: Completed using $queries queries");
    }
}
