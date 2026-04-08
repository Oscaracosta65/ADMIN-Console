[[source]]
<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

$app  = Factory::getApplication();
$user = Factory::getUser();
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
        'pick_expr'      => $pickExpr,
        'full_hit_expr'  => "CASE WHEN {$pickExpr} > 0 AND {$mainExpr} >= {$pickExpr} THEN 1 ELSE 0 END",
        'near_hit_expr'  => "CASE WHEN {$pickExpr} > 1 AND {$mainExpr} = ({$pickExpr} - 1) THEN 1 ELSE 0 END"
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
            'ai_prediction'   => 'AI Prediction',
            'skai_prediction' => 'SKAI Prediction',
            'manual'          => 'Manual Entry',
            'saved'           => 'Saved Prediction',
            'system'          => 'System Generated',
            'user'            => 'User Entry'
        ),
        'prediction_type' => array(
            'pick3'        => 'Pick 3',
            'pick4'        => 'Pick 4',
            'pick5'        => 'Pick 5',
            'pick6'        => 'Pick 6',
            'powerball'    => 'Powerball',
            'euromillions' => 'EuroMillions',
            'megamillions' => 'Mega Millions',
            'daily4'       => 'Daily 4',
            'daily3'       => 'Daily 3',
            'daily'        => 'Daily Lottery',
            'lotto'        => 'Standard Lottery',
            'regular'      => 'Standard Lottery'
        ),
        'prediction_family' => array(
            'regular' => 'Standard Lottery',
            'daily'   => 'Daily Lottery',
            'lotto'   => 'Standard Lottery'
        ),
        'risk_profile' => array(
            'balanced'     => 'Balanced',
            'conservative' => 'Conservative',
            'explorative'  => 'Exploratory',
            'explorer'     => 'Exploratory',
            'aggressive'   => 'Aggressive',
            'moderate'     => 'Moderate'
        ),
        'strategy' => array(
            'balanced'      => 'Balanced',
            'balanced_mix'  => 'Balanced Mix',
            'balanced_mix25'=> 'Balanced Mix 25',
            'skip_pattern'  => 'Skip Pattern',
            'ai_forward'    => 'AI Forward',
            'hybrid'        => 'Hybrid',
            'random'        => 'Random'
        ),
        'skai_run_mode' => array(
            'balanced' => 'Balanced',
            'hybrid'   => 'Hybrid',
            'manual'   => 'Manual',
            'auto'     => 'Automatic'
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
        echo '[[div class="skai-empty"]]No data yet.[[/div]]';
        return;
    }
    echo '[[div class="skai-bars"]]';
    foreach ($rows as $row) {
        $label = isset($row[$labelKey]) ? $row[$labelKey] : '';
        $value = isset($row[$valueKey]) ? (float) $row[$valueKey] : 0;
        $width = max(3, (int) round(($value / $max) * 100));
        echo '[[div class="skai-bar-row"]]';
        echo '[[div class="skai-bar-top"]]';
        echo '[[div class="skai-bar-label" title="' . skaiAdminE($label) . '"]]' . skaiAdminE($label) . '[[/div]]';
        echo '[[div class="skai-bar-value"]]' . call_user_func($formatterCallback, $value) . '[[/div]]';
        echo '[[/div]]';
        echo '[[div class="skai-bar-track"]][[span class="skai-bar-fill" style="width:' . (int) $width . '%;"]][[/span]][[/div]]';
        echo '[[/div]]';
    }
    echo '[[/div]]';
}

function skaiAdminRenderSimpleTable($headers, $rows, $map)
{
    if (empty($rows)) {
        echo '[[div class="skai-empty"]]No data yet.[[/div]]';
        return;
    }
    echo '[[div class="skai-table-wrap"]]';
    echo '[[table class="skai-table"]]';
    echo '[[thead]][[tr]]';
    foreach ($headers as $header) {
        echo '[[th]]' . skaiAdminE($header) . '[[/th]]';
    }
    echo '[[/tr]][[/thead]]';
    echo '[[tbody]]';
    foreach ($rows as $row) {
        echo '[[tr]]';
        foreach ($map as $cellCallback) {
            echo '[[td]]' . call_user_func($cellCallback, $row) . '[[/td]]';
        }
        echo '[[/tr]]';
    }
    echo '[[/tbody]]';
    echo '[[/table]]';
    echo '[[/div]]';
}

function skaiAdminRatio($part, $whole, $decimals = 2)
{
    $part  = (float) $part;
    $whole = (float) $whole;
    if ($whole <= 0) { return number_format(0, (int) $decimals); }
    return number_format($part / $whole, (int) $decimals);
}

function skaiAdminSkaiTableFriendlyName($rawName)
{
    $base = preg_replace('/^[a-z0-9]+_skai_/', 'skai_', strtolower($rawName));
    $map = array(
        'skai_backtest_summary'      => 'Backtest Summary',
        'skai_best_settings'         => 'Best Settings',
        'skai_calibration_bins'      => 'Calibration Bins',
        'skai_learning_performance'  => 'Learning Performance',
        'skai_lottery_settings'      => 'Lottery Settings',
        'skai_parameter_sets'        => 'Parameter Sets',
        'skai_runtime_audit'         => 'Runtime Audit Log',
        'skai_run_log'               => 'Run Log',
        'skai_shared_learning'       => 'Shared Learning',
        'skai_skip_hit_learning'     => 'Skip Hit Learning',
        'skai_skip_hit_outcomes'     => 'Skip Hit Outcomes',
        'skai_skip_hit_runs'         => 'Skip Hit Runs',
        'skai_tail_recovery_summary' => 'Tail Recovery Summary',
        'skai_user_preferences'      => 'User Preferences',
    );
    if (isset($map[$base])) { return $map[$base]; }
    $parts = explode('_', $base);
    array_shift($parts);
    return ucwords(implode(' ', $parts));
}

function skaiAdminInferPredictionType($row)
{
    $pt = isset($row['prediction_type']) ? trim((string) $row['prediction_type']) : '';
    if ($pt !== '' && strtolower($pt) !== 'unknown' && strtolower($pt) !== 'null') {
        return skaiAdminFriendlyLabel($pt, 'prediction_type');
    }
    $dps = isset($row['daily_pick_size'])  ? (int) $row['daily_pick_size']  : 0;
    $tps = isset($row['target_pick_size']) ? (int) $row['target_pick_size'] : 0;
    $ps  = $dps > 0 ? $dps : $tps;
    if ($ps === 3) { return 'Pick 3'; }
    if ($ps === 4) { return 'Pick 4'; }
    if ($ps === 5) { return 'Pick 5'; }
    if ($ps === 6) { return 'Pick 6'; }
    if ($ps > 0)   { return 'Pick ' . $ps; }
    $rh       = isset($row['renderer_hint'])   ? strtolower((string) $row['renderer_hint'])   : '';
    $dv       = isset($row['display_variant']) ? strtolower((string) $row['display_variant']) : '';
    $combined = $rh . $dv;
    if (strpos($combined, 'pick3') !== false || strpos($combined, 'pick_3') !== false)      { return 'Pick 3'; }
    if (strpos($combined, 'pick4') !== false || strpos($combined, 'pick_4') !== false)      { return 'Pick 4'; }
    if (strpos($combined, 'pick5') !== false || strpos($combined, 'pick_5') !== false)      { return 'Pick 5'; }
    if (strpos($combined, 'pick6') !== false || strpos($combined, 'pick_6') !== false)      { return 'Pick 6'; }
    if (strpos($combined, 'powerball') !== false)                                           { return 'Powerball'; }
    if (strpos($combined, 'euromillions') !== false)                                        { return 'EuroMillions'; }
    if (strpos($combined, 'megamillions') !== false || strpos($combined, 'mega') !== false) { return 'Mega Millions'; }
    if (strpos($combined, 'daily') !== false)                                               { return 'Daily Lottery'; }
    $pf = isset($row['prediction_family']) ? strtolower(trim((string) $row['prediction_family'])) : '';
    if ($pf === 'daily')   { return 'Daily Lottery'; }
    if ($pf === 'regular') { return 'Standard Lottery'; }
    return 'Not categorized';
}

function skaiAdminInferPredictionFamily($row)
{
    $pf = isset($row['prediction_family']) ? trim((string) $row['prediction_family']) : '';
    if ($pf !== '' && strtolower($pf) !== 'unknown' && strtolower($pf) !== 'null') {
        return skaiAdminFriendlyLabel($pf, 'prediction_family');
    }
    $dps = isset($row['daily_pick_size']) ? (int) $row['daily_pick_size'] : 0;
    if ($dps > 0) { return 'Daily Lottery'; }
    $pt = isset($row['prediction_type']) ? strtolower(trim((string) $row['prediction_type'])) : '';
    $dailyTypes    = array('daily', 'daily3', 'daily4', 'pick3', 'pick4');
    $standardTypes = array('regular', 'lotto', 'powerball', 'euromillions', 'megamillions', 'pick5', 'pick6');
    if (in_array($pt, $dailyTypes, true))    { return 'Daily Lottery'; }
    if (in_array($pt, $standardTypes, true)) { return 'Standard Lottery'; }
    $tec = isset($row['target_extra_count']) ? (int) $row['target_extra_count'] : 0;
    if ($tec > 0) { return 'Standard Lottery'; }
    return 'Not categorized';
}

