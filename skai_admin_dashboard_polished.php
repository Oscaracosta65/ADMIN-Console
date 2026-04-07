{source}
<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

$app  = Factory::getApplication();
$user = $app->getIdentity();
$db   = Factory::getContainer()->get(DatabaseInterface::class);

function skaiAdminE($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function skaiAdminN($value, $decimals = 0)
{
    if ($value === null || $value === '') {
        return '0';
    }

    return number_format((float) $value, (int) $decimals);
}

function skaiAdminMoney($value)
{
    return '$' . number_format((float) $value, 2);
}

function skaiAdminPercent($part, $whole, $decimals = 1)
{
    $part  = (float) $part;
    $whole = (float) $whole;

    if ($whole <= 0) {
        return '0%';
    }

    return number_format(($part / $whole) * 100, (int) $decimals) . '%';
}

function skaiAdminTableExists($db, $tableName)
{
    try {
        $tables = $db->getTableList();
        $resolved = $db->replacePrefix($tableName);
        return in_array($resolved, $tables, true);
    } catch (Exception $e) {
        return false;
    }
}

function skaiAdminGetColumns($db, $tableName)
{
    try {
        if (!skaiAdminTableExists($db, $tableName)) {
            return array();
        }

        $columns = $db->getTableColumns($tableName, false);

        if (!is_array($columns)) {
            return array();
        }

        return array_keys($columns);
    } catch (Exception $e) {
        return array();
    }
}

function skaiAdminHasColumn($columns, $name)
{
    return in_array($name, $columns, true);
}

function skaiAdminLoadScalar($db, $sql)
{
    try {
        $db->setQuery($sql);
        $result = $db->loadResult();
        return $result !== null ? $result : 0;
    } catch (Exception $e) {
        return 0;
    }
}

function skaiAdminLoadAssocList($db, $sql)
{
    try {
        $db->setQuery($sql);
        $rows = $db->loadAssocList();
        return is_array($rows) ? $rows : array();
    } catch (Exception $e) {
        return array();
    }
}

function skaiAdminSafeMaxForBars($rows, $key)
{
    $max = 0;

    if (!is_array($rows)) {
        return 1;
    }

    foreach ($rows as $row) {
        $v = isset($row[$key]) ? (float) $row[$key] : 0;
        if ($v > $max) {
            $max = $v;
        }
    }

    return $max > 0 ? $max : 1;
}

function skaiAdminResolveTable($db, $candidates, $fallback)
{
    foreach ($candidates as $candidate) {
        if (skaiAdminTableExists($db, $candidate)) {
            return $candidate;
        }
    }

    return $fallback;
}

function skaiAdminBuildHitExpression($predCols)
{
    $pickExpr = 'COALESCE(NULLIF(target_pick_size, 0), NULLIF(daily_pick_size, 0), 0)';
    $mainExpr = skaiAdminHasColumn($predCols, 'main_matches') ? 'COALESCE(main_matches, 0)' : '0';

    return array(
        'pick_expr' => $pickExpr,
        'full_hit_expr' => "CASE WHEN {$pickExpr} > 0 AND {$mainExpr} >= {$pickExpr} THEN 1 ELSE 0 END",
        'near_hit_expr' => "CASE WHEN {$pickExpr} > 1 AND {$mainExpr} = ({$pickExpr} - 1) THEN 1 ELSE 0 END"
    );
}

function skaiAdminFriendlyLabel($value, $type)
{
    $value = trim((string) $value);

    if ($value === '' || strtolower($value) === 'unknown' || strtolower($value) === 'null') {
        return 'Not categorized';
    }

    $map = array(
        'source' => array(
            'ai_prediction' => 'AI Prediction',
            'skai_prediction' => 'SKAI Prediction',
            'manual' => 'Manual Entry',
            'saved' => 'Saved Prediction',
            'system' => 'System Generated'
        ),
        'prediction_type' => array(
            'pick3' => 'Pick 3',
            'pick4' => 'Pick 4',
            'pick5' => 'Pick 5',
            'powerball' => 'Powerball',
            'euromillions' => 'EuroMillions',
            'daily4' => 'Daily 4',
            'daily3' => 'Daily 3',
            'regular' => 'Standard Lottery'
        ),
        'prediction_family' => array(
            'regular' => 'Standard Lottery',
            'daily' => 'Daily Lottery'
        ),
        'risk_profile' => array(
            'balanced' => 'Balanced',
            'conservative' => 'Conservative',
            'explorative' => 'Exploratory',
            'explorer' => 'Exploratory',
            'aggressive' => 'Aggressive'
        ),
        'strategy' => array(
            'balanced' => 'Balanced',
            'balanced_mix' => 'Balanced Mix',
            'balanced_mix25' => 'Balanced Mix 25',
            'skip_pattern' => 'Skip Pattern',
            'ai_forward' => 'AI Forward',
            'hybrid' => 'Hybrid'
        ),
        'skai_run_mode' => array(
            'balanced' => 'Balanced',
            'hybrid' => 'Hybrid',
            'manual' => 'Manual',
            'auto' => 'Automatic'
        )
    );

    $key = strtolower($value);
    if (isset($map[$type]) && isset($map[$type][$key])) {
        return $map[$type][$key];
    }

    $label = str_replace(array('_', '-'), ' ', $value);
    $label = preg_replace('/\s+/', ' ', $label);
    $label = trim($label);

    return ucwords($label);
}

function skaiAdminNormalizeBreakdownRows($rows, $labelKey, $valueKey, $type)
{
    $grouped = array();

    if (!is_array($rows)) {
        return array();
    }

    foreach ($rows as $row) {
        $label = isset($row[$labelKey]) ? skaiAdminFriendlyLabel($row[$labelKey], $type) : 'Not categorized';
        $value = isset($row[$valueKey]) ? (float) $row[$valueKey] : 0;

        if (!isset($grouped[$label])) {
            $grouped[$label] = 0;
        }

        $grouped[$label] += $value;
    }

    arsort($grouped);

    $normalized = array();
    foreach ($grouped as $label => $value) {
        $normalized[] = array(
            'label' => $label,
            'total' => $value
        );
    }

    return $normalized;
}

function skaiAdminCleanLotteryName($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return 'Unknown Lottery';
    }

    return preg_replace('/\s+/', ' ', $value);
}