function skaiAdminInferSource($row)
{
    $src = isset($row['source']) ? trim((string) $row['source']) : '';
    if ($src !== '' && strtolower($src) !== 'unknown' && strtolower($src) !== 'null') {
        return skaiAdminFriendlyLabel($src, 'source');
    }
    $srm = isset($row['skai_run_mode']) ? trim((string) $row['skai_run_mode']) : '';
    $sri = isset($row['skai_run_id'])   ? trim((string) $row['skai_run_id'])   : '';
    if ($srm !== '' || $sri !== '') { return 'SKAI Prediction'; }
    $ais = isset($row['ai_score'])      ? $row['ai_score']      : null;
    $aim = isset($row['ai_model'])      ? $row['ai_model']      : null;
    $aic = isset($row['ai_confidence']) ? $row['ai_confidence'] : null;
    if ($ais !== null || $aim !== null || $aic !== null) { return 'AI Prediction'; }
    return 'Not categorized';
}

function skaiAdminInferLotteryDisplay($row)
{
    $ln  = isset($row['lottery_name'])    ? trim((string) $row['lottery_name'])    : '';
    $jid = isset($row['lottery_join_id']) ? (int) $row['lottery_join_id']          : 0;
    if ($ln !== '') { return skaiAdminCleanLotteryName($ln); }
    if ($jid > 0)   { return 'Lottery #' . $jid; }
    return 'Unknown Lottery';
}

function skaiAdminRenderBarsWithPct($rows, $labelKey, $valueKey, $total)
{
    $max   = skaiAdminSafeMaxForBars($rows, $valueKey);
    $total = (float) $total;
    if (empty($rows)) {
        echo '[[div class="skai-empty"]]No data yet.[[/div]]';
        return;
    }
    echo '[[div class="skai-bars"]]';
    foreach ($rows as $row) {
        $label = isset($row[$labelKey]) ? $row[$labelKey] : '';
        $value = isset($row[$valueKey]) ? (float) $row[$valueKey] : 0;
        $width = max(3, (int) round(($value / $max) * 100));
        $pct   = ($total > 0) ? number_format(($value / $total) * 100, 1) . '%' : '';
        $badge = skaiAdminN($value) . ($pct !== '' ? ' [[span class="skai-bar-pct"]](' . skaiAdminE($pct) . ')[[/span]]' : '');
        echo '[[div class="skai-bar-row"]]';
        echo '[[div class="skai-bar-top"]]';
        echo '[[div class="skai-bar-label" title="' . skaiAdminE($label) . '"]]' . skaiAdminE($label) . '[[/div]]';
        echo '[[div class="skai-bar-value"]]' . $badge . '[[/div]]';
        echo '[[/div]]';
        echo '[[div class="skai-bar-track"]][[span class="skai-bar-fill" style="width:' . (int) $width . '%;"]][[/span]][[/div]]';
        echo '[[/div]]';
    }
    echo '[[/div]]';
}

function skaiAdminRenderInsightCards($cards)
{
    if (empty($cards)) { return; }
    echo '[[div class="skai-insight-row"]]';
    foreach ($cards as $card) {
        $title = isset($card['title']) ? $card['title'] : '';
        $value = isset($card['value']) ? $card['value'] : '';
        $desc  = isset($card['desc'])  ? $card['desc']  : '';
        $flag  = isset($card['flag'])  ? $card['flag']  : 'neutral';
        $cls   = 'skai-insight-card skai-insight-' . skaiAdminE($flag);
        echo '[[div class="' . $cls . '"]]';
        echo '[[div class="skai-insight-title"]]' . skaiAdminE($title) . '[[/div]]';
        echo '[[div class="skai-insight-value"]]' . skaiAdminE($value) . '[[/div]]';
        echo '[[div class="skai-insight-desc"]]'  . skaiAdminE($desc)  . '[[/div]]';
        echo '[[/div]]';
    }
    echo '[[/div]]';
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

$predDateCol        = skaiAdminHasColumn($predCols, 'date_saved')      ? 'date_saved'      : '';
$userLoginCol       = skaiAdminHasColumn($userCols, 'lastvisitDate')   ? 'lastvisitDate'   : '';
$userRegisterCol    = skaiAdminHasColumn($userCols, 'registerDate')    ? 'registerDate'    : '';
$subToDateCol       = skaiAdminHasColumn($subCols,  'to_date')         ? 'to_date'         : '';
$subPublishedCol    = skaiAdminHasColumn($subCols,  'published')       ? 'published'       : '';
$subAmountCol       = skaiAdminHasColumn($subCols,  'amount')          ? 'amount'          : '';
$subPaymentAmountCol= skaiAdminHasColumn($subCols,  'payment_amount')  ? 'payment_amount'  : '';
$subCreatedCol      = skaiAdminHasColumn($subCols,  'created_date')    ? 'created_date'    : '';
$predLotteryJoinCol = skaiAdminHasColumn($predCols, 'target_lottery_id') ? 'target_lottery_id' : (skaiAdminHasColumn($predCols, 'lottery_id') ? 'lottery_id' : '');
$lotteryPkCol       = skaiAdminHasColumn($lotCols,  'lottery_id')      ? 'lottery_id'      : 'id';

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

$predTargetDrawDateCol = skaiAdminHasColumn($predCols, 'target_draw_date') ? 'target_draw_date' : '';
$hasDailyPickSize      = skaiAdminHasColumn($predCols, 'daily_pick_size');
$hasTargetPickSize     = skaiAdminHasColumn($predCols, 'target_pick_size');
$hasPredType           = skaiAdminHasColumn($predCols, 'prediction_type');
$hasPredFamily         = skaiAdminHasColumn($predCols, 'prediction_family');
$hasSourceCol          = skaiAdminHasColumn($predCols, 'source');
$hasSkaiRunMode        = skaiAdminHasColumn($predCols, 'skai_run_mode');
$hasSkaiRunId          = skaiAdminHasColumn($predCols, 'skai_run_id');

$unverifiedClause = count($verifiedWhere) ? 'NOT (' . implode(' OR ', $verifiedWhere) . ')' : '1=1';

$inferTypeExpr = "'unknown'";
if ($hasPredType) {
    $caseWhenParts = array();
    $caseWhenParts[] = "WHEN (prediction_type IS NOT NULL AND prediction_type <> '' AND LOWER(prediction_type) NOT IN ('unknown','null')) THEN prediction_type";
    if ($hasDailyPickSize) {
        $caseWhenParts[] = "WHEN daily_pick_size = 3 THEN 'pick3'";
        $caseWhenParts[] = "WHEN daily_pick_size = 4 THEN 'pick4'";
        $caseWhenParts[] = "WHEN daily_pick_size = 5 THEN 'pick5'";
    }
    if ($hasTargetPickSize) {
        $caseWhenParts[] = "WHEN target_pick_size = 3 THEN 'pick3'";
        $caseWhenParts[] = "WHEN target_pick_size = 4 THEN 'pick4'";
        $caseWhenParts[] = "WHEN target_pick_size = 5 THEN 'pick5'";
    }
    if ($hasPredFamily) {
        $caseWhenParts[] = "WHEN prediction_family = 'daily' THEN 'daily'";
        $caseWhenParts[] = "WHEN prediction_family = 'regular' THEN 'regular'";
    }
    $inferTypeExpr = "CASE " . implode(" ", $caseWhenParts) . " ELSE 'unknown' END";
}

$inferSourceExpr = "'unknown'";
if ($hasSourceCol) {
    $srcParts = array();
    $srcParts[] = "WHEN (source IS NOT NULL AND source <> '' AND LOWER(source) NOT IN ('unknown','null')) THEN source";
    if ($hasSkaiRunMode) {
        $srcParts[] = "WHEN (skai_run_mode IS NOT NULL AND skai_run_mode <> '') THEN 'skai_prediction'";
    }
    if ($hasSkaiRunId) {
        $srcParts[] = "WHEN skai_run_id IS NOT NULL THEN 'skai_prediction'";
    }
    $inferSourceExpr = "CASE " . implode(" ", $srcParts) . " ELSE 'unknown' END";
}

$inferFamilyExpr = "'unknown'";
if ($hasPredFamily) {
    $famParts = array();
    $famParts[] = "WHEN (prediction_family IS NOT NULL AND prediction_family <> '' AND LOWER(prediction_family) NOT IN ('unknown','null')) THEN prediction_family";
    if ($hasDailyPickSize) {
        $famParts[] = "WHEN (daily_pick_size IS NOT NULL AND daily_pick_size > 0) THEN 'daily'";
    }
    if ($hasPredType) {
        $famParts[] = "WHEN prediction_type IN ('pick3','pick4','daily3','daily4') THEN 'daily'";
        $famParts[] = "WHEN prediction_type IN ('regular','lotto','powerball','euromillions') THEN 'regular'";
    }
    $inferFamilyExpr = "CASE " . implode(" ", $famParts) . " ELSE 'unknown' END";
}

$predictionTypes = array();
if (skaiAdminTableExists($db, $tblPredictions)) {
    $predictionTypes = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT " . $inferTypeExpr . " AS label,
                    COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY " . $inferTypeExpr . "
             ORDER BY total DESC, label ASC
             LIMIT 15"
        ),
        'label', 'total', 'prediction_type'
    );
}