function skaiAdminRenderBarsFriendly($rows, $labelKey, $valueKey, $formatterCallback)
{
    $max = skaiAdminSafeMaxForBars($rows, $valueKey);

    if (empty($rows)) {
        echo '<div class="skai-empty">No data yet.</div>';
        return;
    }

    echo '<div class="skai-bars">';
    foreach ($rows as $row) {
        $label = isset($row[$labelKey]) ? $row[$labelKey] : '';
        $value = isset($row[$valueKey]) ? (float) $row[$valueKey] : 0;
        $width = max(3, (int) round(($value / $max) * 100));

        echo '<div class="skai-bar-row">';
        echo '<div class="skai-bar-top">';
        echo '<div class="skai-bar-label" title="' . skaiAdminE($label) . '">' . skaiAdminE($label) . '</div>';
        echo '<div class="skai-bar-value">' . call_user_func($formatterCallback, $value) . '</div>';
        echo '</div>';
        echo '<div class="skai-bar-track"><span class="skai-bar-fill" style="width:' . (int) $width . '%;"></span></div>';
        echo '</div>';
    }
    echo '</div>';
}

function skaiAdminRenderSimpleTable($headers, $rows, $map)
{
    if (empty($rows)) {
        echo '<div class="skai-empty">No data yet.</div>';
        return;
    }

    echo '<div class="skai-table-wrap">';
    echo '<table class="skai-table">';
    echo '<thead><tr>';

    foreach ($headers as $header) {
        echo '<th>' . skaiAdminE($header) . '</th>';
    }

    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($rows as $row) {
        echo '<tr>';
        foreach ($map as $cellCallback) {
            echo '<td>' . call_user_func($cellCallback, $row) . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}

$tblUsers = skaiAdminResolveTable($db, array('#__users', 'jos9d_users'), '#__users');
$tblLotteries = skaiAdminResolveTable($db, array('#__lotteries', 'jos9d_lotteries'), '#__lotteries');
$tblSubs = skaiAdminResolveTable($db, array('#__osmembership_subscribers', 'jos9d_osmembership_subscribers'), '#__osmembership_subscribers');
$tblPredictions = skaiAdminResolveTable($db, array('#__user_saved_numbers', 'jos9d_user_saved_numbers', '#__saved_numbers'), '#__user_saved_numbers');

$tblSkaiTables = array(
    '#__skai_backtest_summary',
    '#__skai_best_settings',
    '#__skai_calibration_bins',
    '#__skai_learning_performance',
    '#__skai_lottery_settings',
    '#__skai_parameter_sets',
    '#__skai_runtime_audit',
    '#__skai_run_log',
    '#__skai_shared_learning',
    '#__skai_skip_hit_learning',
    '#__skai_skip_hit_outcomes',
    '#__skai_skip_hit_runs',
    '#__skai_tail_recovery_summary',
    '#__skai_user_preferences'
);

$userCols = skaiAdminGetColumns($db, $tblUsers);
$lotCols  = skaiAdminGetColumns($db, $tblLotteries);
$subCols  = skaiAdminGetColumns($db, $tblSubs);
$predCols = skaiAdminGetColumns($db, $tblPredictions);

$hits = skaiAdminBuildHitExpression($predCols);
$fullHitExpr = $hits['full_hit_expr'];
$nearHitExpr = $hits['near_hit_expr'];

$predDateCol = skaiAdminHasColumn($predCols, 'date_saved') ? 'date_saved' : '';
$userLoginCol = skaiAdminHasColumn($userCols, 'lastvisitDate') ? 'lastvisitDate' : '';
$userRegisterCol = skaiAdminHasColumn($userCols, 'registerDate') ? 'registerDate' : '';
$subToDateCol = skaiAdminHasColumn($subCols, 'to_date') ? 'to_date' : '';
$subPublishedCol = skaiAdminHasColumn($subCols, 'published') ? 'published' : '';
$subAmountCol = skaiAdminHasColumn($subCols, 'amount') ? 'amount' : '';
$subPaymentAmountCol = skaiAdminHasColumn($subCols, 'payment_amount') ? 'payment_amount' : '';
$subCreatedCol = skaiAdminHasColumn($subCols, 'created_date') ? 'created_date' : '';
$predLotteryJoinCol = skaiAdminHasColumn($predCols, 'target_lottery_id') ? 'target_lottery_id' : (skaiAdminHasColumn($predCols, 'lottery_id') ? 'lottery_id' : '');
$lotteryPkCol = skaiAdminHasColumn($lotCols, 'lottery_id') ? 'lottery_id' : 'id';

$summary = array();

$summary['total_users'] = skaiAdminTableExists($db, $tblUsers)
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblUsers))
    : 0;
$summary['users_active_today'] = ($userLoginCol !== '')
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblUsers) . " WHERE " . $db->quoteName($userLoginCol) . " >= DATE_SUB(NOW(), INTERVAL 1 DAY)")
    : 0;
$summary['users_active_7d'] = ($userLoginCol !== '')
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblUsers) . " WHERE " . $db->quoteName($userLoginCol) . " >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
    : 0;
$summary['users_active_30d'] = ($userLoginCol !== '')
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblUsers) . " WHERE " . $db->quoteName($userLoginCol) . " >= DATE_SUB(NOW(), INTERVAL 30 DAY)")
    : 0;
$summary['new_users_30d'] = ($userRegisterCol !== '')
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblUsers) . " WHERE " . $db->quoteName($userRegisterCol) . " >= DATE_SUB(NOW(), INTERVAL 30 DAY)")
    : 0;
$summary['total_lotteries'] = skaiAdminTableExists($db, $tblLotteries)
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblLotteries))
    : 0;
$summary['total_predictions'] = skaiAdminTableExists($db, $tblPredictions)
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblPredictions))
    : 0;
$summary['predictions_today'] = ($predDateCol !== '')
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblPredictions) . " WHERE " . $db->quoteName($predDateCol) . " >= DATE_SUB(NOW(), INTERVAL 1 DAY)")
    : 0;
$summary['predictions_7d'] = ($predDateCol !== '')
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblPredictions) . " WHERE " . $db->quoteName($predDateCol) . " >= DATE_SUB(NOW(), INTERVAL 7 DAY)")
    : 0;
$summary['predictions_30d'] = ($predDateCol !== '')
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblPredictions) . " WHERE " . $db->quoteName($predDateCol) . " >= DATE_SUB(NOW(), INTERVAL 30 DAY)")
    : 0;

$verifiedWhere = array();
if (skaiAdminHasColumn($predCols, 'main_matches')) {
    $verifiedWhere[] = "main_matches IS NOT NULL";
}
if (skaiAdminHasColumn($predCols, 'bonus_matches')) {
    $verifiedWhere[] = "bonus_matches IS NOT NULL";
}
if (skaiAdminHasColumn($predCols, 'matched_numbers')) {
    $verifiedWhere[] = "(matched_numbers IS NOT NULL AND matched_numbers <> '')";
}
$verifiedClause = count($verifiedWhere) ? implode(' OR ', $verifiedWhere) : '1=0';

$summary['verified_predictions'] = skaiAdminTableExists($db, $tblPredictions)
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblPredictions) . " WHERE (" . $verifiedClause . ")")
    : 0;
$summary['full_hits'] = skaiAdminTableExists($db, $tblPredictions)
    ? skaiAdminLoadScalar($db, "SELECT COALESCE(SUM(" . $fullHitExpr . "), 0) FROM " . $db->quoteName($tblPredictions))
    : 0;
$summary['near_hits'] = skaiAdminTableExists($db, $tblPredictions)
    ? skaiAdminLoadScalar($db, "SELECT COALESCE(SUM(" . $nearHitExpr . "), 0) FROM " . $db->quoteName($tblPredictions))
    : 0;
$summary['active_lotteries_30d'] = ($predDateCol !== '' && $predLotteryJoinCol !== '')
    ? skaiAdminLoadScalar(
        $db,
        "SELECT COUNT(DISTINCT " . $db->quoteName($predLotteryJoinCol) . ")
         FROM " . $db->quoteName($tblPredictions) . "
         WHERE " . $db->quoteName($predDateCol) . " >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )
    : 0;

$subsActiveWhere = array();
if ($subPublishedCol !== '') {
    $subsActiveWhere[] = $db->quoteName($subPublishedCol) . " = 1";
}
if ($subToDateCol !== '') {
    $subsActiveWhere[] = $db->quoteName($subToDateCol) . " >= NOW()";
}
$subsActiveClause = count($subsActiveWhere) ? implode(' AND ', $subsActiveWhere) : '1=1';

$summary['total_subscriptions'] = skaiAdminTableExists($db, $tblSubs)
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblSubs))
    : 0;
$summary['active_subscriptions'] = skaiAdminTableExists($db, $tblSubs)
    ? skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($tblSubs) . " WHERE " . $subsActiveClause)
    : 0;

$amountExpr = '0';
if ($subPaymentAmountCol !== '') {
    $amountExpr = "COALESCE(" . $db->quoteName($subPaymentAmountCol) . ", 0)";
} elseif ($subAmountCol !== '') {
    $amountExpr = "COALESCE(" . $db->quoteName($subAmountCol) . ", 0)";
}

$summary['total_revenue'] = skaiAdminTableExists($db, $tblSubs)
    ? skaiAdminLoadScalar($db, "SELECT COALESCE(SUM(" . $amountExpr . "), 0) FROM " . $db->quoteName($tblSubs) . " WHERE " . $subsActiveClause)
    : 0;
$summary['revenue_30d'] = ($subCreatedCol !== '')
    ? skaiAdminLoadScalar(
        $db,
        "SELECT COALESCE(SUM(" . $amountExpr . "), 0)
         FROM " . $db->quoteName($tblSubs) . "
         WHERE " . $db->quoteName($subCreatedCol) . " >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    )
    : 0;