$predictionFamilies = array();
if (skaiAdminTableExists($db, $tblPredictions)) {
    $predictionFamilies = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT " . $inferFamilyExpr . " AS label,
                    COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY " . $inferFamilyExpr . "
             ORDER BY total DESC, label ASC
             LIMIT 10"
        ),
        'label', 'total', 'prediction_family'
    );
}

$predictionSources = array();
if (skaiAdminTableExists($db, $tblPredictions)) {
    $predictionSources = skaiAdminNormalizeBreakdownRows(
        skaiAdminLoadAssocList(
            $db,
            "SELECT " . $inferSourceExpr . " AS label,
                    COUNT(*) AS total
             FROM " . $db->quoteName($tblPredictions) . "
             GROUP BY " . $inferSourceExpr . "
             ORDER BY total DESC, label ASC
             LIMIT 10"
        ),
        'label', 'total', 'source'
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
        'label', 'total', 'skai_run_mode'
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
        'label', 'total', 'strategy'
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
        'label', 'total', 'risk_profile'
    );
}

$topLotteries = array();
if (skaiAdminTableExists($db, $tblLotteries) && skaiAdminTableExists($db, $tblPredictions) && $predLotteryJoinCol !== '') {
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

foreach ($topLotteries as &$row) {
    $tp = (int) $row['total_predictions'];
    $row['hit_ratio_pct'] = $tp > 0 ? number_format(((float) $row['full_hits'] / $tp) * 100, 1) . '%' : '0.0%';
}
unset($row);

$topLotteriesByHits = $topLotteries;
usort($topLotteriesByHits, function ($a, $b) {
    return (int) $b['full_hits'] - (int) $a['full_hits'];
});
$topLotteriesByHits = array_filter($topLotteriesByHits, function ($r) { return (int) $r['full_hits'] > 0; });
$topLotteriesByHits = array_values($topLotteriesByHits);

$topLotteriesByNearHits = $topLotteries;
usort($topLotteriesByNearHits, function ($a, $b) {
    return (int) $b['near_hits'] - (int) $a['near_hits'];
});
$topLotteriesByNearHits = array_filter($topLotteriesByNearHits, function ($r) { return (int) $r['near_hits'] > 0; });
$topLotteriesByNearHits = array_values($topLotteriesByNearHits);

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

$recentPredictions = array();
if (skaiAdminTableExists($db, $tblPredictions)) {
    $selectParts = array(
        'p.id',
        skaiAdminHasColumn($predCols, 'label')            ? 'p.label'            : "'' AS label",
        skaiAdminHasColumn($predCols, 'source')           ? 'p.source'           : "'' AS source",
        skaiAdminHasColumn($predCols, 'prediction_type')  ? 'p.prediction_type'  : "'' AS prediction_type",
        skaiAdminHasColumn($predCols, 'prediction_family')? 'p.prediction_family': "'' AS prediction_family",
        skaiAdminHasColumn($predCols, 'main_matches')     ? 'p.main_matches'     : 'NULL AS main_matches',
        skaiAdminHasColumn($predCols, 'bonus_matches')    ? 'p.bonus_matches'    : 'NULL AS bonus_matches',
        skaiAdminHasColumn($predCols, 'target_draw_date') ? 'p.target_draw_date' : 'NULL AS target_draw_date',
        skaiAdminHasColumn($predCols, 'date_saved')       ? 'p.date_saved'       : 'NULL AS date_saved',
        skaiAdminHasColumn($predCols, 'user_id')          ? 'p.user_id'          : 'NULL AS user_id',
        ($predLotteryJoinCol !== '') ? 'p.' . $db->quoteName($predLotteryJoinCol) . ' AS lottery_join_id' : 'NULL AS lottery_join_id',
        'u.name AS user_name',
        'l.name AS lottery_name',
        skaiAdminHasColumn($predCols, 'daily_pick_size')  ? 'p.daily_pick_size'  : '0 AS daily_pick_size',
        skaiAdminHasColumn($predCols, 'target_pick_size') ? 'p.target_pick_size' : '0 AS target_pick_size',
        skaiAdminHasColumn($predCols, 'skai_run_mode')    ? 'p.skai_run_mode'    : "'' AS skai_run_mode",
        skaiAdminHasColumn($predCols, 'skai_run_id')      ? 'p.skai_run_id'      : 'NULL AS skai_run_id'
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
        $rawName = $db->replacePrefix($skaiTable);
        $skaiCounts[] = array(
            'table_name'    => $rawName,
            'friendly_name' => skaiAdminSkaiTableFriendlyName($rawName),
            'total_rows'    => (int) skaiAdminLoadScalar($db, "SELECT COUNT(*) FROM " . $db->quoteName($skaiTable))
        );
    }
}
usort($skaiCounts, function ($a, $b) { return $b['total_rows'] - $a['total_rows']; });

$skaiTotalRows       = array_sum(array_column($skaiCounts, 'total_rows'));
$skaiActiveTableCount = count(array_filter($skaiCounts, function ($r) { return $r['total_rows'] > 0; }));
$skaiEmptyTableCount  = count($skaiCounts) - $skaiActiveTableCount;
$skaiEmptyTables     = array_values(array_filter($skaiCounts, function ($r) { return $r['total_rows'] === 0; }));

$distinctPredLotteries = 0;
if ($predLotteryJoinCol !== '') {
    $distinctPredLotteries = (int) skaiAdminLoadScalar(
        $db,
        "SELECT COUNT(DISTINCT " . $db->quoteName($predLotteryJoinCol) . ") FROM " . $db->quoteName($tblPredictions)
    );
}

$lotteryZeroPreds = array();
if (skaiAdminTableExists($db, $tblLotteries) && skaiAdminTableExists($db, $tblPredictions) && $predLotteryJoinCol !== '') {
    $lotteryZeroPreds = skaiAdminLoadAssocList(
        $db,
        "SELECT l." . $db->quoteName($lotteryPkCol) . " AS lottery_id, l." . $db->quoteName('name') . " AS lottery_name
         FROM " . $db->quoteName($tblLotteries) . " AS l
         LEFT JOIN " . $db->quoteName($tblPredictions) . " AS p
             ON p." . $db->quoteName($predLotteryJoinCol) . " = l." . $db->quoteName($lotteryPkCol) . "
         WHERE p." . $db->quoteName($predLotteryJoinCol) . " IS NULL
         ORDER BY l." . $db->quoteName('name') . " ASC
         LIMIT 20"
    );
}

$heavyUnverifiedLotteries = array();
if (skaiAdminTableExists($db, $tblLotteries) && skaiAdminTableExists($db, $tblPredictions) && $predLotteryJoinCol !== '') {
    $lotteryVerifiedExpr = str_replace(
        array('main_matches', 'bonus_matches', 'matched_numbers'),
        array('p.main_matches', 'p.bonus_matches', 'p.matched_numbers'),
        $verifiedClause
    );
    $heavyRaw = skaiAdminLoadAssocList(
        $db,
        "SELECT
             l." . $db->quoteName($lotteryPkCol) . " AS lottery_id,
             l." . $db->quoteName('name') . " AS lottery_name,
             COUNT(p.id) AS total_predictions,
             COALESCE(SUM(CASE WHEN (" . $lotteryVerifiedExpr . ") THEN 1 ELSE 0 END), 0) AS verified_count
         FROM " . $db->quoteName($tblLotteries) . " AS l
         INNER JOIN " . $db->quoteName($tblPredictions) . " AS p
             ON p." . $db->quoteName($predLotteryJoinCol) . " = l." . $db->quoteName($lotteryPkCol) . "
         GROUP BY l." . $db->quoteName($lotteryPkCol) . ", l." . $db->quoteName('name') . "
         HAVING COUNT(p.id) >= 5
         ORDER BY total_predictions DESC
         LIMIT 20"
    );
    foreach ($heavyRaw as $r) {
        $tp   = (int) $r['total_predictions'];
        $vc   = (int) $r['verified_count'];
        $rate = $tp > 0 ? $vc / $tp : 1;
        if ($rate < 0.30) {
            $r['verified_pct']     = skaiAdminPercent($vc, $tp);
            $r['unverified_count'] = $tp - $vc;
            $heavyUnverifiedLotteries[] = $r;
        }
    }
}

$usersNoPredictions = 0;
if (skaiAdminTableExists($db, $tblUsers) && skaiAdminTableExists($db, $tblPredictions) && skaiAdminHasColumn($predCols, 'user_id')) {
    $usersNoPredictions = (int) skaiAdminLoadScalar(
        $db,
        "SELECT COUNT(u.id)
         FROM " . $db->quoteName($tblUsers) . " AS u
         LEFT JOIN " . $db->quoteName($tblPredictions) . " AS p ON p.user_id = u.id
         WHERE p.user_id IS NULL"
    );
}

$topTypesByFullHit = array();
$topTypesByNearHit = array();
if (skaiAdminTableExists($db, $tblPredictions)) {
    if ($hasPredType || $hasDailyPickSize || $hasTargetPickSize) {
        $topTypesByFullHit = skaiAdminNormalizeBreakdownRows(
            skaiAdminLoadAssocList(
                $db,
                "SELECT " . $inferTypeExpr . " AS label,
                        COALESCE(SUM(" . $fullHitExpr . "), 0) AS total
                 FROM " . $db->quoteName($tblPredictions) . "
                 GROUP BY " . $inferTypeExpr . "
                 HAVING COALESCE(SUM(" . $fullHitExpr . "), 0) > 0
                 ORDER BY total DESC, label ASC
                 LIMIT 12"
            ),
            'label', 'total', 'prediction_type'
        );
        $topTypesByNearHit = skaiAdminNormalizeBreakdownRows(
            skaiAdminLoadAssocList(
                $db,
                "SELECT " . $inferTypeExpr . " AS label,
                        COALESCE(SUM(" . $nearHitExpr . "), 0) AS total
                 FROM " . $db->quoteName($tblPredictions) . "
                 GROUP BY " . $inferTypeExpr . "
                 HAVING COALESCE(SUM(" . $nearHitExpr . "), 0) > 0
                 ORDER BY total DESC, label ASC
                 LIMIT 12"
            ),
            'label', 'total', 'prediction_type'
        );
    }
}

$staleRecordsCount = 0;
if (skaiAdminTableExists($db, $tblPredictions) && $predTargetDrawDateCol !== '') {
    $staleRecordsCount = (int) skaiAdminLoadScalar(
        $db,
        "SELECT COUNT(*) FROM " . $db->quoteName($tblPredictions) . "
         WHERE " . $db->quoteName($predTargetDrawDateCol) . " < NOW()
           AND (" . $unverifiedClause . ")"
    );
}

$dqUnknownPredType    = 0;
$dqInferrablePredType = 0;
if ($hasPredType && skaiAdminTableExists($db, $tblPredictions)) {
    $dqUnknownPredType = (int) skaiAdminLoadScalar(
        $db,
        "SELECT COUNT(*) FROM " . $db->quoteName($tblPredictions) . "
         WHERE prediction_type IS NULL OR prediction_type = '' OR LOWER(prediction_type) IN ('unknown','null')"
    );
    if ($dqUnknownPredType > 0 && ($hasDailyPickSize || $hasTargetPickSize)) {
        $dqInferrableParts = array();
        if ($hasDailyPickSize)  { $dqInferrableParts[] = "(daily_pick_size IS NOT NULL AND daily_pick_size > 0)"; }
        if ($hasTargetPickSize) { $dqInferrableParts[] = "(target_pick_size IS NOT NULL AND target_pick_size > 0)"; }
        $dqInferrableWhere = implode(' OR ', $dqInferrableParts);
        $dqInferrablePredType = (int) skaiAdminLoadScalar(
            $db,
            "SELECT COUNT(*) FROM " . $db->quoteName($tblPredictions) . "
             WHERE (prediction_type IS NULL OR prediction_type = '' OR LOWER(prediction_type) IN ('unknown','null'))
               AND (" . $dqInferrableWhere . ")"
        );
    }
}
$dqTrulyUnclassifiable   = max(0, $dqUnknownPredType - $dqInferrablePredType);
$recordsWithMatchData    = (int) $summary['verified_predictions'];
$recordsWithoutMatchData = max(0, (int) $summary['total_predictions'] - $recordsWithMatchData);

$derived = array();
$derived['users_inactive_30d']    = max(0, (int) $summary['total_users'] - (int) $summary['users_active_30d']);
$derived['activity_rate_pct']     = skaiAdminPercent($summary['users_active_30d'], $summary['total_users']);
$derived['sub_penetration_pct']   = skaiAdminPercent($summary['active_subscriptions'], $summary['total_users']);
$derived['avg_preds_active_user'] = skaiAdminRatio($summary['total_predictions'], $summary['users_active_30d']);
$derived['avg_preds_total_user']  = skaiAdminRatio($summary['total_predictions'], $summary['total_users']);
$derived['preds_per_day_30d']     = number_format((float) $summary['predictions_30d'] / 30, 1);
$derived['awaiting_verification'] = max(0, (int) $summary['total_predictions'] - (int) $summary['verified_predictions']);
$derived['verification_rate_pct'] = skaiAdminPercent($summary['verified_predictions'], $summary['total_predictions']);
$derived['full_hit_rate_pct']     = skaiAdminPercent($summary['full_hits'], $summary['verified_predictions']);
$derived['near_hit_rate_pct']     = skaiAdminPercent($summary['near_hits'], $summary['verified_predictions']);
$derived['hit_rate_of_total_pct'] = skaiAdminPercent($summary['full_hits'], $summary['total_predictions']);
$derived['lottery_activity_rate'] = skaiAdminPercent($summary['active_lotteries_30d'], $summary['total_lotteries']);
$derived['lotteries_zero_preds']  = max(0, (int) $summary['total_lotteries'] - $distinctPredLotteries);

$largestUncategorizedBucket = 'Not detected';
$largestUncategorizedCount  = 0;
foreach ($predictionTypes as $pt) {
    if ($pt['label'] === 'Not categorized' && (int) $pt['total'] > $largestUncategorizedCount) {
        $largestUncategorizedCount  = (int) $pt['total'];
        $largestUncategorizedBucket = 'Prediction Types: ' . skaiAdminN($largestUncategorizedCount) . ' records';
    }
}

$topInsightCards = array();

$card1Val  = !empty($topLotteries) ? skaiAdminCleanLotteryName($topLotteries[0]['lottery_name']) : 'No data';
$card1Desc = !empty($topLotteries) ? skaiAdminN($topLotteries[0]['total_predictions']) . ' saved predictions this platform' : 'No lottery predictions recorded yet';
$topInsightCards[] = array('title' => 'Most Active Lottery', 'value' => $card1Val, 'desc' => $card1Desc, 'flag' => 'positive');

if (!empty($heavyUnverifiedLotteries)) {
    $hv = $heavyUnverifiedLotteries[0];
    $topInsightCards[] = array(
        'title' => 'Highest Verification Gap',
        'value' => skaiAdminCleanLotteryName($hv['lottery_name']),
        'desc'  => $hv['verified_pct'] . ' verified of ' . skaiAdminN($hv['total_predictions']) . ' predictions',
        'flag'  => 'warn'
    );
} else {
    $topInsightCards[] = array(
        'title' => 'Highest Verification Gap',
        'value' => 'None detected',
        'desc'  => 'All active lotteries have acceptable verification rates',
        'flag'  => 'positive'
    );
}

$topInsightCards[] = array(
    'title' => 'User Activity Signal',
    'value' => $derived['activity_rate_pct'] . ' active (30d)',
    'desc'  => skaiAdminN($summary['users_active_30d']) . ' of ' . skaiAdminN($summary['total_users']) . ' total users active in past 30 days',
    'flag'  => ((float) $summary['users_active_30d'] / max(1, (float) $summary['total_users'])) > 0.10 ? 'positive' : 'warn'
);

$topInsightCards[] = array(
    'title' => 'Largest Uncategorized Bucket',
    'value' => $largestUncategorizedBucket !== 'Not detected' ? skaiAdminN($largestUncategorizedCount) . ' records' : 'None detected',
    'desc'  => $largestUncategorizedBucket !== 'Not detected' ? $largestUncategorizedBucket : 'All records have classifiable prediction types',
    'flag'  => $largestUncategorizedCount > 0 ? 'warn' : 'positive'
);

$topInsightCards[] = array(
    'title' => 'Verification Coverage',
    'value' => $derived['verification_rate_pct'],
    'desc'  => skaiAdminN($summary['verified_predictions']) . ' verified, ' . skaiAdminN($derived['awaiting_verification']) . ' awaiting',
    'flag'  => (float) $summary['verified_predictions'] >= (float) $summary['total_predictions'] * 0.5 ? 'positive' : 'warn'
);

if ($staleRecordsCount > 0) {
    $topInsightCards[] = array(
        'title' => 'Biggest Data Quality Issue',
        'value' => skaiAdminN($staleRecordsCount) . ' stale records',
        'desc'  => 'Predictions past target draw date without verification results',
        'flag'  => 'warn'
    );
} elseif ($dqTrulyUnclassifiable > 0) {
    $topInsightCards[] = array(
        'title' => 'Biggest Data Quality Issue',
        'value' => skaiAdminN($dqTrulyUnclassifiable) . ' unclassifiable',
        'desc'  => 'Prediction type records that cannot be inferred from any available field',
        'flag'  => 'warn'
    );
} else {
    $topInsightCards[] = array(
        'title' => 'Data Quality Status',
        'value' => 'No critical issues',
        'desc'  => 'No stale or unclassifiable records detected at this time',
        'flag'  => 'positive'
    );
}

$healthChecks = array(
    array('label' => 'Predictions waiting for verification', 'value' => $derived['awaiting_verification'],      'flag' => $derived['awaiting_verification'] > 0 ? 'warn' : 'ok'),
    array('label' => 'Stale records past draw date',         'value' => $staleRecordsCount,                     'flag' => $staleRecordsCount > 0 ? 'warn' : 'ok'),
    array('label' => 'Lotteries with no saved predictions',  'value' => $derived['lotteries_zero_preds'],       'flag' => $derived['lotteries_zero_preds'] > 0 ? 'warn' : 'ok'),
    array('label' => 'Users with no predictions ever',       'value' => $usersNoPredictions,                    'flag' => 'neutral'),
    array('label' => 'Predictions saved in last 30 days',    'value' => (int) $summary['predictions_30d'],      'flag' => (int) $summary['predictions_30d'] > 0 ? 'ok' : 'warn'),
    array('label' => 'Users active in last 30 days',         'value' => (int) $summary['users_active_30d'],     'flag' => (int) $summary['users_active_30d'] > 0 ? 'ok' : 'warn'),
    array('label' => 'Active paid subscriptions',            'value' => (int) $summary['active_subscriptions'], 'flag' => (int) $summary['active_subscriptions'] > 0 ? 'ok' : 'warn'),
    array('label' => 'Empty SKAI tracking tables',           'value' => $skaiEmptyTableCount,                   'flag' => $skaiEmptyTableCount > 0 ? 'warn' : 'ok'),
);
?>
[[style]]
.skai-admin-wrap {
    max-width: 1540px;
    margin: 24px auto 56px auto;
    padding: 0 20px;
    font-family: Arial, Helvetica, sans-serif;
    color: #111827;
    background: #f1f5f9;
}
.skai-header {
    margin-bottom: 20px;
    padding: 28px 32px;
    background: #ffffff;
    border: 1px solid #dbe6f0;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
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
.skai-title { margin: 0 0 8px 0; font-size: 34px; line-height: 1.15; font-weight: 800; color: #0f172a; }
.skai-subtitle { margin: 0; color: #475569; font-size: 15px; line-height: 1.5; max-width: 900px; }
.skai-section {
    margin: 0 0 20px 0;
    background: #ffffff;
    border: 1px solid #dbe6f0;
    border-radius: 20px;
    padding: 24px 28px 28px 28px;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.05);
}
.skai-section-head {
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f1f5f9;
}
.skai-section-title-row { display: flex; align-items: center; gap: 12px; margin-bottom: 5px; }
.skai-section-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: #eff6ff;
    color: #2563eb;
    font-size: 12px;
    font-weight: 800;
    flex-shrink: 0;
    border: 1px solid #bfdbfe;
}
.skai-section-title { margin: 0; font-size: 20px; line-height: 1.2; font-weight: 800; color: #0f172a; }
.skai-section-note { margin: 0 0 0 46px; color: #64748b; font-size: 13px; }
.skai-grid-cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin: 20px 0 24px 0; }
.skai-card { background: #ffffff; border: 1px solid #dbe2ea; border-radius: 16px; padding: 16px; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.04); }
.skai-kpi-group { margin: 0 0 18px 0; }
.skai-kpi-group:last-child { margin-bottom: 0; }
.skai-kpi-group-title { margin: 0 0 10px 0; font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 800; }
.skai-kpi-label { font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 10px; }
.skai-kpi-value { font-size: 30px; line-height: 1; font-weight: 800; color: #0f172a; margin-bottom: 8px; }
.skai-kpi-meta { font-size: 13px; color: #475569; }
.skai-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.skai-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.skai-panel { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 18px 20px; }
.skai-panel h3 { margin: 0 0 8px 0; font-size: 15px; line-height: 1.25; font-weight: 800; color: #0f172a; padding-bottom: 10px; border-bottom: 1px solid #e9eef5; }
.skai-panel-desc { margin: 0 0 14px 0; color: #64748b; font-size: 13px; line-height: 1.45; }
.skai-bars { display: flex; flex-direction: column; gap: 12px; }
.skai-bar-row { display: flex; flex-direction: column; gap: 6px; }
.skai-bar-top { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.skai-bar-label { font-size: 13px; font-weight: 700; color: #334155; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 75%; }
.skai-bar-value { font-size: 13px; font-weight: 700; color: #0f172a; white-space: nowrap; }
.skai-bar-track { width: 100%; height: 10px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
.skai-bar-fill { display: block; height: 100%; border-radius: 999px; background: linear-gradient(90deg, #2563eb 0%, #0ea5e9 100%); }
.skai-table-wrap { overflow-x: auto; }
.skai-table { width: 100%; border-collapse: collapse; min-width: 700px; }
.skai-table thead th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; padding: 11px 10px; border-bottom: 1px solid #dbe2ea; background: #f1f5f9; }
.skai-table tbody td { padding: 11px 10px; border-bottom: 1px solid #edf2f7; font-size: 13px; color: #1e293b; vertical-align: top; line-height: 1.45; }
.skai-table tbody tr:nth-child(even) { background: #fafbfd; }
.skai-table tbody tr:hover { background: #eff6ff; }
.skai-tag { display: inline-block; padding: 4px 8px; border-radius: 999px; background: #eff6ff; color: #1d4ed8; font-size: 12px; font-weight: 700; }
.skai-muted { color: #64748b; }
.skai-empty { padding: 16px; border: 1px dashed #cbd5e1; border-radius: 12px; color: #64748b; font-size: 14px; background: #f8fafc; }
.skai-health-list { display: flex; flex-direction: column; gap: 8px; }
.skai-health-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 11px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
.skai-health-row::before { content: ''; display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: #94a3b8; flex-shrink: 0; }
.skai-health-ok::before   { background: #16a34a; }
.skai-health-warn::before { background: #d97706; }
.skai-health-label { font-size: 13px; color: #334155; font-weight: 600; flex: 1; }
.skai-health-value { font-size: 14px; color: #0f172a; font-weight: 800; white-space: nowrap; }
.skai-footnote { margin-top: 18px; padding: 14px 16px; border: 1px solid #dbe2ea; border-radius: 14px; background: #f8fafc; color: #475569; font-size: 13px; line-height: 1.5; }
.skai-section-divider { display: none; }
.skai-insight-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; margin: 0 0 28px 0; }
.skai-insight-card { padding: 16px 18px; border-radius: 14px; border: 1px solid #e2e8f0; border-left-width: 4px; }
.skai-insight-positive { border-left-color: #16a34a; background: #f0fdf4; }
.skai-insight-warn     { border-left-color: #d97706; background: #fffbeb; }
.skai-insight-neutral  { border-left-color: #94a3b8; background: #f8fafc; }
.skai-insight-title { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 6px; }
.skai-insight-value { font-size: 16px; font-weight: 800; color: #0f172a; margin-bottom: 4px; line-height: 1.35; }
.skai-insight-desc  { font-size: 12px; color: #64748b; line-height: 1.4; }
.skai-kpi-row { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; margin: 0 0 20px 0; }
.skai-kpi-mini { background: #ffffff; border: 1px solid #dbe6f0; border-top: 3px solid #2563eb; border-radius: 12px; padding: 14px 16px; }
.skai-kpi-mini-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
.skai-kpi-mini-value { font-size: 26px; font-weight: 800; color: #0f172a; line-height: 1; margin-bottom: 4px; }
.skai-kpi-mini-value-sm { font-size: 16px; }
.skai-kpi-mini-sub   { font-size: 12px; color: #64748b; }
.skai-health-ok   .skai-health-value { color: #16a34a; }
.skai-health-warn .skai-health-value { color: #d97706; }
.skai-bar-pct { color: #64748b; font-weight: 400; font-size: 12px; }
.skai-list-plain { display: flex; flex-direction: column; gap: 6px; }
.skai-list-item  { padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 13px; color: #334155; }
.skai-derived-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin: 0 0 18px 0; }
.skai-derived-item  { background: #ffffff; border: 1px solid #e2e8f0; border-left: 3px solid #6366f1; border-radius: 10px; padding: 12px 14px; }
.skai-derived-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
.skai-derived-value { font-size: 22px; font-weight: 800; color: #1e293b; }
.skai-tag-warn { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 12px; font-weight: 700; }
.skai-tag-ok   { display: inline-block; padding: 3px 8px; border-radius: 999px; background: #dcfce7; color: #166534; font-size: 12px; font-weight: 700; }
.skai-stat-card { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 14px; padding: 20px; text-align: center; }
.skai-stat-card-value { font-size: 40px; font-weight: 800; color: #0369a1; line-height: 1; margin-bottom: 8px; }
.skai-stat-card-label { font-size: 13px; font-weight: 700; color: #0c4a6e; margin-bottom: 6px; }
.skai-stat-card-note  { font-size: 12px; color: #64748b; }
@media (max-width: 1400px) { .skai-grid-cards { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1200px) {
    .skai-grid-cards  { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .skai-insight-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .skai-kpi-row     { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .skai-derived-grid{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 980px) {
    .skai-grid-cards, .skai-grid-2, .skai-grid-3 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    .skai-insight-row  { grid-template-columns: repeat(1, minmax(0, 1fr)); }
    .skai-kpi-row      { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .skai-derived-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .skai-title { font-size: 28px; }
}
[[/style]]
[[div class="skai-admin-wrap"]]

[[div class="skai-header"]]
    [[span class="skai-eyebrow"]]SKAI Admin Intelligence Dashboard[[/span]]
    [[h1 class="skai-title"]]Platform Intelligence Overview[[/h1]]
    [[p class="skai-subtitle"]]Real-time analytics across users, predictions, lotteries, subscriptions, and SKAI learning data. All metrics are derived live from the database at render time.[[/p]]
[[/div]]

[[div class="skai-section-divider"]][[/div]]

<!-- Section 1: Executive Overview -->
[[div class="skai-section"]]
    [[div class="skai-section-head"]]
        [[div class="skai-section-title-row"]]
            [[span class="skai-section-num"]]01[[/span]]
            [[h2 class="skai-section-title"]]Executive Overview[[/h2]]
        [[/div]]
        [[p class="skai-section-note"]]Top-level intelligence signals and KPIs across the platform.[[/p]]
    [[/div]]
    <?php skaiAdminRenderInsightCards($topInsightCards); ?>
    [[div class="skai-grid-cards"]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Total Users[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['total_users']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]All registered accounts[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Active Users (30d)[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['users_active_30d']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]<?php echo skaiAdminE($derived['activity_rate_pct']); ?> of total[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]New Users (30d)[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['new_users_30d']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Registered in past 30 days[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Total Predictions[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['total_predictions']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]All saved predictions[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Predictions Today[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['predictions_today']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Saved in last 24 hours[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Predictions (7d)[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['predictions_7d']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Saved in last 7 days[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Verified Predictions[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['verified_predictions']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]<?php echo skaiAdminE($derived['verification_rate_pct']); ?> coverage[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Full Hits[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['full_hits']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]<?php echo skaiAdminE($derived['full_hit_rate_pct']); ?> of verified[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Active Lotteries (30d)[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['active_lotteries_30d']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]With predictions in 30 days[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Total Lotteries[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['total_lotteries']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Configured on platform[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Active Subscriptions[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['active_subscriptions']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]<?php echo skaiAdminE($derived['sub_penetration_pct']); ?> user penetration[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Total Revenue[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminMoney($summary['total_revenue']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Active subscriptions total[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Last Refresh[[/div]]
            [[div class="skai-kpi-mini-value skai-kpi-mini-value-sm"]]<?php echo date('H:i'); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]<?php echo date('Y-m-d'); ?> server time[[/div]]
        [[/div]]
    [[/div]]
[[/div]]

[[div class="skai-section-divider"]][[/div]]

<!-- Section 2: User and Membership Insights -->
[[div class="skai-section"]]
    [[div class="skai-section-head"]]
        [[div class="skai-section-title-row"]]
            [[span class="skai-section-num"]]02[[/span]]
            [[h2 class="skai-section-title"]]User and Membership Insights[[/h2]]
        [[/div]]
        [[p class="skai-section-note"]]Engagement metrics, derived user stats, and top prediction contributors.[[/p]]
    [[/div]]
    [[div class="skai-derived-grid"]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Activity Rate (30d)[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminE($derived['activity_rate_pct']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Subscription Penetration[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminE($derived['sub_penetration_pct']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Avg Preds / Active User[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminE($derived['avg_preds_active_user']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Avg Preds / Total User[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminE($derived['avg_preds_total_user']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Inactive Users (30d)[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminN($derived['users_inactive_30d']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Users With No Predictions[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminN($usersNoPredictions); ?>[[/div]]
        [[/div]]
    [[/div]]
    [[div class="skai-grid-2"]]
        [[div class="skai-panel"]]
            [[h3]]Top Users by Prediction Count[[/h3]]
            [[p class="skai-panel-desc"]]Users who have saved the most predictions on the platform.[[/p]]
            <?php
            skaiAdminRenderSimpleTable(
                array('User', 'Username', 'Predictions', 'Last Activity'),
                $topUsers,
                array(
                    function($r) { return skaiAdminE($r['user_name']); },
                    function($r) { return '[[span class="skai-muted"]]' . skaiAdminE($r['username']) . '[[/span]]'; },
                    function($r) { return '[[strong]]' . skaiAdminN($r['total_predictions']) . '[[/strong]]'; },
                    function($r) { return '[[span class="skai-muted"]]' . skaiAdminE(isset($r['last_prediction_at']) ? substr((string)$r['last_prediction_at'], 0, 10) : '') . '[[/span]]'; },
                )
            );
            ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Recent Logins[[/h3]]
            [[p class="skai-panel-desc"]]Most recently active users by last login timestamp.[[/p]]
            <?php
            skaiAdminRenderSimpleTable(
                array('Name', 'Username', 'Last Login', 'Registered'),
                $recentLogins,
                array(
                    function($r) { return skaiAdminE($r['name']); },
                    function($r) { return '[[span class="skai-muted"]]' . skaiAdminE($r['username']) . '[[/span]]'; },
                    function($r) { return skaiAdminE(isset($r['lastvisitDate']) ? substr((string)$r['lastvisitDate'], 0, 16) : ''); },
                    function($r) { return '[[span class="skai-muted"]]' . skaiAdminE(isset($r['registerDate']) ? substr((string)$r['registerDate'], 0, 10) : '') . '[[/span]]'; },
                )
            );
            ?>
        [[/div]]
    [[/div]]
[[/div]]

[[div class="skai-section-divider"]][[/div]]

<!-- Section 3: Prediction Insights -->
[[div class="skai-section"]]
    [[div class="skai-section-head"]]
        [[div class="skai-section-title-row"]]
            [[span class="skai-section-num"]]03[[/span]]
            [[h2 class="skai-section-title"]]Prediction Insights[[/h2]]
        [[/div]]
        [[p class="skai-section-note"]]Breakdown of prediction volume, types, sources, and families.[[/p]]
    [[/div]]
    [[div class="skai-kpi-row"]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Total Predictions[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['total_predictions']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]All time[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Today[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['predictions_today']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Last 24 hours[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Last 7 Days[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['predictions_7d']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Rolling 7-day[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Last 30 Days[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['predictions_30d']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Rolling 30-day[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Avg / Day (30d)[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminE($derived['preds_per_day_30d']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Daily average[[/div]]
        [[/div]]
    [[/div]]
    [[div class="skai-grid-2"]]
        [[div class="skai-panel"]]
            [[h3]]Prediction Types[[/h3]]
            [[p class="skai-panel-desc"]]Distribution of prediction types with inference fallback from pick size and family fields.[[/p]]
            <?php skaiAdminRenderBarsWithPct($predictionTypes, 'label', 'total', $summary['total_predictions']); ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Prediction Sources[[/h3]]
            [[p class="skai-panel-desc"]]Origin of predictions - AI, SKAI engine, manual entry, or other sources.[[/p]]
            <?php skaiAdminRenderBarsWithPct($predictionSources, 'label', 'total', $summary['total_predictions']); ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Prediction Families[[/h3]]
            [[p class="skai-panel-desc"]]Daily vs standard lottery family breakdown with inference from pick size and type.[[/p]]
            <?php skaiAdminRenderBarsWithPct($predictionFamilies, 'label', 'total', $summary['total_predictions']); ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Avg Predictions Per Active Lottery[[/h3]]
            [[p class="skai-panel-desc"]]Prediction density across active lotteries in the past 30 days.[[/p]]
            [[div class="skai-stat-card"]]
                [[div class="skai-stat-card-value"]]<?php echo skaiAdminRatio($summary['predictions_30d'], max(1, $summary['active_lotteries_30d'])); ?>[[/div]]
                [[div class="skai-stat-card-label"]]Avg Predictions Per Active Lottery (30d)[[/div]]
                [[div class="skai-stat-card-note"]]<?php echo skaiAdminN($summary['predictions_30d']); ?> predictions across <?php echo skaiAdminN($summary['active_lotteries_30d']); ?> active lotteries[[/div]]
            [[/div]]
        [[/div]]
    [[/div]]
[[/div]]

[[div class="skai-section-divider"]][[/div]]

<!-- Section 4: Lottery Insights -->
[[div class="skai-section"]]
    [[div class="skai-section-head"]]
        [[div class="skai-section-title-row"]]
            [[span class="skai-section-num"]]04[[/span]]
            [[h2 class="skai-section-title"]]Lottery Insights[[/h2]]
        [[/div]]
        [[p class="skai-section-note"]]Lottery activity, prediction distribution, and engagement coverage.[[/p]]
    [[/div]]
    [[div class="skai-kpi-row"]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Total Lotteries[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['total_lotteries']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Configured on platform[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Active (30d)[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($summary['active_lotteries_30d']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Had predictions in 30 days[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Activity Rate[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminE($derived['lottery_activity_rate']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Lotteries with recent activity[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Zero-Prediction Lotteries[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($derived['lotteries_zero_preds']); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]No predictions ever saved[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Distinct Lotteries Used[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($distinctPredLotteries); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Referenced in predictions table[[/div]]
        [[/div]]
    [[/div]]
    [[div class="skai-grid-3"]]
        [[div class="skai-panel"]]
            [[h3]]Top Lotteries by Volume[[/h3]]
            [[p class="skai-panel-desc"]]Lotteries with the most saved predictions on the platform.[[/p]]
            <?php skaiAdminRenderBarsFriendly($topLotteries, 'lottery_name', 'total_predictions', 'skaiAdminN'); ?>
        [[/div]]
        [[div class="skai-panel" style="grid-column: span 2;"]]
            [[h3]]Lottery Performance Table[[/h3]]
            [[p class="skai-panel-desc"]]Predictions, full hits, near hits, and hit ratio for each lottery.[[/p]]
            <?php
            skaiAdminRenderSimpleTable(
                array('Lottery', 'Predictions', 'Full Hits', 'Near Hits', 'Hit Ratio'),
                $topLotteries,
                array(
                    function($r) { return skaiAdminE($r['lottery_name']); },
                    function($r) { return skaiAdminN($r['total_predictions']); },
                    function($r) { return '[[strong]]' . skaiAdminN($r['full_hits']) . '[[/strong]]'; },
                    function($r) { return skaiAdminN($r['near_hits']); },
                    function($r) { return '[[span class="skai-tag"]]' . skaiAdminE($r['hit_ratio_pct']) . '[[/span]]'; },
                )
            );
            ?>
        [[/div]]
    [[/div]]
    <?php if (!empty($heavyUnverifiedLotteries)): ?>
    [[div class="skai-panel" style="margin-top:16px;"]]
        [[h3]]Lotteries With High Unverified Rate[[/h3]]
        [[p class="skai-panel-desc"]]Lotteries with 5+ predictions but less than 30% verification coverage.[[/p]]
        <?php
        skaiAdminRenderSimpleTable(
            array('Lottery', 'Total Predictions', 'Verified', 'Unverified', 'Verified %'),
            $heavyUnverifiedLotteries,
            array(
                function($r) { return skaiAdminE($r['lottery_name']); },
                function($r) { return skaiAdminN($r['total_predictions']); },
                function($r) { return skaiAdminN($r['verified_count']); },
                function($r) { return '[[span class="skai-tag-warn"]]' . skaiAdminN($r['unverified_count']) . '[[/span]]'; },
                function($r) { return skaiAdminE($r['verified_pct']); },
            )
        );
        ?>
    [[/div]]
    <?php endif; ?>
    <?php if (!empty($lotteryZeroPreds)): ?>
    [[div class="skai-panel" style="margin-top:16px;"]]
        [[h3]]Lotteries With No Predictions[[/h3]]
        [[p class="skai-panel-desc"]]These lotteries have been configured but no users have saved predictions for them.[[/p]]
        [[div class="skai-list-plain"]]
            <?php foreach ($lotteryZeroPreds as $lzp): ?>
            [[div class="skai-list-item"]]<?php echo skaiAdminE(skaiAdminCleanLotteryName($lzp['lottery_name'])); ?>[[/div]]
            <?php endforeach; ?>
        [[/div]]
    [[/div]]
    <?php endif; ?>
[[/div]]

[[div class="skai-section-divider"]][[/div]]

<!-- Section 5: Accuracy and Verification Insights -->
[[div class="skai-section"]]
    [[div class="skai-section-head"]]
        [[div class="skai-section-title-row"]]
            [[span class="skai-section-num"]]05[[/span]]
            [[h2 class="skai-section-title"]]Accuracy and Verification Insights[[/h2]]
        [[/div]]
        [[p class="skai-section-note"]]Prediction outcome tracking, verification coverage, and hit rates.[[/p]]
    [[/div]]
    [[div class="skai-derived-grid"]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Verification Rate[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminE($derived['verification_rate_pct']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Records Verified[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminN($summary['verified_predictions']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Awaiting Verification[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminN($derived['awaiting_verification']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Stale Records[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminN($staleRecordsCount); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Full Hit Rate[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminE($derived['full_hit_rate_pct']); ?>[[/div]]
        [[/div]]
        [[div class="skai-derived-item"]]
            [[div class="skai-derived-label"]]Near Hit Rate[[/div]]
            [[div class="skai-derived-value"]]<?php echo skaiAdminE($derived['near_hit_rate_pct']); ?>[[/div]]
        [[/div]]
    [[/div]]
    [[div class="skai-grid-2"]]
        [[div class="skai-panel"]]
            [[h3]]Top Prediction Types by Full Hits[[/h3]]
            [[p class="skai-panel-desc"]]Which prediction types are generating the most full matches.[[/p]]
            <?php skaiAdminRenderBarsFriendly($topTypesByFullHit, 'label', 'total', 'skaiAdminN'); ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Top Prediction Types by Near Hits[[/h3]]
            [[p class="skai-panel-desc"]]Which prediction types are generating the most near-match outcomes.[[/p]]
            <?php skaiAdminRenderBarsFriendly($topTypesByNearHit, 'label', 'total', 'skaiAdminN'); ?>
        [[/div]]
    [[/div]]
    [[div class="skai-panel" style="margin-top:16px;"]]
        [[h3]]Recent Predictions[[/h3]]
        [[p class="skai-panel-desc"]]The 15 most recently saved predictions with inferred type, source, and outcome data.[[/p]]
        <?php
        skaiAdminRenderSimpleTable(
            array('ID', 'User', 'Lottery', 'Type', 'Source', 'Saved', 'Main Matches', 'Bonus'),
            $recentPredictions,
            array(
                function($r) { return skaiAdminN($r['id']); },
                function($r) { return skaiAdminE($r['user_name'] ?? ''); },
                function($r) { return skaiAdminE(skaiAdminInferLotteryDisplay($r)); },
                function($r) { return '[[span class="skai-tag"]]' . skaiAdminE(skaiAdminInferPredictionType($r)) . '[[/span]]'; },
                function($r) { return '[[span class="skai-muted"]]' . skaiAdminE(skaiAdminInferSource($r)) . '[[/span]]'; },
                function($r) { return skaiAdminE(isset($r['date_saved']) ? substr((string)$r['date_saved'], 0, 10) : ''); },
                function($r) { return isset($r['main_matches']) && $r['main_matches'] !== null ? skaiAdminN($r['main_matches']) : '[[span class="skai-muted"]]-[[/span]]'; },
                function($r) { return isset($r['bonus_matches']) && $r['bonus_matches'] !== null ? skaiAdminN($r['bonus_matches']) : '[[span class="skai-muted"]]-[[/span]]'; },
            )
        );
        ?>
    [[/div]]
[[/div]]

[[div class="skai-section-divider"]][[/div]]

<!-- Section 6: Learning and SKAI Insights -->
[[div class="skai-section"]]
    [[div class="skai-section-head"]]
        [[div class="skai-section-title-row"]]
            [[span class="skai-section-num"]]06[[/span]]
            [[h2 class="skai-section-title"]]Learning and SKAI Insights[[/h2]]
        [[/div]]
        [[p class="skai-section-note"]]SKAI engine learning table activity and data volume tracking.[[/p]]
    [[/div]]
    [[div class="skai-kpi-row"]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Total SKAI Rows[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($skaiTotalRows); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Across all SKAI tables[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Active Tables[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($skaiActiveTableCount); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Tables with data[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Empty Tables[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo skaiAdminN($skaiEmptyTableCount); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Tables present but empty[[/div]]
        [[/div]]
        [[div class="skai-kpi-mini"]]
            [[div class="skai-kpi-mini-label"]]Tables Tracked[[/div]]
            [[div class="skai-kpi-mini-value"]]<?php echo count($skaiCounts); ?>[[/div]]
            [[div class="skai-kpi-mini-sub"]]Detected SKAI tables[[/div]]
        [[/div]]
    [[/div]]
    [[div class="skai-grid-2"]]
        [[div class="skai-panel"]]
            [[h3]]SKAI Table Activity (Row Counts)[[/h3]]
            [[p class="skai-panel-desc"]]Volume of data stored in each SKAI learning and tracking table.[[/p]]
            <?php skaiAdminRenderBarsFriendly($skaiCounts, 'friendly_name', 'total_rows', 'skaiAdminN'); ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Empty SKAI Tables[[/h3]]
            [[p class="skai-panel-desc"]]SKAI tables that exist in the database but contain no rows yet.[[/p]]
            <?php if (empty($skaiEmptyTables)): ?>
            [[div class="skai-empty"]]All detected SKAI tables contain data.[[/div]]
            <?php else: ?>
            [[div class="skai-list-plain"]]
                <?php foreach ($skaiEmptyTables as $et): ?>
                [[div class="skai-list-item"]]<?php echo skaiAdminE($et['friendly_name']); ?> [[span class="skai-muted"]](<?php echo skaiAdminE($et['table_name']); ?>)[[/span]][[/div]]
                <?php endforeach; ?>
            [[/div]]
            <?php endif; ?>
        [[/div]]
    [[/div]]
[[/div]]

[[div class="skai-section-divider"]][[/div]]

<!-- Section 7: System Health and Data Quality -->
[[div class="skai-section"]]
    [[div class="skai-section-head"]]
        [[div class="skai-section-title-row"]]
            [[span class="skai-section-num"]]07[[/span]]
            [[h2 class="skai-section-title"]]System Health and Data Quality[[/h2]]
        [[/div]]
        [[p class="skai-section-note"]]Platform health checks, classification quality, and data coverage signals.[[/p]]
    [[/div]]
    [[div class="skai-grid-2"]]
        [[div class="skai-panel"]]
            [[h3]]Health Checks[[/h3]]
            [[p class="skai-panel-desc"]]Key operational signals flagged as OK or needing attention.[[/p]]
            [[div class="skai-health-list"]]
                <?php foreach ($healthChecks as $hc): ?>
                [[div class="skai-health-row skai-health-<?php echo skaiAdminE($hc['flag']); ?>"]]
                    [[span class="skai-health-label"]]<?php echo skaiAdminE($hc['label']); ?>[[/span]]
                    [[span class="skai-health-value"]]<?php echo skaiAdminN($hc['value']); ?>[[/span]]
                [[/div]]
                <?php endforeach; ?>
            [[/div]]
        [[/div]]
        [[div class="skai-grid-2" style="gap:16px; align-content:start;"]]
            [[div class="skai-panel"]]
                [[h3]]Classification Quality[[/h3]]
                [[p class="skai-panel-desc"]]How well prediction types can be classified from available fields.[[/p]]
                [[div class="skai-derived-grid" style="grid-template-columns: repeat(1, minmax(0, 1fr));"]]
                    [[div class="skai-derived-item"]]
                        [[div class="skai-derived-label"]]Unknown Pred Types[[/div]]
                        [[div class="skai-derived-value"]]<?php echo skaiAdminN($dqUnknownPredType); ?>[[/div]]
                    [[/div]]
                    [[div class="skai-derived-item"]]
                        [[div class="skai-derived-label"]]Inferrable from Pick Size[[/div]]
                        [[div class="skai-derived-value"]]<?php echo skaiAdminN($dqInferrablePredType); ?>[[/div]]
                    [[/div]]
                    [[div class="skai-derived-item"]]
                        [[div class="skai-derived-label"]]Truly Unclassifiable[[/div]]
                        [[div class="skai-derived-value"]]<?php echo skaiAdminN($dqTrulyUnclassifiable); ?>[[/div]]
                    [[/div]]
                [[/div]]
            [[/div]]
            [[div class="skai-panel"]]
                [[h3]]Data Coverage[[/h3]]
                [[p class="skai-panel-desc"]]How much prediction data has match outcome results attached.[[/p]]
                [[div class="skai-derived-grid" style="grid-template-columns: repeat(1, minmax(0, 1fr));"]]
                    [[div class="skai-derived-item"]]
                        [[div class="skai-derived-label"]]With Match Data[[/div]]
                        [[div class="skai-derived-value"]]<?php echo skaiAdminN($recordsWithMatchData); ?>[[/div]]
                    [[/div]]
                    [[div class="skai-derived-item"]]
                        [[div class="skai-derived-label"]]Without Match Data[[/div]]
                        [[div class="skai-derived-value"]]<?php echo skaiAdminN($recordsWithoutMatchData); ?>[[/div]]
                    [[/div]]
                    [[div class="skai-derived-item"]]
                        [[div class="skai-derived-label"]]Coverage Rate[[/div]]
                        [[div class="skai-derived-value"]]<?php echo skaiAdminE($derived['verification_rate_pct']); ?>[[/div]]
                    [[/div]]
                [[/div]]
            [[/div]]
        [[/div]]
    [[/div]]
[[/div]]

[[div class="skai-section-divider"]][[/div]]

<!-- Section 8: Technical Detail -->
[[div class="skai-section"]]
    [[div class="skai-section-head"]]
        [[div class="skai-section-title-row"]]
            [[span class="skai-section-num"]]08[[/span]]
            [[h2 class="skai-section-title"]]Technical Detail[[/h2]]
        [[/div]]
        [[p class="skai-section-note"]]SKAI engine run modes, strategies, risk profiles, and table-level data breakdown.[[/p]]
    [[/div]]
    [[div class="skai-grid-3"]]
        [[div class="skai-panel"]]
            [[h3]]Run Mode Breakdown[[/h3]]
            [[p class="skai-panel-desc"]]Distribution of SKAI run modes across all predictions.[[/p]]
            <?php skaiAdminRenderBarsWithPct($runModes, 'label', 'total', $summary['total_predictions']); ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Strategy Breakdown[[/h3]]
            [[p class="skai-panel-desc"]]Distribution of prediction strategies used by the SKAI engine.[[/p]]
            <?php skaiAdminRenderBarsWithPct($strategies, 'label', 'total', $summary['total_predictions']); ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Risk Profile Breakdown[[/h3]]
            [[p class="skai-panel-desc"]]Distribution of risk profile settings applied to predictions.[[/p]]
            <?php skaiAdminRenderBarsWithPct($riskProfiles, 'label', 'total', $summary['total_predictions']); ?>
        [[/div]]
    [[/div]]
    [[div class="skai-grid-2" style="margin-top:16px;"]]
        [[div class="skai-panel"]]
            [[h3]]SKAI Table Row Counts[[/h3]]
            [[p class="skai-panel-desc"]]Exact row counts for all detected SKAI tracking and learning tables.[[/p]]
            <?php
            skaiAdminRenderSimpleTable(
                array('Table', 'Friendly Name', 'Row Count'),
                $skaiCounts,
                array(
                    function($r) { return '[[span class="skai-muted" style="font-size:11px;"]]' . skaiAdminE($r['table_name']) . '[[/span]]'; },
                    function($r) { return skaiAdminE($r['friendly_name']); },
                    function($r) { return '[[strong]]' . skaiAdminN($r['total_rows']) . '[[/strong]]'; },
                )
            );
            ?>
        [[/div]]
        [[div class="skai-panel"]]
            [[h3]]Dashboard Notes[[/h3]]
            [[p class="skai-panel-desc"]]Context and methodology for interpreting these metrics.[[/p]]
            [[div class="skai-footnote"]]
                [[strong]]Data Quality:[[/strong]] Prediction types, sources, and families are inferred from multiple fields
                using a cascade logic. Records missing primary classification fields are inferred from pick size,
                family indicators, and renderer hints before being marked as uncategorized.[[br]][[br]]
                [[strong]]Verification:[[/strong]] A prediction is considered verified if any of main_matches,
                bonus_matches, or matched_numbers fields contain non-null data. Stale records are predictions
                where target_draw_date has passed but no verification data exists.[[br]][[br]]
                [[strong]]Hit Rate:[[/strong]] Full hit rate is computed as full hits divided by verified predictions.
                Near hit rate follows the same logic using near hit counts. Both rates reflect outcome quality
                of predictions that have been through the draw cycle.[[br]][[br]]
                [[strong]]SKAI Tables:[[/strong]] Fourteen SKAI engine tables are tracked. Counts of zero indicate
                the table exists but the SKAI engine has not yet written data to it. Tables not present in the
                database are simply omitted from this view.[[br]][[br]]
                [[strong]]Revenue:[[/strong]] Revenue figures reflect active subscriptions (published=1, to_date in future)
                and use payment_amount if available, otherwise amount. All figures are direct database aggregates
                with no caching.
            [[/div]]
        [[/div]]
    [[/div]]
[[/div]]

[[/div]]
[[/source]]