$topLotteries = array();
if (skaiAdminTableExists($db, $tblPredictions) && skaiAdminTableExists($db, $tblLotteries) && $predLotteryJoinCol !== '') {
    $lotteryFullHitExpr = str_replace('main_matches', 'p.main_matches', $fullHitExpr);
    $lotteryNearHitExpr = str_replace('main_matches', 'p.main_matches', $nearHitExpr);

    $topLotteriesRaw = skaiAdminLoadAssocList(
        $db,
        "SELECT
            l." . $db->quoteName($lotteryPkCol) . " AS lottery_id,
            l." . $db->quoteName('name') . " AS lottery_name,
            COUNT(p.id) AS total_predictions,
            COALESCE(SUM(" . $lotteryFullHitExpr . "), 0) AS full_hits,
            COALESCE(SUM(" . $lotteryNearHitExpr . "), 0) AS near_hits
         FROM " . $db->quoteName($tblLotteries) . " AS l
         LEFT JOIN " . $db->quoteName($tblPredictions) . " AS p
            ON p." . $db->quoteName($predLotteryJoinCol) . " = l." . $db->quoteName($lotteryPkCol) . "
         GROUP BY l." . $db->quoteName($lotteryPkCol) . ", l." . $db->quoteName('name') . "
         ORDER BY total_predictions DESC, lottery_name ASC
         LIMIT 12"
    );

    foreach ($topLotteriesRaw as $row) {
        $row['lottery_name'] = skaiAdminCleanLotteryName($row['lottery_name']);
        $topLotteries[] = $row;
    }
}

$topUsers = array();
if (skaiAdminTableExists($db, $tblPredictions) && skaiAdminTableExists($db, $tblUsers) && skaiAdminHasColumn($predCols, 'user_id')) {
    $orderPredDate = $predDateCol !== '' ? 'MAX(p.' . $db->quoteName($predDateCol) . ')' : 'MAX(p.id)';

    $topUsers = skaiAdminLoadAssocList(
        $db,
        "SELECT
            u.id AS user_id,
            u.name AS user_name,
            u.username AS username,
            u.email AS email,
            COUNT(p.id) AS total_predictions,
            " . $orderPredDate . " AS last_prediction_at
         FROM " . $db->quoteName($tblUsers) . " AS u
         INNER JOIN " . $db->quoteName($tblPredictions) . " AS p
            ON p.user_id = u.id
         GROUP BY u.id, u.name, u.username, u.email
         ORDER BY total_predictions DESC, user_name ASC
         LIMIT 12"
    );
}

$predictionTypes = array();
if (skaiAdminTableExists($db, $tblPredictions) && skaiAdminHasColumn($predCols, 'prediction_type')) {
    $predictionTypes = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT
                COALESCE(NULLIF(prediction_type, ''), 'unknown') AS label,
                COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY COALESCE(NULLIF(prediction_type, ''), 'unknown')
             ORDER BY total DESC, label ASC
             LIMIT 15"
        ),
        'label',
        'total',
        'prediction_type'
    );
}

$predictionFamilies = array();
if (skaiAdminTableExists($db, $tblPredictions) && skaiAdminHasColumn($predCols, 'prediction_family')) {
    $predictionFamilies = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT
                COALESCE(NULLIF(prediction_family, ''), 'unknown') AS label,
                COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY COALESCE(NULLIF(prediction_family, ''), 'unknown')
             ORDER BY total DESC, label ASC
             LIMIT 10"
        ),
        'label',
        'total',
        'prediction_family'
    );
}

$predictionSources = array();
if (skaiAdminTableExists($db, $tblPredictions) && skaiAdminHasColumn($predCols, 'source')) {
    $predictionSources = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT
                COALESCE(NULLIF(source, ''), 'unknown') AS label,
                COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY COALESCE(NULLIF(source, ''), 'unknown')
             ORDER BY total DESC, label ASC
             LIMIT 10"
        ),
        'label',
        'total',
        'source'
    );
}

$runModes = array();
if (skaiAdminTableExists($db, $tblPredictions) && skaiAdminHasColumn($predCols, 'skai_run_mode')) {
    $runModes = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT
                COALESCE(NULLIF(skai_run_mode, ''), 'unknown') AS label,
                COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY COALESCE(NULLIF(skai_run_mode, ''), 'unknown')
             ORDER BY total DESC, label ASC
             LIMIT 10"
        ),
        'label',
        'total',
        'skai_run_mode'
    );
}

$strategies = array();
if (skaiAdminTableExists($db, $tblPredictions) && skaiAdminHasColumn($predCols, 'strategy')) {
    $strategies = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT
                COALESCE(NULLIF(strategy, ''), 'unknown') AS label,
                COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY COALESCE(NULLIF(strategy, ''), 'unknown')
             ORDER BY total DESC, label ASC
             LIMIT 10"
        ),
        'label',
        'total',
        'strategy'
    );
}

$riskProfiles = array();
if (skaiAdminTableExists($db, $tblPredictions) && skaiAdminHasColumn($predCols, 'risk_profile')) {
    $riskProfiles = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT
                COALESCE(NULLIF(risk_profile, ''), 'unknown') AS label,
                COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY COALESCE(NULLIF(risk_profile, ''), 'unknown')
             ORDER BY total DESC, label ASC
             LIMIT 10"
        ),
        'label',
        'total',
        'risk_profile'
    );
}

$recentPredictions = array();
if (skaiAdminTableExists($db, $tblPredictions)) {
    $selectParts = array(
        'p.id',
        skaiAdminHasColumn($predCols, 'label') ? 'p.label' : "'' AS label",
        skaiAdminHasColumn($predCols, 'source') ? 'p.source' : "'' AS source",
        skaiAdminHasColumn($predCols, 'prediction_type') ? 'p.prediction_type' : "'' AS prediction_type",
        skaiAdminHasColumn($predCols, 'prediction_family') ? 'p.prediction_family' : "'' AS prediction_family",
        skaiAdminHasColumn($predCols, 'main_matches') ? 'p.main_matches' : 'NULL AS main_matches',
        skaiAdminHasColumn($predCols, 'bonus_matches') ? 'p.bonus_matches' : 'NULL AS bonus_matches',
        skaiAdminHasColumn($predCols, 'target_draw_date') ? 'p.target_draw_date' : 'NULL AS target_draw_date',
        skaiAdminHasColumn($predCols, 'date_saved') ? 'p.date_saved' : 'NULL AS date_saved',
        skaiAdminHasColumn($predCols, 'user_id') ? 'p.user_id' : 'NULL AS user_id',
        ($predLotteryJoinCol !== '') ? 'p.' . $db->quoteName($predLotteryJoinCol) . ' AS lottery_join_id' : 'NULL AS lottery_join_id',
        'u.name AS user_name',
        'l.name AS lottery_name'
    );

    $orderByRecent = $predDateCol !== '' ? 'p.' . $db->quoteName($predDateCol) . ' DESC' : 'p.id DESC';

    $recentPredictions = skaiAdminLoadAssocList(
        $db,
        "SELECT
            " . implode(",\n            ", $selectParts) . "
         FROM " . $db->quoteName($tblPredictions) . " AS p
         LEFT JOIN " . $db->quoteName($tblUsers) . " AS u
            ON " . (skaiAdminHasColumn($predCols, 'user_id') ? 'p.user_id = u.id' : '1=0') . "
         LEFT JOIN " . $db->quoteName($tblLotteries) . " AS l
            ON " . (($predLotteryJoinCol !== '') ? 'p.' . $db->quoteName($predLotteryJoinCol) . ' = l.' . $db->quoteName($lotteryPkCol) : '1=0') . "
         ORDER BY " . $orderByRecent . "
         LIMIT 15"
    );
}

$recentLogins = array();
if (skaiAdminTableExists($db, $tblUsers) && $userLoginCol !== '') {
    $recentLogins = skaiAdminLoadAssocList(
        $db,
        "SELECT
            id,
            name,
            username,
            email,
            " . $db->quoteName($userLoginCol) . " AS lastvisitDate,
            " . ($userRegisterCol !== '' ? $db->quoteName($userRegisterCol) : 'NULL') . " AS registerDate
         FROM " . $db->quoteName($tblUsers) . "
         WHERE " . $db->quoteName($userLoginCol) . " IS NOT NULL
         ORDER BY " . $db->quoteName($userLoginCol) . " DESC
         LIMIT 15"
    );
}

$skaiCounts = array();
foreach ($tblSkaiTables as $skaiTable) {
    if (skaiAdminTableExists($db, $skaiTable)) {
        $skaiCounts[] = array(
            'table_name' => $db->replacePrefix($skaiTable),
            'total_rows' => (int) skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($skaiTable))
        );
    }
}

$distinctPredLotteries = 0;
if ($predLotteryJoinCol !== '') {
    $distinctPredLotteries = (int) skaiAdminLoadScalar(
        $db,
        "SELECT COUNT(DISTINCT " . $db->quoteName($predLotteryJoinCol) . ") FROM " . $db->quoteName($tblPredictions)
    );
}

$healthChecks = array(
    array('label' => 'Predictions waiting for verification', 'value' => max(0, (int) $summary['total_predictions'] - (int) $summary['verified_predictions'])),
    array('label' => 'Lotteries with no saved predictions', 'value' => max(0, (int) $summary['total_lotteries'] - $distinctPredLotteries)),
    array('label' => 'Predictions saved in the last 30 days', 'value' => (int) $summary['predictions_30d']),
    array('label' => 'Users active in the last 30 days', 'value' => (int) $summary['users_active_30d']),
    array('label' => 'Active paid subscriptions', 'value' => (int) $summary['active_subscriptions'])
);
?>

[[style]]
.skai-admin-wrap {
    max-width: 1500px;
    margin: 24px auto 48px auto;
    padding: 0 16px;
    font-family: Arial, Helvetica, sans-serif;
    color: #111827;
}
.skai-header {
    margin-bottom: 18px;
}
.skai-eyebrow {
    display: inline-block;
    margin-bottom: 10px;
    padding: 6px 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 700;
}
.skai-title {
    margin: 0 0 8px 0;
    font-size: 34px;
    line-height: 1.15;
    font-weight: 800;
    color: #0f172a;
}
.skai-subtitle {
    margin: 0;
    color: #475569;
    font-size: 15px;
    line-height: 1.5;
    max-width: 900px;
}
.skai-section {
    margin: 0 0 22px 0;
}
.skai-section-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.skai-section-title {
    margin: 0;
    font-size: 22px;
    line-height: 1.2;
    font-weight: 800;
    color: #0f172a;
}
.skai-section-note {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}
.skai-grid-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin: 24px 0 28px 0;
}
.skai-card {
    background: #ffffff;
    border: 1px solid #dbe2ea;
    border-radius: 16px;
    padding: 16px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}
.skai-kpi-group {
    margin: 0 0 18px 0;
}
.skai-kpi-group:last-child {
    margin-bottom: 0;
}
.skai-kpi-group-title {
    margin: 0 0 10px 0;
    font-size: 12px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    font-weight: 800;
}
.skai-kpi-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 10px;
}
.skai-kpi-value {
    font-size: 30px;
    line-height: 1;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 8px;
}
.skai-kpi-meta {
    font-size: 13px;
    color: #475569;
}
.skai-grid-2 {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}
.skai-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}
.skai-panel {
    background: #ffffff;
    border: 1px solid #dbe2ea;
    border-radius: 18px;
    padding: 18px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.04);
}
.skai-panel h3 {
    margin: 0 0 4px 0;
    font-size: 17px;
    line-height: 1.25;
    font-weight: 800;
    color: #0f172a;
}
.skai-panel-desc {
    margin: 0 0 14px 0;
    color: #64748b;
    font-size: 13px;
    line-height: 1.45;
}
.skai-bars {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.skai-bar-row {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.skai-bar-top {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
}
.skai-bar-label {
    font-size: 13px;
    font-weight: 700;
    color: #334155;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 75%;
}
.skai-bar-value {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
    white-space: nowrap;
}
.skai-bar-track {
    width: 100%;
    height: 10px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.skai-bar-fill {
    display: block;
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #2563eb 0%, #0ea5e9 100%);
}
.skai-table-wrap {
    overflow-x: auto;
}
.skai-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 700px;
}
.skai-table thead th {
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #64748b;
    padding: 12px 10px;
    border-bottom: 1px solid #dbe2ea;
    background: #f8fafc;
}
.skai-table tbody td {
    padding: 12px 10px;
    border-bottom: 1px solid #edf2f7;
    font-size: 13px;
    color: #1e293b;
    vertical-align: top;
    line-height: 1.45;
}
.skai-table tbody tr:hover {
    background: #f8fbff;
}
.skai-tag {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-size: 12px;
    font-weight: 700;
}
.skai-muted {
    color: #64748b;
}
.skai-empty {
    padding: 16px;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    color: #64748b;
    font-size: 14px;
    background: #f8fafc;
}
.skai-health-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.skai-health-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
}
.skai-health-label {
    font-size: 14px;
    color: #334155;
    font-weight: 700;
}
.skai-health-value {
    font-size: 14px;
    color: #0f172a;
    font-weight: 800;
}
.skai-footnote {
    margin-top: 18px;
    padding: 14px 16px;
    border: 1px solid #dbe2ea;
    border-radius: 14px;
    background: #f8fafc;
    color: #475569;
    font-size: 13px;
    line-height: 1.5;
}
@media (max-width: 1400px) {
    .skai-grid-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 980px) {
    .skai-grid-cards,
    .skai-grid-2,
    .skai-grid-3 {
        grid-template-columns: repeat(1, minmax(0, 1fr));
    }
    .skai-title {
        font-size: 28px;
    }
}
[[/style]]

[[div class="skai-admin-wrap"]]
    [[div class="skai-header"]]
        [[div class="skai-eyebrow"]]Admin Dashboard[[/div]]
        [[h1 class="skai-title"]]LottoExpert Admin Intelligence Dashboard[[/h1]]
        [[p class="skai-subtitle"]]A cleaner overview of platform activity, user engagement, prediction volume, results tracking, and system status. This version translates raw technical values into more readable labels where possible.[[/p]]
    [[/div]]

    [[div class="skai-grid-cards"]]
        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Business Overview[[/div]]
                [[div class="skai-kpi-label"]]Total Users[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN($summary['total_users']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]New in 30 days: <?php echo skaiAdminN($summary['new_users_30d']); ?>[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]User Activity[[/div]]
                [[div class="skai-kpi-label"]]Active Users[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN($summary['users_active_30d']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Today: <?php echo skaiAdminN($summary['users_active_today']); ?> | Last 7 days: <?php echo skaiAdminN($summary['users_active_7d']); ?>[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Prediction Overview[[/div]]
                [[div class="skai-kpi-label"]]Total Predictions[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN($summary['total_predictions']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Today: <?php echo skaiAdminN($summary['predictions_today']); ?> | Last 30 days: <?php echo skaiAdminN($summary['predictions_30d']); ?>[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Results Tracking[[/div]]
                [[div class="skai-kpi-label"]]Verified Predictions[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN($summary['verified_predictions']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Verification rate: <?php echo skaiAdminPercent($summary['verified_predictions'], $summary['total_predictions']); ?>[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Results Tracking[[/div]]
                [[div class="skai-kpi-label"]]Full Hits[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN($summary['full_hits']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Near hits: <?php echo skaiAdminN($summary['near_hits']); ?>[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Lottery Coverage[[/div]]
                [[div class="skai-kpi-label"]]Total Lotteries[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN($summary['total_lotteries']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Active in 30 days: <?php echo skaiAdminN($summary['active_lotteries_30d']); ?>[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Memberships[[/div]]
                [[div class="skai-kpi-label"]]Active Subscriptions[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN($summary['active_subscriptions']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Total subscription records: <?php echo skaiAdminN($summary['total_subscriptions']); ?>[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Revenue Snapshot[[/div]]
                [[div class="skai-kpi-label"]]Active Revenue Total[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminMoney($summary['total_revenue']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Revenue in 30 days: <?php echo skaiAdminMoney($summary['revenue_30d']); ?>[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Prediction Velocity[[/div]]
                [[div class="skai-kpi-label"]]Saved in Last 7 Days[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN($summary['predictions_7d']); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Recent platform activity[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Top Users[[/div]]
                [[div class="skai-kpi-label"]]Power Users[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN(count($topUsers)); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Most active users shown below[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]System Tracking[[/div]]
                [[div class="skai-kpi-label"]]Tracked SKAI Tables[[/div]]
                [[div class="skai-kpi-value"]]
                    <?php echo skaiAdminN(count($skaiCounts)); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Counts shown below[[/div]]
            [[/div]]
        [[/div]]

        [[div class="skai-card"]]
            [[div class="skai-kpi-group"]]
                [[div class="skai-kpi-group-title"]]Refresh Time[[/div]]
                [[div class="skai-kpi-label"]]Last Refresh[[/div]]
                [[div class="skai-kpi-value" style="font-size:20px;"]]
                    <?php echo skaiAdminE(HTMLHelper::_('date', 'now', 'Y-m-d H:i:s')); ?>
                [[/div]]
                [[div class="skai-kpi-meta"]]Server time[[/div]]
            [[/div]]
        [[/div]]
    [[/div]]

    [[div class="skai-section"]]
        [[div class="skai-section-head"]]
            [[h2 class="skai-section-title"]]Prediction Activity[[/h2]]
            [[p class="skai-section-note"]]What users are saving most often and where the strongest demand is[[/p]]
        [[/div]]

        [[div class="skai-grid-2"]]
            [[div class="skai-panel"]]
                [[h3]]Most Popular Lotteries[[/h3]]
                [[p class="skai-panel-desc"]]Lotteries with the most saved predictions.[[/p]]
                <?php skaiAdminRenderBarsFriendly($topLotteries, 'lottery_name', 'total_predictions', 'skaiAdminN'); ?>
            [[/div]]

            [[div class="skai-panel"]]
                [[h3]]Prediction Type Breakdown[[/h3]]
                [[p class="skai-panel-desc"]]Shows which kinds of predictions users save most often.[[/p]]
                <?php skaiAdminRenderBarsFriendly($predictionTypes, 'label', 'total', 'skaiAdminN'); ?>
            [[/div]]

            [[div class="skai-panel"]]
                [[h3]]Prediction Family Breakdown[[/h3]]
                [[p class="skai-panel-desc"]]Compares standard lottery predictions with daily-style predictions.[[/p]]
                <?php skaiAdminRenderBarsFriendly($predictionFamilies, 'label', 'total', 'skaiAdminN'); ?>
            [[/div]]

            [[div class="skai-panel"]]
                [[h3]]Prediction Source Breakdown[[/h3]]
                [[p class="skai-panel-desc"]]Shows how predictions were created, such as AI or SKAI-based saves.[[/p]]
                <?php skaiAdminRenderBarsFriendly($predictionSources, 'label', 'total', 'skaiAdminN'); ?>
            [[/div]]
        [[/div]]
    [[/div]]

    [[div class="skai-section"]]
        [[div class="skai-section-head"]]
            [[h2 class="skai-section-title"]]Technical Breakdown[[/h2]]
            [[p class="skai-section-note"]]Useful internal data for SKAI monitoring, translated into friendlier labels[[/p]]
        [[/div]]

        [[div class="skai-grid-3"]]
            [[div class="skai-panel"]]
                [[h3]]Run Mode Usage[[/h3]]
                [[p class="skai-panel-desc"]]How prediction runs are classified internally.[[/p]]
                <?php skaiAdminRenderBarsFriendly($runModes, 'label', 'total', 'skaiAdminN'); ?>
            [[/div]]

            [[div class="skai-panel"]]
                [[h3]]Strategy Usage[[/h3]]
                [[p class="skai-panel-desc"]]Which strategy families are being used most.[[/p]]
                <?php skaiAdminRenderBarsFriendly($strategies, 'label', 'total', 'skaiAdminN'); ?>
            [[/div]]

            [[div class="skai-panel"]]
                [[h3]]Risk Profile Usage[[/h3]]
                [[p class="skai-panel-desc"]]Distribution of conservative, balanced, and exploratory profiles.[[/p]]
                <?php skaiAdminRenderBarsFriendly($riskProfiles, 'label', 'total', 'skaiAdminN'); ?>
            [[/div]]
        [[/div]]
    [[/div]]

    [[div class="skai-section"]]
        [[div class="skai-section-head"]]
            [[h2 class="skai-section-title"]]User Activity[[/h2]]
            [[p class="skai-section-note"]]Who is using the platform most and who logged in recently[[/p]]
        [[/div]]

        [[div class="skai-grid-2"]]
            [[div class="skai-panel"]]
                [[h3]]Most Active Prediction Users[[/h3]]
                [[p class="skai-panel-desc"]]Users with the highest number of saved predictions.[[/p]]
                <?php
                skaiAdminRenderSimpleTable(
                    array('User', 'Username', 'Email', 'Predictions', 'Last Prediction'),
                    $topUsers,
                    array(
                        function ($row) {
                            return '<strong>' . skaiAdminE($row['user_name']) . '</strong>';
                        },
                        function ($row) {
                            return skaiAdminE($row['username']);
                        },
                        function ($row) {
                            return skaiAdminE($row['email']);
                        },
                        function ($row) {
                            return skaiAdminN($row['total_predictions']);
                        },
                        function ($row) {
                            return skaiAdminE($row['last_prediction_at']);
                        }
                    )
                );
                ?>
            [[/div]]

            [[div class="skai-panel"]]
                [[h3]]Recent User Logins[[/h3]]
                [[p class="skai-panel-desc"]]Latest users who visited the site recently.[[/p]]
                <?php
                skaiAdminRenderSimpleTable(
                    array('User', 'Username', 'Email', 'Last Login', 'Registered'),
                    $recentLogins,
                    array(
                        function ($row) {
                            return '<strong>' . skaiAdminE($row['name']) . '</strong>';
                        },
                        function ($row) {
                            return skaiAdminE($row['username']);
                        },
                        function ($row) {
                            return skaiAdminE($row['email']);
                        },
                        function ($row) {
                            return skaiAdminE($row['lastvisitDate']);
                        },
                        function ($row) {
                            return skaiAdminE($row['registerDate']);
                        }
                    )
                );
                ?>
            [[/div]]
        [[/div]]
    [[/div]]

    [[div class="skai-section"]]
        [[div class="skai-section-head"]]
            [[h2 class="skai-section-title"]]Recent Saved Predictions[[/h2]]
            [[p class="skai-section-note"]]Latest prediction records with cleaner labels and easier scanning[[/p]]
        [[/div]]

        [[div class="skai-panel"]]
            [[p class="skai-panel-desc"]]This table shows the most recent saved predictions, what type they were, and whether match data is already available.[[/p]]
            <?php
            skaiAdminRenderSimpleTable(
                array('ID', 'User', 'Lottery', 'Type', 'Family', 'Source', 'Saved', 'Target Draw', 'Main Matches', 'Bonus Matches'),
                $recentPredictions,
                array(
                    function ($row) {
                        return skaiAdminN($row['id']);
                    },
                    function ($row) {
                        return skaiAdminE($row['user_name']);
                    },
                    function ($row) {
                        return skaiAdminE(skaiAdminCleanLotteryName($row['lottery_name']));
                    },
                    function ($row) {
                        return '<span class="skai-tag">' . skaiAdminE(skaiAdminFriendlyLabel($row['prediction_type'], 'prediction_type')) . '</span>';
                    },
                    function ($row) {
                        return skaiAdminE(skaiAdminFriendlyLabel($row['prediction_family'], 'prediction_family'));
                    },
                    function ($row) {
                        return skaiAdminE(skaiAdminFriendlyLabel($row['source'], 'source'));
                    },
                    function ($row) {
                        return skaiAdminE($row['date_saved']);
                    },
                    function ($row) {
                        return skaiAdminE($row['target_draw_date']);
                    },
                    function ($row) {
                        return ($row['main_matches'] !== null && $row['main_matches'] !== '') ? skaiAdminN($row['main_matches']) : '<span class="skai-muted">Not verified yet</span>';
                    },
                    function ($row) {
                        return ($row['bonus_matches'] !== null && $row['bonus_matches'] !== '') ? skaiAdminN($row['bonus_matches']) : '<span class="skai-muted">Not verified yet</span>';
                    }
                )
            );
            ?>
        [[/div]]
    [[/div]]

    [[div class="skai-section"]]
        [[div class="skai-section-head"]]
            [[h2 class="skai-section-title"]]Lottery Performance Table[[/h2]]
            [[p class="skai-section-note"]]Popularity plus hit visibility in one simpler table[[/p]]
        [[/div]]

        [[div class="skai-panel"]]
            [[p class="skai-panel-desc"]]This compares the most active lotteries by total saved predictions and hit visibility.[[/p]]
            <?php
            skaiAdminRenderSimpleTable(
                array('Lottery', 'Predictions', 'Full Hits', 'Near Hits'),
                $topLotteries,
                array(
                    function ($row) {
                        return '<strong>' . skaiAdminE(skaiAdminCleanLotteryName($row['lottery_name'])) . '</strong>';
                    },
                    function ($row) {
                        return skaiAdminN($row['total_predictions']);
                    },
                    function ($row) {
                        return skaiAdminN($row['full_hits']);
                    },
                    function ($row) {
                        return skaiAdminN($row['near_hits']);
                    }
                )
            );
            ?>
        [[/div]]
    [[/div]]

    [[div class="skai-section"]]
        [[div class="skai-section-head"]]
            [[h2 class="skai-section-title"]]System Status[[/h2]]
            [[p class="skai-section-note"]]Quick checks for platform coverage, verification, and tracking[[/p]]
        [[/div]]

        [[div class="skai-grid-2"]]
            [[div class="skai-panel"]]
                [[h3]]Health Checks[[/h3]]
                [[p class="skai-panel-desc"]]A quick summary of the most useful monitoring signals.[[/p]]
                [[div class="skai-health-list"]]
                    <?php foreach ($healthChecks as $item) : ?>
                        <div class="skai-health-row">
                            <div class="skai-health-label"><?php echo skaiAdminE($item['label']); ?></div>
                            <div class="skai-health-value"><?php echo skaiAdminN($item['value']); ?></div>
                        </div>
                    <?php endforeach; ?>
                [[/div]]
            [[/div]]

            [[div class="skai-panel"]]
                [[h3]]SKAI Table Row Counts[[/h3]]
                [[p class="skai-panel-desc"]]Shows the row totals in the tracked SKAI-related tables.[[/p]]
                <?php
                skaiAdminRenderSimpleTable(
                    array('Table', 'Rows'),
                    $skaiCounts,
                    array(
                        function ($row) {
                            return skaiAdminE($row['table_name']);
                        },
                        function ($row) {
                            return skaiAdminN($row['total_rows']);
                        }
                    )
                );
                ?>
            [[/div]]
        [[/div]]
    [[/div]]

    [[div class="skai-footnote"]]
        [[strong]]Phase 1 polished note:[[/strong]] this version keeps the same core data sources but improves readability by translating raw labels, clarifying section purposes, and making the dashboard easier for a regular person to scan quickly.
    [[/div]]
[[/div]]
{/source}
