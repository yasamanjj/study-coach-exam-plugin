<?php
/**
 * Plugin Name: Study Coach Exam Plugin (WCE)
 * Description: سیستم مدیریت و برگزاری آزمون‌های آنلاین فوق‌امنیتی با قابلیت بانک سوال، تصحیح خودکار تستی و دستی تشریحی.
 * Version: 2.1.0
 * Author: Study Coach
 * Text Domain: wce-plugin
 */

if (!defined('ABSPATH')) {
    exit; // خروج در صورت دسترسی مستقیم
}

define('WCE_TABLE_BANKS', 'wce_question_banks');
define('WCE_TABLE_QUESTIONS', 'wce_questions');
define('WCE_TABLE_EXAMS', 'wce_exams');
define('WCE_TABLE_ATTEMPTS', 'wce_attempts');
define('WCE_TABLE_ANSWERS', 'wce_answers');
define('WCE_TABLE_LOGS', 'wce_logs');

register_activation_hook(__FILE__, 'wce_install_database');

add_action('admin_init', 'wce_check_db_tables');
function wce_check_db_tables() {
    global $wpdb;
    $table_exams = $wpdb->prefix . WCE_TABLE_EXAMS;
    
    $exam_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_exams}'") == $table_exams;
    if (!$exam_table_exists) {
        wce_install_database();
        return;
    }

    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `{$table_exams}` LIKE 'allow_unanswered'");
    if (empty($column_exists)) {
        wce_install_database();
    }
}

function wce_install_database() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // ۱. جدول بانک‌های سوال
    $sql_banks = "CREATE TABLE {$wpdb->prefix}" . WCE_TABLE_BANKS . " (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // ۲. جدول سوالات
    $sql_questions = "CREATE TABLE {$wpdb->prefix}" . WCE_TABLE_QUESTIONS . " (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        bank_id BIGINT(20) NOT NULL,
        type VARCHAR(20) DEFAULT 'single',
        text LONGTEXT NOT NULL,
        options LONGTEXT DEFAULT NULL,
        correct_answer VARCHAR(10) DEFAULT NULL,
        points FLOAT DEFAULT 1.0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY bank_id (bank_id)
    ) $charset_collate;";

    // ۳. جدول آزمون‌ها
    $sql_exams = "CREATE TABLE {$wpdb->prefix}" . WCE_TABLE_EXAMS . " (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        bank_id BIGINT(20) NOT NULL,
        duration INT(11) NOT NULL DEFAULT 60,
        passing_percentage FLOAT DEFAULT 50.0,
        show_answer_sheet TINYINT(1) DEFAULT 0,
        allow_unanswered TINYINT(1) DEFAULT 1,
        random_single_count INT(11) DEFAULT 0,
        random_descriptive_count INT(11) DEFAULT 0,
        descriptive_points FLOAT DEFAULT 1.0,
        start_time DATETIME DEFAULT NULL,
        end_time DATETIME DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    // ۴. جدول تلاش‌های داوطلبان
    $sql_attempts = "CREATE TABLE {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        exam_id BIGINT(20) NOT NULL,
        user_id BIGINT(20) NOT NULL,
        start_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        end_time DATETIME DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'running',
        auto_score FLOAT DEFAULT 0,
        manual_score FLOAT DEFAULT 0,
        final_score FLOAT DEFAULT 0,
        correct_count INT(11) DEFAULT 0,
        wrong_count INT(11) DEFAULT 0,
        unanswered_count INT(11) DEFAULT 0,
        test_percentage FLOAT DEFAULT 0,
        PRIMARY KEY  (id),
        KEY exam_user (exam_id, user_id)
    ) $charset_collate;";

    // ۵. جدول پاسخ‌های داده شده
    $sql_answers = "CREATE TABLE {$wpdb->prefix}" . WCE_TABLE_ANSWERS . " (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        attempt_id BIGINT(20) NOT NULL,
        question_id BIGINT(20) NOT NULL,
        answer_data LONGTEXT DEFAULT NULL,
        points_awarded FLOAT DEFAULT 0,
        is_checked TINYINT(1) DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY attempt_question (attempt_id, question_id)
    ) $charset_collate;";

    // ۶. جدول لاگ‌های امنیتی
    $sql_logs = "CREATE TABLE {$wpdb->prefix}" . WCE_TABLE_LOGS . " (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        attempt_id BIGINT(20) NOT NULL,
        log_type VARCHAR(50) NOT NULL,
        details TEXT DEFAULT NULL,
        log_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY attempt_id (attempt_id)
    ) $charset_collate;";

    dbDelta($sql_banks);
    dbDelta($sql_questions);
    dbDelta($sql_exams);
    dbDelta($sql_attempts);
    dbDelta($sql_answers);
    dbDelta($sql_logs);

    $exams_table = $wpdb->prefix . WCE_TABLE_EXAMS;
    $columns_to_add = array(
        'passing_percentage'       => 'FLOAT DEFAULT 50.0',
        'show_answer_sheet'        => 'TINYINT(1) DEFAULT 0',
        'allow_unanswered'         => 'TINYINT(1) DEFAULT 1',
        'random_single_count'      => 'INT(11) DEFAULT 0',
        'random_descriptive_count' => 'INT(11) DEFAULT 0',
        'descriptive_points'       => 'FLOAT DEFAULT 1.0',
        'start_time'               => 'DATETIME DEFAULT NULL',
        'end_time'                 => 'DATETIME DEFAULT NULL',
        'status'                   => "VARCHAR(20) DEFAULT 'active'"
    );

    foreach ($columns_to_add as $col => $def) {
        $col_check = $wpdb->get_results("SHOW COLUMNS FROM `{$exams_table}` LIKE '{$col}'");
        if (empty($col_check)) {
            $wpdb->query("ALTER TABLE `{$exams_table}` ADD `{$col}` {$def}");
        }
    }

    wce_register_rewrite_rules();
    flush_rewrite_rules();
}

add_action('init', 'wce_register_rewrite_rules');
function wce_register_rewrite_rules() {
    add_rewrite_rule('^exam/([0-9]+)/?', 'index.php?wce_exam_id=$matches[1]', 'top');
}

add_filter('query_vars', 'wce_register_query_vars');
function wce_register_query_vars($vars) {
    $vars[] = 'wce_exam_id';
    return $vars;
}

add_action('admin_menu', 'wce_add_admin_menu');
function wce_add_admin_menu() {
    add_menu_page('آزمون‌ساز WCE', 'آزمون‌ساز WCE', 'manage_options', 'wce-dashboard', 'wce_render_admin_dashboard', 'dashicons-welcome-write-blog', 6);
    add_submenu_page('wce-dashboard', 'داشبورد و راهنما', 'داشبورد و راهنما', 'manage_options', 'wce-dashboard', 'wce_render_admin_dashboard');
    add_submenu_page('wce-dashboard', 'بانک‌های سوال', 'بانک‌های سوال', 'manage_options', 'wce-banks', 'wce_render_admin_banks');
    add_submenu_page('wce-dashboard', 'مدیریت سوالات', 'مدیریت سوالات', 'manage_options', 'wce-questions', 'wce_render_admin_questions');
    add_submenu_page('wce-dashboard', 'مدیریت آزمون‌ها', 'مدیریت آزمون‌ها', 'manage_options', 'wce-exams', 'wce_render_admin_exams');
    add_submenu_page('wce-dashboard', 'نتایج و تصحیح', 'نتایج و تصحیح', 'manage_options', 'wce-results', 'wce_render_admin_results');
}

function wce_admin_ui_styles() {
    echo '<style>
        .wce-admin-wrap { direction: rtl; font-family: Tahoma, sans-serif; margin: 20px 20px 0 0; }
        .wce-card { background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .wce-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .wce-form-group { margin-bottom: 15px; }
        .wce-form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
        .wce-form-group input[type="text"], .wce-form-group input[type="number"], .wce-form-group select, .wce-form-group textarea { width: 100%; max-width: 500px; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; }
        .wce-btn { background: #2271b1; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: bold; }
        .wce-btn:hover { background: #135e96; color: #fff; }
        .wce-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .wce-table th, .wce-table td { border: 1px solid #c3c4c7; padding: 10px; text-align: right; }
        .wce-table th { background: #f0f0f1; }
        .wce-badge { background: #d1fae5; color: #065f46; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .wce-badge-danger { background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .q-sheet-item { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 12px; }
        .q-sheet-item.correct { border-right: 5px solid #10b981; }
        .q-sheet-item.wrong { border-right: 5px solid #ef4444; }
        .q-sheet-item.unanswered { border-right: 5px solid #f59e0b; }
        .opt-row { padding: 4px 8px; margin: 2px 0; border-radius: 4px; }
        .opt-correct { background: #d1fae5; font-weight: bold; }
        .opt-user-wrong { background: #fee2e2; }
    </style>';
}

function wce_shamsi_to_gregorian($jy, $jm, $jd) {
    $jy += 1595;
    $days = -355668 + (365 * $jy) + ((int)($jy / 33) * 8) + (int)((($jy % 33) + 3) / 4) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * (int)($days / 146097);
    $days %= 146097;
    if ($days > 36524) {
        $days--;
        $gy += 100 * (int)($days / 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * (int)($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $sal_a = array(0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    for ($gm = 1; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return array($gy, $gm, $gd);
}

function wce_gregorian_to_shamsi($gy, $gm, $gd) {
    $g_d_m = array(0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334);
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * ((int)($days / 12053));
    $days %= 12053;
    $jy += 4 * ((int)($days / 1461));
    $days %= 1461;
    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + (int)($days / 31) : 7 + (int)(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return array($jy, $jm, $jd);
}

function wce_parse_input_datetime($input_str) {
    $input_str = trim($input_str);
    if (empty($input_str)) {
        return null;
    }

    $input_str = str_replace('-', '/', $input_str);
    $parts = explode(' ', $input_str);
    $date_part = $parts[0];
    $time_part = isset($parts[1]) ? $parts[1] : '00:00:00';

    if (strlen($time_part) == 5) {
        $time_part .= ':00';
    }

    $date_bits = explode('/', $date_part);
    if (count($date_bits) === 3) {
        $y = intval($date_bits[0]);
        $m = intval($date_bits[1]);
        $d = intval($date_bits[2]);

        if ($y < 1700 && $y > 1300) {
            list($gy, $gm, $gd) = wce_shamsi_to_gregorian($y, $m, $d);
            return sprintf('%04d-%02d-%02d %s', $gy, $gm, $gd, $time_part);
        } elseif ($y >= 1700) {
            return sprintf('%04d-%02d-%02d %s', $y, $m, $d, $time_part);
        }
    }

    $ts = strtotime($input_str);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}

function wce_get_user_phone($user_id) {
    $phone = get_user_meta($user_id, 'billing_phone', true);
    if (!$phone) {
        $phone = get_user_meta($user_id, 'phone_number', true);
    }
    return $phone ? $phone : 'ثبت نشده';
}

function wce_get_user_full_name($user_id) {
    $first_name = get_user_meta($user_id, 'first_name', true);
    $last_name = get_user_meta($user_id, 'last_name', true);
    $full_name = trim($first_name . ' ' . $last_name);
    
    if (!empty($full_name)) {
        return $full_name;
    }
    
    $user = get_userdata($user_id);
    return $user ? $user->display_name : 'کاربر ناشناس';
}

function wce_gregorian_to_shamsi_string($date_str) {
    if (empty($date_str) || $date_str === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($date_str);
    if (!$ts) return '';
    
    $gy = intval(date('Y', $ts));
    $gm = intval(date('m', $ts));
    $gd = intval(date('d', $ts));
    $time = date('H:i', $ts);

    list($jy, $jm, $jd) = wce_gregorian_to_shamsi($gy, $gm, $gd);
    return sprintf('%04d/%02d/%02d %s', $jy, $jm, $jd, $time);
}

function wce_render_admin_dashboard() {
    wce_admin_ui_styles();
    global $wpdb;

    if (isset($_POST['wce_upload_csv_nonce']) && wp_verify_nonce($_POST['wce_upload_csv_nonce'], 'wce_csv_upload_action')) {
        $bank_id = intval($_POST['target_bank_id']);
        if ($bank_id > 0 && !empty($_FILES['wce_csv_file']['tmp_name'])) {
            $handle = fopen($_FILES['wce_csv_file']['tmp_name'], 'r');
            if ($handle !== false) {
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }
                
                $row = 0;
                $imported = 0;
                while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                    $row++;
                    if ($row === 1) continue;

                    $q_text = isset($data[3]) ? sanitize_text_field($data[3]) : '';
                    if (empty($q_text)) continue;

                    $q_desc_flag = isset($data[4]) ? trim($data[4]) : '';
                    $is_descriptive = ($q_desc_flag === 'تشریحی');

                    if ($is_descriptive) {
                        $wpdb->insert(
                            $wpdb->prefix . WCE_TABLE_QUESTIONS,
                            array(
                                'bank_id' => $bank_id,
                                'type' => 'descriptive',
                                'text' => $q_text,
                                'options' => null,
                                'correct_answer' => null,
                                'points' => 1.0
                            )
                        );
                        $imported++;
                    } else {
                        $correct_ans = isset($data[5]) ? trim($data[5]) : '1';
                        $options = array();
                        for ($i = 6; $i <= 10; $i++) {
                            if (isset($data[$i]) && trim($data[$i]) !== '') {
                                $options[] = sanitize_text_field($data[$i]);
                            }
                        }

                        if (!empty($options)) {
                            $wpdb->insert(
                                $wpdb->prefix . WCE_TABLE_QUESTIONS,
                                array(
                                    'bank_id' => $bank_id,
                                    'type' => 'single',
                                    'text' => $q_text,
                                    'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                                    'correct_answer' => $correct_ans,
                                    'points' => 1.0
                                )
                            );
                            $imported++;
                        }
                    }
                }
                fclose($handle);
                add_settings_error('wce_messages', 'wce_success', 'تعداد ' . $imported . ' سوال با موفقیت بارگذاری شد.', 'updated');
            }
        }
    }
    ?>
    <div class="wce-admin-wrap">
        <h2>سیستم آزمون‌ساز فوق‌امنیتی WCE (محیط با همزمانی بالا)</h2>
        <?php settings_errors('wce_messages'); ?>

        <div class="wce-card">
            <h3>درباره سیستم و راهنما</h3>
            <p>این سیستم اختصاصی برای سازمان‌ها، مدارس و دانشگاه‌هایی که نیاز به برگزاری آزمون‌های آنلاین امن با تعداد زیاد داوطلبان همزمان را دارند طراحی و پیاده‌سازی شده است.</p>
            <p><strong>فرمول محاسبه نمره تستی:</strong> درصد بخش تستی از فرمول <code>((درست × ۳) - غلط) / (کل تست‌ها × ۳) × ۱۰۰</code> پیروی می‌کند.</p>
        </div>

        <div class="wce-grid">
            <div class="wce-card">
                <h3>بارگذاری گروهی سوالات (CSV)</h3>
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('wce_csv_upload_action', 'wce_upload_csv_nonce'); ?>
                    <div class="wce-form-group">
                        <label for="target_bank_id">انتخاب بانک سوال هدف:</label>
                        <select name="target_bank_id" id="target_bank_id" required>
                            <option value="">-- انتخاب بانک سوال --</option>
                            <?php
                            $banks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}" . WCE_TABLE_BANKS);
                            foreach ($banks as $bank) {
                                echo '<option value="'.intval($bank->id).'">'.esc_html($bank->name).'</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="wce-form-group">
                        <label for="wce_csv_file">انتخاب فایل CSV سوالات:</label>
                        <input type="file" name="wce_csv_file" id="wce_csv_file" accept=".csv" required />
                    </div>
                    <button type="submit" class="wce-btn">شروع بارگذاری سوالات</button>
                </form>
            </div>

            <div class="wce-card">
                <h3>دانلود نمونه فایل اکسل</h3>
                <p>می‌توانید فایل نمونه منطبق بر فرمت درخواستی خود را دریافت کرده و سوالات جدید را بر اساس آن ویرایش کنید.</p>
                <a href="<?php echo admin_url('admin-ajax.php?action=wce_download_sample_csv'); ?>" class="button button-secondary">دانلود نمونه فایل CSV اکسل</a>
            </div>
        </div>
    </div>
    <?php
}

add_action('wp_ajax_wce_download_sample_csv', 'wce_download_sample_csv');
function wce_download_sample_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز');
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=wce_sample_questions.csv');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ردیف', 'وضعیت', 'تعداد گزینه‌ها', 'سوال', 'شرح', 'گزینه صحیح', 'گزینه 1', 'گزینه 2', 'گزینه 3', 'گزینه 4', 'گزینه 5'));
    fputcsv($output, array('1', '1', '4', 'برنامه ریزی باید بر اساس ..... نگارش شود', '', '3', 'وضعیت موجود دانش‌آموز', 'برنامه‌های آزمون', 'استراتژی‌های', 'نقاط قوت و ضعف', ''));
    fputcsv($output, array('2', '1', '4', 'همه عبارات زیر، حول یک محور میچرخد به جز...', '', '1', 'تدوین استراتژی', 'برنامه‌سازی', 'برنامه کلی', 'برنامه پروژه‌ای', ''));
    fputcsv($output, array('3', '1', '0', 'مفهوم تفکر استراتژیک در برنامه‌ریزی تحصیلی را توضیح دهید.', 'تشریحی', '', '', '', '', '', ''));
    fclose($output);
    exit;
}

function wce_render_admin_banks() {
    wce_admin_ui_styles();
    global $wpdb;

    if (isset($_GET['action']) && $_GET['action'] === 'delete_bank' && isset($_GET['bank_id'])) {
        $bank_id = intval($_GET['bank_id']);
        check_admin_referer('wce_delete_bank_' . $bank_id);
        
        $wpdb->delete($wpdb->prefix . WCE_TABLE_BANKS, array('id' => $bank_id));
        $wpdb->delete($wpdb->prefix . WCE_TABLE_QUESTIONS, array('bank_id' => $bank_id));
        add_settings_error('wce_messages', 'wce_success', 'بانک سوال و تمامی سوالات مربوط به آن با موفقیت حذف شدند.', 'updated');
    }

    if (isset($_POST['wce_create_bank_nonce']) && wp_verify_nonce($_POST['wce_create_bank_nonce'], 'wce_create_bank_action')) {
        $name = sanitize_text_field($_POST['bank_name']);
        $desc = sanitize_textarea_field($_POST['bank_desc']);
        if (!empty($name)) {
            $wpdb->insert(
                $wpdb->prefix . WCE_TABLE_BANKS,
                array('name' => $name, 'description' => $desc),
                array('%s', '%s')
            );
            add_settings_error('wce_messages', 'wce_success', 'بانک سوال جدید با موفقیت ایجاد شد.', 'updated');
        }
    }
    ?>
    <div class="wce-admin-wrap">
        <h2>مدیریت بانک سوالات</h2>
        <?php settings_errors('wce_messages'); ?>

        <div class="wce-grid">
            <div class="wce-card">
                <h3>تعریف بانک جدید</h3>
                <form method="post">
                    <?php wp_nonce_field('wce_create_bank_action', 'wce_create_bank_nonce'); ?>
                    <div class="wce-form-group">
                        <label for="bank_name">نام بانک سوال:</label>
                        <input type="text" name="bank_name" id="bank_name" required placeholder="مثال: سوالات تخصصی ریاضی مهندسی" />
                    </div>
                    <div class="wce-form-group">
                        <label for="bank_desc">توضیحات بانک:</label>
                        <textarea name="bank_desc" id="bank_desc" rows="4"></textarea>
                    </div>
                    <button type="submit" class="wce-btn">ایجاد بانک سوالات</button>
                </form>
            </div>

            <div class="wce-card">
                <h3>لیست بانک‌های فعال</h3>
                <table class="wce-table">
                    <thead>
                        <tr>
                            <th>شناسه</th>
                            <th>نام بانک</th>
                            <th>تعداد سوالات</th>
                            <th>تاریخ ثبت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $banks = $wpdb->get_results("SELECT b.*, COUNT(q.id) as question_count FROM {$wpdb->prefix}" . WCE_TABLE_BANKS . " b LEFT JOIN {$wpdb->prefix}" . WCE_TABLE_QUESTIONS . " q ON b.id = q.bank_id GROUP BY b.id ORDER BY b.id DESC");
                        if ($banks) {
                            foreach ($banks as $bank) {
                                echo '<tr>';
                                echo '<td>' . intval($bank->id) . '</td>';
                                echo '<td><strong>' . esc_html($bank->name) . '</strong></td>';
                                echo '<td>' . intval($bank->question_count) . ' سوال</td>';
                                echo '<td>' . esc_html($bank->created_at) . '</td>';
                                echo '<td>';
                                $del_bank_url = wp_nonce_url(admin_url('admin.php?page=wce-banks&action=delete_bank&bank_id=' . $bank->id), 'wce_delete_bank_' . $bank->id);
                                echo '<a href="' . esc_url($del_bank_url) . '" class="button button-small button-link-delete" style="color: red;" onclick="return confirm(\'آیا از حذف این بانک سوال و تمامی سوالات متصل به آن اطمینان دارید؟\');">حذف کامل بانک</a>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="5" style="text-align:center;">هیچ بانکی یافت نشد.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

function wce_render_admin_questions() {
    wce_admin_ui_styles();
    global $wpdb;

    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['q_id'])) {
        $q_id = intval($_GET['q_id']);
        check_admin_referer('wce_delete_question_' . $q_id);
        $wpdb->delete($wpdb->prefix . WCE_TABLE_QUESTIONS, array('id' => $q_id));
        add_settings_error('wce_messages', 'wce_success', 'سوال مورد نظر با موفقیت حذف شد.', 'updated');
    }

    if (isset($_POST['wce_edit_question_nonce']) && wp_verify_nonce($_POST['wce_edit_question_nonce'], 'wce_edit_question_action')) {
        $q_id = intval($_POST['edit_q_id']);
        $text = wp_kses_post($_POST['q_text']);
        $type = sanitize_text_field($_POST['q_type']);

        if ($type === 'single') {
            $options = array();
            for ($i = 1; $i <= 5; $i++) {
                if (isset($_POST['q_option_' . $i]) && trim($_POST['q_option_' . $i]) !== '') {
                    $options[] = sanitize_text_field($_POST['q_option_' . $i]);
                }
            }
            $correct = sanitize_text_field($_POST['q_correct_answer']);

            $wpdb->update(
                $wpdb->prefix . WCE_TABLE_QUESTIONS,
                array(
                    'text' => $text,
                    'type' => 'single',
                    'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
                    'correct_answer' => $correct
                ),
                array('id' => $q_id)
            );
        } else {
            $wpdb->update(
                $wpdb->prefix . WCE_TABLE_QUESTIONS,
                array(
                    'text' => $text,
                    'type' => 'descriptive',
                    'options' => null,
                    'correct_answer' => null
                ),
                array('id' => $q_id)
            );
        }
        add_settings_error('wce_messages', 'wce_success', 'سوال با موفقیت بروزرسانی شد.', 'updated');
    }

    $is_edit = isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['q_id']);
    $edit_q = null;
    if ($is_edit) {
        $q_id = intval($_GET['q_id']);
        $edit_q = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}" . WCE_TABLE_QUESTIONS . " WHERE id = %d", $q_id));
    }

    $selected_bank = isset($_GET['bank_id']) ? intval($_GET['bank_id']) : 0;
    ?>
    <div class="wce-admin-wrap">
        <h2>مدیریت و ویرایش تک‌تک سوالات</h2>
        <?php settings_errors('wce_messages'); ?>

        <?php if ($is_edit && $edit_q): ?>
            <div class="wce-card">
                <h3>ویرایش سوال شناسه <?php echo intval($edit_q->id); ?></h3>
                <form method="post">
                    <?php wp_nonce_field('wce_edit_question_action', 'wce_edit_question_nonce'); ?>
                    <input type="hidden" name="edit_q_id" value="<?php echo intval($edit_q->id); ?>" />

                    <div class="wce-form-group">
                        <label for="q_text">متن سوال:</label>
                        <textarea name="q_text" id="q_text" rows="4" required><?php echo esc_textarea($edit_q->text); ?></textarea>
                    </div>

                    <div class="wce-form-group">
                        <label for="q_type">نوع سوال:</label>
                        <select name="q_type" id="q_type" onchange="toggleOptionsDisplay(this.value)">
                            <option value="single" <?php selected($edit_q->type, 'single'); ?>>تستی</option>
                            <option value="descriptive" <?php selected($edit_q->type, 'descriptive'); ?>>تشریحی</option>
                        </select>
                    </div>

                    <?php 
                    $options = json_decode($edit_q->options, true);
                    if (!is_array($options)) {
                        $options = [];
                    }
                    ?>

                    <div id="options-holder" style="display: <?php echo $edit_q->type == 'single' ? 'block' : 'none'; ?>;">
                        <p style="font-weight: bold; margin-bottom: 10px;">گزینه‌های سوال (خالی رها نکنید):</p>
                        <?php for ($i = 1; $i <= 5; $i++): 
                            $opt_val = isset($options[$i - 1]) ? $options[$i - 1] : '';
                            ?>
                            <div class="wce-form-group" style="margin-right: 15px;">
                                <label>گزینه <?php echo $i; ?>:</label>
                                <input type="text" name="q_option_<?php echo $i; ?>" value="<?php echo esc_attr($opt_val); ?>" />
                            </div>
                        <?php endfor; ?>

                        <div class="wce-form-group">
                            <label for="q_correct_answer">شماره گزینه صحیح (از ۱ تا ۵):</label>
                            <input type="number" name="q_correct_answer" id="q_correct_answer" min="1" max="5" value="<?php echo esc_attr($edit_q->correct_answer); ?>" />
                        </div>
                    </div>

                    <button type="submit" class="wce-btn">ذخیره تغییرات سوال</button>
                    <a href="?page=wce-questions&bank_id=<?php echo intval($edit_q->bank_id); ?>" class="button button-secondary" style="margin-top: 5px;">انصراف</a>
                </form>

                <script>
                    function toggleOptionsDisplay(type) {
                        const holder = document.getElementById('options-holder');
                        if (type === 'single') {
                            holder.style.display = 'block';
                        } else {
                            holder.style.display = 'none';
                        }
                    }
                </script>
            </div>
        <?php else: ?>
            <div class="wce-card">
                <h3>انتخاب بانک سوال برای نمایش و ویرایش:</h3>
                <form method="get" action="">
                    <input type="hidden" name="page" value="wce-questions" />
                    <div class="wce-form-group" style="display: flex; gap: 10px; align-items: center;">
                        <select name="bank_id" style="max-width: 300px;">
                            <option value="">-- انتخاب بانک سوال --</option>
                            <?php
                            $banks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}" . WCE_TABLE_BANKS);
                            foreach ($banks as $bank) {
                                $sel = ($selected_bank == $bank->id) ? 'selected' : '';
                                echo '<option value="'.intval($bank->id).'" '.$sel.'>'.esc_html($bank->name).'</option>';
                            }
                            ?>
                        </select>
                        <button type="submit" class="button button-primary">نمایش سوالات</button>
                    </div>
                </form>
            </div>

            <?php if ($selected_bank > 0): ?>
                <div class="wce-card">
                    <h3>لیست سوالات موجود در این بانک</h3>
                    <table class="wce-table">
                        <thead>
                            <tr>
                                <th style="width: 5%">ID</th>
                                <th style="width: 10%">نوع</th>
                                <th style="width: 45%">متن سوال</th>
                                <th style="width: 20%">گزینه‌ها / وضعیت</th>
                                <th style="width: 20%">عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}" . WCE_TABLE_QUESTIONS . " WHERE bank_id = %d ORDER BY id DESC", $selected_bank));
                            if ($questions) {
                                foreach ($questions as $q) {
                                    $options_text = '-';
                                    if ($q->type == 'single') {
                                        $opts = json_decode($q->options, true);
                                        if (is_array($opts)) {
                                            $options_text = 'گزینه صحیح: ' . esc_html($q->correct_answer) . ' | تعداد گزینه‌ها: ' . count($opts);
                                        }
                                    } else {
                                        $options_text = '<span style="color: blue;">تشریحی</span>';
                                    }

                                    echo '<tr>';
                                    echo '<td>' . intval($q->id) . '</td>';
                                    echo '<td>' . ($q->type == 'single' ? '<span class="wce-badge">تستی</span>' : '<span class="wce-badge-danger">تشریحی</span>') . '</td>';
                                    echo '<td><strong>' . wp_kses_post($q->text) . '</strong></td>';
                                    echo '<td>' . $options_text . '</td>';
                                    echo '<td>';
                                    echo '<a href="' . admin_url('admin.php?page=wce-questions&action=edit&q_id=' . $q->id) . '" class="button button-small">ویرایش جزئیات</a> ';
                                    
                                    $del_url = wp_nonce_url(admin_url('admin.php?page=wce-questions&action=delete&q_id=' . $q->id . '&bank_id=' . $selected_bank), 'wce_delete_question_' . $q->id);
                                    echo '<a href="' . esc_url($del_url) . '" class="button button-small button-link-delete" style="color: red;" onclick="return confirm(\'آیا مطمئن هستید که می‌خواهید این سوال را حذف کنید؟\');">حذف</a>';
                                    
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="5" style="text-align:center;">هیچ سوالی در این بانک یافت نشد.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
}

function wce_render_admin_exams() {
    wce_admin_ui_styles();
    global $wpdb;

    if (isset($_GET['action']) && $_GET['action'] === 'delete_exam' && isset($_GET['exam_id'])) {
        $e_id = intval($_GET['exam_id']);
        check_admin_referer('wce_delete_exam_' . $e_id);
        $wpdb->delete($wpdb->prefix . WCE_TABLE_EXAMS, array('id' => $e_id));
        add_settings_error('wce_messages', 'wce_success', 'آزمون با موفقیت حذف گردید.', 'updated');
    }

    if (isset($_POST['wce_create_exam_nonce']) || isset($_POST['wce_edit_exam_nonce'])) {
        $title = sanitize_text_field($_POST['exam_title']);
        $bank_id = intval($_POST['exam_bank_id']);
        $duration = intval($_POST['exam_duration']);
        $passing_pct = floatval($_POST['exam_passing_percentage']);
        $show_sheet = isset($_POST['exam_show_answer_sheet']) ? 1 : 0;
        $allow_unanswered = isset($_POST['exam_allow_unanswered']) ? 1 : 0;
        $rnd_single = intval($_POST['exam_random_single_count']);
        $rnd_desc = intval($_POST['exam_random_descriptive_count']);
        $desc_points = floatval($_POST['exam_descriptive_points']);

        $start_time = !empty($_POST['exam_start_time']) ? wce_parse_input_datetime($_POST['exam_start_time']) : null;
        $end_time = !empty($_POST['exam_end_time']) ? wce_parse_input_datetime($_POST['exam_end_time']) : null;

        $exam_data = array(
            'title' => $title,
            'bank_id' => $bank_id,
            'duration' => $duration,
            'passing_percentage' => $passing_pct,
            'show_answer_sheet' => $show_sheet,
            'allow_unanswered' => $allow_unanswered,
            'random_single_count' => $rnd_single,
            'random_descriptive_count' => $rnd_desc,
            'descriptive_points' => $desc_points,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'status' => isset($_POST['exam_status']) ? sanitize_text_field($_POST['exam_status']) : 'active'
        );

        if (isset($_POST['wce_edit_exam_nonce']) && wp_verify_nonce($_POST['wce_edit_exam_nonce'], 'wce_edit_exam_action')) {
            $edit_id = intval($_POST['edit_exam_id']);
            $updated = $wpdb->update(
                $wpdb->prefix . WCE_TABLE_EXAMS,
                $exam_data,
                array('id' => $edit_id)
            );

            if ($updated === false) {
                add_settings_error('wce_messages', 'wce_error', 'خطا در ویرایش آزمون: ' . esc_html($wpdb->last_error), 'error');
            } else {
                add_settings_error('wce_messages', 'wce_success', 'آزمون با موفقیت بروزرسانی شد.', 'updated');
            }
        } else if (isset($_POST['wce_create_exam_nonce']) && wp_verify_nonce($_POST['wce_create_exam_nonce'], 'wce_create_exam_action')) {
            $inserted = $wpdb->insert(
                $wpdb->prefix . WCE_TABLE_EXAMS,
                $exam_data
            );

            if ($inserted === false) {
                add_settings_error('wce_messages', 'wce_error', 'خطا در ایجاد آزمون در پایگاه داده: ' . esc_html($wpdb->last_error), 'error');
            } else {
                add_settings_error('wce_messages', 'wce_success', 'آزمون جدید با موفقیت ایجاد شد.', 'updated');
            }
        }
    }

    $is_edit = isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['exam_id']);
    $edit_exam = null;
    if ($is_edit) {
        $edit_id = intval($_GET['exam_id']);
        $edit_exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}" . WCE_TABLE_EXAMS . " WHERE id = %d", $edit_id));
    }
    ?>
    <div class="wce-admin-wrap">
        <h2>مدیریت آزمون‌ها</h2>
        <?php settings_errors('wce_messages'); ?>

        <div class="wce-grid">
            <div class="wce-card">
                <h3><?php echo $is_edit ? 'ویرایش آزمون' : 'تعریف آزمون جدید'; ?></h3>
                <form method="post">
                    <?php if ($is_edit): ?>
                        <?php wp_nonce_field('wce_edit_exam_action', 'wce_edit_exam_nonce'); ?>
                        <input type="hidden" name="edit_exam_id" value="<?php echo intval($edit_exam->id); ?>" />
                    <?php else: ?>
                        <?php wp_nonce_field('wce_create_exam_action', 'wce_create_exam_nonce'); ?>
                    <?php endif; ?>

                    <div class="wce-form-group">
                        <label for="exam_title">عنوان آزمون:</label>
                        <input type="text" name="exam_title" id="exam_title" required value="<?php echo $is_edit ? esc_attr($edit_exam->title) : ''; ?>" placeholder="مثال: میان‌ترم هوش مصنوعی" />
                    </div>
                    <div class="wce-form-group">
                        <label for="exam_bank_id">بانک سوال مرجع:</label>
                        <select name="exam_bank_id" id="exam_bank_id" required>
                            <option value="">-- انتخاب بانک سوال --</option>
                            <?php
                            $banks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}" . WCE_TABLE_BANKS);
                            if ($banks) {
                                foreach ($banks as $bank) {
                                    $selected = $is_edit && $edit_exam->bank_id == $bank->id ? 'selected' : '';
                                    echo '<option value="'.intval($bank->id).'" '.$selected.'>'.esc_html($bank->name).'</option>';
                                }
                            }
                            ?>
                        </select>
                        <?php if (empty($banks)): ?>
                            <p class="description" style="color:red;">هیچ بانک سوالی وجود ندارد. لطفاً ابتدا از منوی "بانک‌های سوال" یک بانک جدید ایجاد کنید.</p>
                        <?php endif; ?>
                    </div>
                    <div class="wce-form-group">
                        <label for="exam_duration">مدت زمان آزمون (به دقیقه):</label>
                        <input type="number" name="exam_duration" id="exam_duration" min="5" max="300" required value="<?php echo $is_edit ? intval($edit_exam->duration) : '60'; ?>" />
                    </div>

                    <div class="wce-form-group">
                        <label for="exam_passing_percentage">حد نصاب درصد قبولی آزمون (٪):</label>
                        <input type="number" step="0.1" name="exam_passing_percentage" id="exam_passing_percentage" min="0" max="100" required value="<?php echo $is_edit ? floatval($edit_exam->passing_percentage) : '50.0'; ?>" />
                        <p class="description">حداقل درصد مورد نیاز داوطلب برای قبول شدن در آزمون.</p>
                    </div>

                    <div class="wce-form-group" style="background:#f9fafb; padding:10px; border-radius:6px; border:1px solid #e5e7eb;">
                        <label for="exam_show_answer_sheet" style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="exam_show_answer_sheet" id="exam_show_answer_sheet" value="1" <?php echo ($is_edit && $edit_exam->show_answer_sheet) ? 'checked' : ''; ?> />
                            <span>اجازه مشاهده پاسخنامه به دانش‌آموز پس از پایان آزمون</span>
                        </label>
                        <p class="description" style="margin-top:5px; font-size:12px;">در صورت عدم فعال‌سازی، کاربر فقط پیغام ثبت موفقیت‌آمیز را خواهد دید.</p>
                    </div>

                    <!-- تنظیم جدید: امکان پاسخ‌های نزده -->
                    <div class="wce-form-group" style="background:#f9fafb; padding:10px; border-radius:6px; border:1px solid #e5e7eb;">
                        <label for="exam_allow_unanswered" style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                            <input type="checkbox" name="exam_allow_unanswered" id="exam_allow_unanswered" value="1" <?php echo (!$is_edit || intval($edit_exam->allow_unanswered) === 1) ? 'checked' : ''; ?> />
                            <span>امکان داشتن سوالات بدون پاسخ (نزده)</span>
                        </label>
                        <p class="description" style="margin-top:5px; font-size:12px;">اگر این گزینه تیک بزند، داوطلب می‌تواند برخی سوالات را بدون پاسخ رها کند. در صورت عدم تیک، پاسخ به تمامی سوالات الزامی خواهد بود.</p>
                    </div>

                    <div class="wce-form-group">
                        <label for="exam_random_single_count">تعداد سوالات تستی تصادفی:</label>
                        <input type="number" name="exam_random_single_count" id="exam_random_single_count" min="0" required value="<?php echo $is_edit ? intval($edit_exam->random_single_count) : '0'; ?>" />
                        <p class="description">محاسبه بر اساس فرمول: <code>((درست × ۳) - غلط) / (کل × ۳) × ۱۰۰</code></p>
                    </div>

                    <div class="wce-form-group">
                        <label for="exam_random_descriptive_count">تعداد سوالات تشریحی تصادفی:</label>
                        <input type="number" name="exam_random_descriptive_count" id="exam_random_descriptive_count" min="0" required value="<?php echo $is_edit ? intval($edit_exam->random_descriptive_count) : '0'; ?>" />
                    </div>

                    <div class="wce-form-group">
                        <label for="exam_descriptive_points">بارم هر سوال تشریحی (نمره دستی):</label>
                        <input type="number" step="0.1" name="exam_descriptive_points" id="exam_descriptive_points" min="0" required value="<?php echo $is_edit ? floatval($edit_exam->descriptive_points) : '1.5'; ?>" />
                    </div>

                    <div class="wce-form-group">
                        <label for="exam_start_time">زمان شروع فعال شدن آزمون (شمسی - فرمت: ۱۴۰۵/۰۴/۲۸ ۱۵:۳۰):</label>
                        <input type="text" name="exam_start_time" id="exam_start_time" placeholder="مثال: 1405/04/28 15:30" value="<?php echo $is_edit ? esc_attr(wce_gregorian_to_shamsi_string($edit_exam->start_time)) : ''; ?>" />
                    </div>

                    <div class="wce-form-group">
                        <label for="exam_end_time">زمان پایان مهلت ورود به آزمون (شمسی - فرمت: ۱۴۰۵/۰۴/۲۸ ۱۸:۳۰):</label>
                        <input type="text" name="exam_end_time" id="exam_end_time" placeholder="مثال: 1405/04/28 18:30" value="<?php echo $is_edit ? esc_attr(wce_gregorian_to_shamsi_string($edit_exam->end_time)) : ''; ?>" />
                    </div>

                    <?php if ($is_edit): ?>
                        <div class="wce-form-group">
                            <label for="exam_status">وضعیت آزمون:</label>
                            <select name="exam_status" id="exam_status">
                                <option value="active" <?php selected($edit_exam->status, 'active'); ?>>فعال</option>
                                <option value="inactive" <?php selected($edit_exam->status, 'inactive'); ?>>غیرفعال</option>
                            </select>
                        </div>
                    <?php endif; ?>

                    <button type="submit" class="wce-btn" style="margin-top:10px;"><?php echo $is_edit ? 'بروزرسانی آزمون' : 'ساخت و انتشار آزمون'; ?></button>
                    <?php if ($is_edit): ?>
                        <a href="<?php echo admin_url('admin.php?page=wce-exams'); ?>" class="button button-secondary" style="margin-top:10px; margin-right: 10px;">انصراف</a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="wce-card">
                <h3>لیست آزمون‌های فعال</h3>
                <table class="wce-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>عنوان</th>
                            <th>تعداد سوالات</th>
                            <th>حد نصاب درصد</th>
                            <th>پاسخ‌های نزده</th>
                            <th>وضعیت</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $exams = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}" . WCE_TABLE_EXAMS . " ORDER BY id DESC");
                        if ($exams) {
                            foreach ($exams as $exam) {
                                $url = home_url('/exam/' . $exam->id);
                                echo '<tr>';
                                echo '<td>' . intval($exam->id) . '</td>';
                                echo '<td><strong>' . esc_html($exam->title) . '</strong></td>';
                                echo '<td>' . intval($exam->random_single_count) . ' تستی<br>' . intval($exam->random_descriptive_count) . ' تشریحی</td>';
                                echo '<td><strong>' . floatval($exam->passing_percentage) . '٪</strong></td>';
                                echo '<td>' . (intval($exam->allow_unanswered) === 1 ? '<span class="wce-badge">مجاز</span>' : '<span class="wce-badge-danger">الزامی (همه)</span>') . '</td>';
                                echo '<td>' . ($exam->status == 'active' ? '<span class="wce-badge">فعال</span>' : '<span class="wce-badge-danger">غیرفعال</span>') . '</td>';
                                echo '<td>';
                                echo '<a href="' . admin_url('admin.php?page=wce-exams&action=edit&exam_id='.$exam->id) . '" class="button button-small" style="margin-left: 5px;">ویرایش</a> ';
                                
                                $del_exam_url = wp_nonce_url(admin_url('admin.php?page=wce-exams&action=delete_exam&exam_id=' . $exam->id), 'wce_delete_exam_' . $exam->id);
                                echo '<a href="' . esc_url($del_exam_url) . '" class="button button-small button-link-delete" style="color: red;" onclick="return confirm(\'آیا از حذف کامل این آزمون مطمئن هستید؟\');">حذف</a>';
                                
                                echo '<div style="margin-top: 5px;"><a href="' . esc_url($url) . '" target="_blank" style="direction:ltr; display:inline-block; font-size:11px;">/exam/' . intval($exam->id) . '</a></div>';
                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="7" style="text-align:center;">هیچ آزمونی یافت نشد.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

function wce_render_admin_results() {
    wce_admin_ui_styles();
    global $wpdb;

    if (isset($_POST['wce_bulk_action_nonce']) && wp_verify_nonce($_POST['wce_bulk_action_nonce'], 'wce_bulk_action_results')) {
        $bulk_action = sanitize_text_field($_POST['bulk_action_select']);
        $selected_ids = isset($_POST['attempt_ids']) ? array_map('intval', $_POST['attempt_ids']) : array();

        if (!empty($selected_ids) && $bulk_action === 'delete') {
            $ids_placeholder = implode(',', array_fill(0, count($selected_ids), '%d'));
            
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " WHERE id IN ($ids_placeholder)", $selected_ids));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}" . WCE_TABLE_ANSWERS . " WHERE attempt_id IN ($ids_placeholder)", $selected_ids));
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}" . WCE_TABLE_LOGS . " WHERE attempt_id IN ($ids_placeholder)", $selected_ids));

            add_settings_error('wce_messages', 'wce_success', 'تعداد ' . count($selected_ids) . ' مورد از نتایج انتخاب‌شده با موفقیت حذف گردیدند.', 'updated');
        }
    }

    if (isset($_POST['wce_manual_grade_nonce']) && wp_verify_nonce($_POST['wce_manual_grade_nonce'], 'wce_manual_grade_action')) {
        $attempt_id = intval($_POST['grade_attempt_id']);
        $scores = $_POST['scores'];

        if ($attempt_id && is_array($scores)) {
            $total_manual = 0;
            foreach ($scores as $q_id => $points) {
                $q_id = intval($q_id);
                $points = floatval($points);
                
                $wpdb->update(
                    $wpdb->prefix . WCE_TABLE_ANSWERS,
                    array('points_awarded' => $points, 'is_checked' => 1),
                    array('attempt_id' => $attempt_id, 'question_id' => $q_id),
                    array('%f', '%d'),
                    array('%d', '%d')
                );
                $total_manual += $points;
            }

            $attempt_info = $wpdb->get_row($wpdb->prepare("SELECT test_percentage FROM {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " WHERE id = %d", $attempt_id));

            $wpdb->update(
                $wpdb->prefix . WCE_TABLE_ATTEMPTS,
                array(
                    'manual_score' => $total_manual,
                    'final_score' => floatval($attempt_info->test_percentage) + $total_manual
                ),
                array('id' => $attempt_id),
                array('%f', '%f'),
                array('%d')
            );
            add_settings_error('wce_messages', 'wce_success', 'نمره‌دهی تشریحی با موفقیت ذخیره شد.', 'updated');
        }
    }

    if (isset($_GET['action']) && $_GET['action'] === 'delete_attempt' && isset($_GET['attempt_id'])) {
        $att_id = intval($_GET['attempt_id']);
        check_admin_referer('wce_delete_attempt_' . $att_id);
        $wpdb->delete($wpdb->prefix . WCE_TABLE_ATTEMPTS, array('id' => $att_id));
        $wpdb->delete($wpdb->prefix . WCE_TABLE_ANSWERS, array('attempt_id' => $att_id));
        $wpdb->delete($wpdb->prefix . WCE_TABLE_LOGS, array('attempt_id' => $att_id));
        add_settings_error('wce_messages', 'wce_success', 'تلاش داوطلب و پاسخ‌های مربوطه پاک شد.', 'updated');
    }

    $selected_exam_id = isset($_GET['filter_exam_id']) ? intval($_GET['filter_exam_id']) : 0;
    $filter_type = isset($_GET['filter_type']) ? sanitize_text_field($_GET['filter_type']) : '';
    $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    ?>
    <div class="wce-admin-wrap">
        <h2>لیست نتایج داوطلبان، پاسخنامه‌ها و تصحیح دستی</h2>
        <?php settings_errors('wce_messages'); ?>

        <div class="wce-card" style="display:flex; justify-space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
                <h3>گزارش‌ها و خروجی‌ها</h3>
                <p style="margin:0; color:#666;">دانلود فایل کامل اکسل نتایج یا چاپ دسته‌جمعی آزمون‌های تشریحی</p>
            </div>
            <div>
                <a href="<?php echo admin_url('admin-ajax.php?action=wce_export_results_csv'); ?>" class="wce-btn" style="text-decoration:none;">خروجی کامل اکسل نتایج (CSV)</a>
            </div>
        </div>

        <div class="wce-card">
            <h3>فیلتر و جستجوی پیشرفته در نتایج</h3>
            <form method="get" action="">
                <input type="hidden" name="page" value="wce-results" />
                <div class="wce-form-group" style="display: flex; gap: 10px; align-items: center; flex-wrap:wrap;">
                    
                    <input type="text" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="جستجوی نام، فامیل، شماره یا آزمون..." style="max-width:250px;" />

                    <select name="filter_exam_id" style="max-width:220px;">
                        <option value="">-- همه آزمون‌ها --</option>
                        <?php
                        $exams = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}" . WCE_TABLE_EXAMS);
                        foreach ($exams as $exam) {
                            $sel = ($selected_exam_id == $exam->id) ? 'selected' : '';
                            echo '<option value="' . intval($exam->id) . '" ' . $sel . '>' . esc_html($exam->title) . '</option>';
                        }
                        ?>
                    </select>

                    <select name="filter_type" style="max-width:200px;">
                        <option value="">-- همه انواع آزمون --</option>
                        <option value="has_descriptive" <?php selected($filter_type, 'has_descriptive'); ?>>دارای سوالات تشریحی</option>
                        <option value="test_only" <?php selected($filter_type, 'test_only'); ?>>فقط تستی</option>
                    </select>

                    <button type="submit" class="button button-primary">اعمال فیلتر و جستجو</button>
                    <?php if ($selected_exam_id > 0 || !empty($search_term) || !empty($filter_type)): ?>
                        <a href="?page=wce-results" class="button button-secondary">پاک کردن فیلترها</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <form method="post" id="wce-bulk-form">
            <?php wp_nonce_field('wce_bulk_action_results', 'wce_bulk_action_nonce'); ?>
            
            <div style="margin-bottom: 12px; display: flex; align-items: center; gap: 10px; background: #fff; padding: 10px 15px; border-radius: 6px; border: 1px solid #ccd0d4;">
                <strong>عملیات گروهی:</strong>
                <select name="bulk_action_select" id="bulk_action_select">
                    <option value="">-- انتخاب عملیات --</option>
                    <option value="delete">حذف نتایج انتخاب‌شده</option>
                </select>
                <button type="submit" class="button action" onclick="return confirm('آیا از انجام این عملیات روی موارد انتخاب شده اطمینان دارید؟');">اعمال روی انتخاب‌شده‌ها</button>
                
                <button type="button" class="button button-secondary" onclick="exportBulkDescriptivePDF()" style="margin-right:auto; background:#047857; color:#fff; border-color:#047857;">
                    چاپ / خروجی PDF دسته‌جمعی آزمون‌های تشریحی
                </button>
            </div>

            <div class="wce-card" style="padding:0; overflow-x:auto;">
                <table class="wce-table" style="margin:0; border:none;">
                    <thead>
                        <tr>
                            <th style="width:30px; text-align:center;"><input type="checkbox" id="wce-select-all" onclick="toggleSelectAll(this)" /></th>
                            <th>نام و نام خانوادگی</th>
                            <th>شماره تماس</th>
                            <th>عنوان آزمون</th>
                            <th>درصد تستی</th>
                            <th>جزئیات تست</th>
                            <th>حد نصاب قبولی</th>
                            <th>نمره تشریحی</th>
                            <th>وضعیت</th>
                            <th>عملیات مدیریت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $where_clauses = array("1=1");
                        $params = array();

                        if ($selected_exam_id > 0) {
                            $where_clauses[] = "a.exam_id = %d";
                            $params[] = $selected_exam_id;
                        }

                        if ($filter_type === 'has_descriptive') {
                            $where_clauses[] = "e.random_descriptive_count > 0";
                        } elseif ($filter_type === 'test_only') {
                            $where_clauses[] = "e.random_descriptive_count = 0";
                        }

                        if (!empty($search_term)) {
                            $where_clauses[] = "(u.display_name LIKE %s OR e.title LIKE %s OR um_fn.meta_value LIKE %s OR um_ln.meta_value LIKE %s OR um_p1.meta_value LIKE %s OR um_p2.meta_value LIKE %s)";
                            $s_like = '%' . $wpdb->esc_like($search_term) . '%';
                            $params[] = $s_like;
                            $params[] = $s_like;
                            $params[] = $s_like;
                            $params[] = $s_like;
                            $params[] = $s_like;
                            $params[] = $s_like;
                        }

                        $where_sql = implode(' AND ', $where_clauses);
                        if (!empty($params)) {
                            $where_sql = $wpdb->prepare($where_sql, $params);
                        }

                        $query = "
                            SELECT a.*, u.display_name, e.title as exam_title, e.passing_percentage, e.random_descriptive_count
                            FROM {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " a
                            JOIN {$wpdb->users} u ON a.user_id = u.ID
                            JOIN {$wpdb->prefix}" . WCE_TABLE_EXAMS . " e ON a.exam_id = e.id
                            LEFT JOIN {$wpdb->usermeta} um_fn ON (u.ID = um_fn.user_id AND um_fn.meta_key = 'first_name')
                            LEFT JOIN {$wpdb->usermeta} um_ln ON (u.ID = um_ln.user_id AND um_ln.meta_key = 'last_name')
                            LEFT JOIN {$wpdb->usermeta} um_p1 ON (u.ID = um_p1.user_id AND um_p1.meta_key = 'billing_phone')
                            LEFT JOIN {$wpdb->usermeta} um_p2 ON (u.ID = um_p2.user_id AND um_p2.meta_key = 'phone_number')
                            WHERE $where_sql
                            GROUP BY a.id
                            ORDER BY a.id DESC
                        ";

                        $attempts = $wpdb->get_results($query);

                        if ($attempts) {
                            foreach ($attempts as $att) {
                                $phone_number = wce_get_user_phone($att->user_id);
                                $full_name = wce_get_user_full_name($att->user_id);
                                $is_passed = floatval($att->test_percentage) >= floatval($att->passing_percentage);

                                echo '<tr>';
                                echo '<td style="text-align:center;"><input type="checkbox" name="attempt_ids[]" value="' . intval($att->id) . '" class="wce-attempt-cb" /></td>';
                                echo '<td><strong>' . esc_html($full_name) . '</strong></td>';
                                echo '<td><code style="font-size:12px; direction:ltr; display:inline-block;">' . esc_html($phone_number) . '</code></td>';
                                echo '<td>' . esc_html($att->exam_title) . '</td>';
                                echo '<td><strong style="font-size:15px; color:#2563eb;">' . floatval($att->test_percentage) . '٪</strong></td>';
                                echo '<td style="font-size:11px;">درست: ' . intval($att->correct_count) . ' | غلط: ' . intval($att->wrong_count) . ' | نزده: ' . intval($att->unanswered_count) . '</td>';
                                echo '<td>' . floatval($att->passing_percentage) . '٪ (' . ($is_passed ? '<span class="wce-badge">قبول</span>' : '<span class="wce-badge-danger">مردود</span>') . ')</td>';
                                echo '<td>' . floatval($att->manual_score) . ' نمره</td>';
                                echo '<td>' . ($att->status == 'completed' ? '<span class="wce-badge">پایان یافته</span>' : '<span class="wce-badge-danger">درحال آزمون</span>') . '</td>';
                                echo '<td>';
                                
                                echo '<a href="?page=wce-results&view_sheet=' . intval($att->id) . '" class="button button-small button-primary" style="margin-left:3px;">پاسخنامه</a> ';
                                
                                if ($att->status == 'completed') {
                                    echo '<a href="' . admin_url('admin-ajax.php?action=wce_print_pdf&attempt_id=' . intval($att->id)) . '" target="_blank" class="button button-small" style="background:#047857; color:#fff; border-color:#047857; margin-left: 3px;">چاپ/PDF</a> ';
                                }
                                echo '<a href="?page=wce-results&view_logs=' . intval($att->id) . '" class="button button-small" style="background:#4b5563; color:#fff; border-color:#4b5563; margin-left: 3px;">لاگ</a> ';
                                
                                $del_attempt_url = wp_nonce_url(admin_url('admin.php?page=wce-results&action=delete_attempt&attempt_id=' . $att->id), 'wce_delete_attempt_' . $att->id);
                                echo '<a href="' . esc_url($del_attempt_url) . '" class="button button-small" style="background:#dc2626; color:#fff; border-color:#dc2626;" onclick="return confirm(\'آیا از حذف مطمئن هستید؟\');">حذف</a>';
                                
                                echo '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="10" style="text-align:center;">هیچ نتایجی منطبق با جستجو/فیلتر پیدا نشد.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </form>

        <script>
            function toggleSelectAll(source) {
                const checkboxes = document.querySelectorAll('.wce-attempt-cb');
                checkboxes.forEach(cb => cb.checked = source.checked);
            }

            function exportBulkDescriptivePDF() {
                const checked = document.querySelectorAll('.wce-attempt-cb:checked');
                if (checked.length === 0) {
                    alert('لطفاً حداقل یک کاربر یا نتیجه آزمون را از جدول انتخاب کنید.');
                    return;
                }
                const ids = Array.from(checked).map(cb => cb.value).join(',');
                const printUrl = "<?php echo admin_url('admin-ajax.php?action=wce_print_bulk_descriptive'); ?>&attempt_ids=" + ids;
                window.open(printUrl, '_blank');
            }
        </script>

        <?php
        if (isset($_GET['view_sheet'])) {
            $sheet_attempt_id = intval($_GET['view_sheet']);
            wce_render_admin_student_sheet($sheet_attempt_id);
        }

        if (isset($_GET['view_logs'])) {
            $log_attempt_id = intval($_GET['view_logs']);
            $logs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}" . WCE_TABLE_LOGS . " WHERE attempt_id = %d ORDER BY log_time DESC", $log_attempt_id));
            ?>
            <div class="wce-card" style="border: 1px solid #e53e3e;">
                <h3 style="color:#e53e3e;">لاگ‌های امنیتی ضد تقلب (شناسه تلاش: <?php echo $log_attempt_id; ?>)</h3>
                <table class="wce-table">
                    <thead>
                        <tr>
                            <th>نوع رویداد</th>
                            <th>زمان ثبت رویداد</th>
                            <th>جزئیات تکمیلی</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($logs) {
                            foreach ($logs as $log) {
                                echo '<tr>';
                                echo '<td><span class="wce-badge-danger">' . esc_html($log->log_type) . '</span></td>';
                                echo '<td>' . esc_html($log->log_time) . '</td>';
                                echo '<td>' . esc_html($log->details) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="3" style="text-align:center;">هیچ رفتار مشکوکی ثبت نشده است.</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
        ?>
    </div>
    <?php
}

function wce_render_admin_student_sheet($attempt_id) {
    global $wpdb;

    $attempt = $wpdb->get_row($wpdb->prepare("
        SELECT a.*, u.display_name, u.user_email, e.title as exam_title, e.passing_percentage, e.descriptive_points 
        FROM {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " a
        JOIN {$wpdb->users} u ON a.user_id = u.ID
        JOIN {$wpdb->prefix}" . WCE_TABLE_EXAMS . " e ON a.exam_id = e.id
        WHERE a.id = %d
    ", $attempt_id));

    if (!$attempt) return;

    $user_full_name = wce_get_user_full_name($attempt->user_id);

    $answers = $wpdb->get_results($wpdb->prepare("
        SELECT sa.*, q.text as question_text, q.type as question_type, q.options as question_options, q.correct_answer as question_correct
        FROM {$wpdb->prefix}" . WCE_TABLE_ANSWERS . " sa
        JOIN {$wpdb->prefix}" . WCE_TABLE_QUESTIONS . " q ON sa.question_id = q.id
        WHERE sa.attempt_id = %d
        ORDER BY q.type DESC, sa.id ASC
    ", $attempt_id));

    $is_passed = floatval($attempt->test_percentage) >= floatval($attempt->passing_percentage);
    ?>
    <div class="wce-card" style="border: 2px solid #007cba;">
        <h3>پاسخنامه تفصیلی داوطلب: <?php echo esc_html($user_full_name); ?> (آزمون: <?php echo esc_html($attempt->exam_title); ?>)</h3>
        
        <div style="background:#f8fafc; padding:15px; border-radius:8px; margin-bottom:20px; display:flex; gap:20px; flex-wrap:wrap;">
            <div><strong>نام و نام خانوادگی:</strong> <?php echo esc_html($user_full_name); ?></div>
            <div><strong>درصد تستی:</strong> <span style="color:#2563eb; font-size:18px;"><?php echo floatval($attempt->test_percentage); ?>٪</span></div>
            <div><strong>حد نصاب قبولی:</strong> <?php echo floatval($attempt->passing_percentage); ?>٪</div>
            <div><strong>وضعیت حد نصاب:</strong> <?php echo $is_passed ? '<span class="wce-badge">قبول</span>' : '<span class="wce-badge-danger">مردود</span>'; ?></div>
            <div><strong>درست:</strong> <?php echo intval($attempt->correct_count); ?></div>
            <div><strong>غلط:</strong> <?php echo intval($attempt->wrong_count); ?></div>
            <div><strong>نزده:</strong> <?php echo intval($attempt->unanswered_count); ?></div>
        </div>

        <form method="post">
            <?php wp_nonce_field('wce_manual_grade_action', 'wce_manual_grade_action_nonce'); ?>
            <input type="hidden" name="wce_manual_grade_nonce" value="<?php echo wp_create_nonce('wce_manual_grade_action'); ?>" />
            <input type="hidden" name="grade_attempt_id" value="<?php echo $attempt_id; ?>" />

            <h4>سوالات و پاسخ‌های ثبت‌شده:</h4>
            <?php
            $q_index = 1;
            $has_descriptive = false;
            if ($answers) {
                foreach ($answers as $ans) {
                    if ($ans->question_type === 'single') {
                        $user_ans = trim($ans->answer_data);
                        $correct_ans = trim($ans->question_correct);
                        $opts = json_decode($ans->question_options, true);
                        if (!is_array($opts)) $opts = [];

                        $status_class = 'unanswered';
                        $status_label = 'پاسخ داده نشده';
                        if (!empty($user_ans)) {
                            if ($user_ans == $correct_ans) {
                                $status_class = 'correct';
                                $status_label = 'پاسخ صحیح (مثبت)';
                            } else {
                                $status_class = 'wrong';
                                $status_label = 'پاسخ نادرست (منفی)';
                            }
                        }

                        echo '<div class="q-sheet-item ' . $status_class . '">';
                        echo '<div><strong>سوال ' . $q_index . ' (تستی):</strong> ' . wp_kses_post($ans->question_text) . ' <span class="wce-badge" style="margin-right:10px;">' . $status_label . '</span></div>';
                        echo '<div style="margin-top:8px;">';
                        
                        foreach ($opts as $opt_idx => $opt_val) {
                            $opt_num = $opt_idx + 1;
                            $style = 'opt-row';
                            if ($opt_num == $correct_ans) {
                                $style .= ' opt-correct';
                            }
                            if ($user_ans == $opt_num && $user_ans != $correct_ans) {
                                $style .= ' opt-user-wrong';
                            }
                            
                            $prefix = '';
                            if ($user_ans == $opt_num) $prefix .= ' [انتخاب کاربر] ';
                            if ($correct_ans == $opt_num) $prefix .= ' [گزینه صحیح] ';

                            echo '<div class="' . $style . '">گزینه ' . $opt_num . ': ' . esc_html($opt_val) . ' <strong>' . $prefix . '</strong></div>';
                        }
                        echo '</div></div>';
                    } else {
                        $has_descriptive = true;
                        echo '<div class="q-sheet-item" style="border-right: 5px solid #3b82f6;">';
                        echo '<div><strong>سوال ' . $q_index . ' (تشریحی):</strong> ' . wp_kses_post($ans->question_text) . '</div>';
                        echo '<div style="background:#fff; padding:10px; border:1px solid #ddd; margin:10px 0; border-radius:4px;">';
                        echo '<strong>پاسخ داوطلب:</strong><br>' . nl2br(esc_html($ans->answer_data ? $ans->answer_data : 'پاسخی ثبت نشده است.'));
                        echo '</div>';
                        echo '<div class="wce-form-group">';
                        echo '<label>نمره اختصاصی استاد (حداکثر ' . floatval($attempt->descriptive_points) . ' نمره):</label>';
                        echo '<input type="number" step="0.1" min="0" max="' . floatval($attempt->descriptive_points) . '" name="scores[' . intval($ans->question_id) . ']" value="' . floatval($ans->points_awarded) . '" style="max-width:200px;" />';
                        echo '</div>';
                        echo '</div>';
                    }
                    $q_index++;
                }
            }
            ?>

            <?php if ($has_descriptive): ?>
                <button type="submit" class="wce-btn" style="margin-top:15px;">ذخیره و ثبت نهایی نمرات تشریحی</button>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

add_action('wp_ajax_wce_print_pdf', 'wce_ajax_print_pdf');
function wce_ajax_print_pdf() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز');
    }
    $attempt_id = intval($_GET['attempt_id']);
    if (!$attempt_id) {
        wp_die('شناسه نامعتبر');
    }

    global $wpdb;
    $attempt = $wpdb->get_row($wpdb->prepare("
        SELECT a.*, u.display_name, u.user_email, e.title as exam_title, e.passing_percentage, e.descriptive_points 
        FROM {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " a
        JOIN {$wpdb->users} u ON a.user_id = u.ID
        JOIN {$wpdb->prefix}" . WCE_TABLE_EXAMS . " e ON a.exam_id = e.id
        WHERE a.id = %d
    ", $attempt_id));

    if (!$attempt) {
        wp_die('تلاش داوطلب یافت نشد.');
    }

    $phone_number = wce_get_user_phone($attempt->user_id);
    $full_name = wce_get_user_full_name($attempt->user_id);
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>برگه پاسخنامه - <?php echo esc_html($full_name); ?></title>
        <style>
            body { font-family: Tahoma, Arial, sans-serif; direction: rtl; padding: 30px; color: #333; }
            .pdf-header { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; }
            .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; background: #f8fafc; padding: 15px; border: 1px solid #ddd; margin-bottom: 20px; }
            .no-print { background: #2563eb; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
            @media print { .no-print { display: none; } }
        </style>
    </head>
    <body>
        <button class="no-print" onclick="window.print()">پرینت و خروجی PDF</button>
        <div class="pdf-header">
            <h2>برگه پاسخنامه داوطلب</h2>
            <div>سامانه آزمون WCE</div>
        </div>
        <div class="meta-grid">
            <div><strong>نام و نام خانوادگی:</strong> <?php echo esc_html($full_name); ?></div>
            <div><strong>شماره تماس:</strong> <?php echo esc_html($phone_number); ?></div>
            <div><strong>عنوان آزمون:</strong> <?php echo esc_html($attempt->exam_title); ?></div>
            <div><strong>درصد تستی:</strong> %<?php echo floatval($attempt->test_percentage); ?></div>
            <div><strong>حد نصاب قبولی:</strong> %<?php echo floatval($attempt->passing_percentage); ?></div>
            <div><strong>نمره تشریحی:</strong> <?php echo floatval($attempt->manual_score); ?></div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

add_action('wp_ajax_wce_print_bulk_descriptive', 'wce_ajax_print_bulk_descriptive');
function wce_ajax_print_bulk_descriptive() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز');
    }

    $raw_ids = isset($_GET['attempt_ids']) ? sanitize_text_field($_GET['attempt_ids']) : '';
    if (empty($raw_ids)) {
        wp_die('هیچ شناسه تلاشی انتخاب نشده است.');
    }

    $ids_array = array_map('intval', explode(',', $raw_ids));
    $ids_array = array_filter($ids_array);

    if (empty($ids_array)) {
        wp_die('شناسه‌های وارد شده معتبر نیستند.');
    }

    global $wpdb;
    $ids_placeholder = implode(',', array_fill(0, count($ids_array), '%d'));

    $attempts = $wpdb->get_results($wpdb->prepare("
        SELECT a.*, u.display_name, e.title as exam_title 
        FROM {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " a
        JOIN {$wpdb->users} u ON a.user_id = u.ID
        JOIN {$wpdb->prefix}" . WCE_TABLE_EXAMS . " e ON a.exam_id = e.id
        WHERE a.id IN ($ids_placeholder)
        ORDER BY a.id DESC
    ", $ids_array));

    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <title>خروجی دسته‌جمعی پاسخنامه‌های تشریحی</title>
        <style>
            body { font-family: Tahoma, Arial, sans-serif; direction: rtl; padding: 20px; color: #1e293b; line-height: 1.6; }
            .no-print-bar { background: #2563eb; color: #fff; padding: 12px; text-align: center; border-radius: 6px; margin-bottom: 20px; }
            .btn-print { background: #10b981; color: #fff; border: none; padding: 10px 25px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 15px; }
            .student-sheet-container { background: #fff; border: 2px solid #cbd5e1; border-radius: 8px; padding: 25px; margin-bottom: 30px; page-break-after: always; }
            .sheet-header { border-bottom: 2px solid #2563eb; padding-bottom: 10px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
            .info-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 20px; flex-wrap: wrap; }
            .q-desc-block { background: #fff; border: 1px solid #cbd5e1; border-right: 5px solid #3b82f6; border-radius: 6px; padding: 15px; margin-bottom: 15px; }
            .q-desc-title { font-weight: bold; font-size: 15px; margin-bottom: 8px; color: #0f172a; }
            .q-desc-answer { background: #f1f5f9; padding: 12px; border-radius: 4px; border: 1px solid #e2e8f0; margin-top: 8px; font-size: 14px; white-space: pre-wrap; }
            @media print {
                .no-print-bar { display: none; }
                body { padding: 0; }
                .student-sheet-container { border: 1px solid #000; box-shadow: none; margin-bottom: 0; }
            }
        </style>
    </head>
    <body>
        <div class="no-print-bar">
            <span>تعداد کل پاسخنامه‌های انتخاب‌شده: <strong><?php echo count($attempts); ?></strong></span>
            <button class="btn-print" onclick="window.print()" style="margin-right:20px;">پرینت یکجا / دانلود PDF</button>
        </div>

        <?php
        if ($attempts) {
            foreach ($attempts as $att) {
                $full_name = wce_get_user_full_name($att->user_id);
                $phone_number = wce_get_user_phone($att->user_id);

                $desc_answers = $wpdb->get_results($wpdb->prepare("
                    SELECT sa.*, q.text as question_text, q.points as max_points
                    FROM {$wpdb->prefix}" . WCE_TABLE_ANSWERS . " sa
                    JOIN {$wpdb->prefix}" . WCE_TABLE_QUESTIONS . " q ON sa.question_id = q.id
                    WHERE sa.attempt_id = %d AND q.type = 'descriptive'
                    ORDER BY sa.id ASC
                ", $att->id));

                echo '<div class="student-sheet-container">';
                echo '<div class="sheet-header">';
                echo '<h2 style="margin:0; font-size:18px;">پاسخنامه تشریحی داوطلب</h2>';
                echo '<span style="font-weight:bold; color:#2563eb;">' . esc_html($att->exam_title) . '</span>';
                echo '</div>';

                echo '<div class="info-box">';
                echo '<div><strong>نام و نام خانوادگی:</strong> ' . esc_html($full_name) . '</div>';
                echo '<div><strong>شماره تماس:</strong> ' . esc_html($phone_number) . '</div>';
                echo '<div><strong>شناسه تلاش:</strong> ' . intval($att->id) . '</div>';
                echo '<div><strong>نمره تشریحی ثبت‌شده:</strong> ' . floatval($att->manual_score) . '</div>';
                echo '</div>';

                if ($desc_answers) {
                    $q_num = 1;
                    foreach ($desc_answers as $ans) {
                        echo '<div class="q-desc-block">';
                        echo '<div class="q-desc-title">سوال ' . $q_num . ': ' . wp_kses_post($ans->question_text) . '</div>';
                        echo '<div class="q-desc-answer"><strong>پاسخ داوطلب:</strong><br>' . esc_html($ans->answer_data ? $ans->answer_data : 'پاسخی توسط داوطلب ثبت نشده است.') . '</div>';
                        echo '<div style="margin-top:10px; font-size:13px; color:#475569;">نمره کسب شده: <strong>' . floatval($ans->points_awarded) . '</strong></div>';
                        echo '</div>';
                        $q_num++;
                    }
                } else {
                    echo '<p style="color:#ef4444; font-weight:bold;">این داوطلب هیچ پاسخ تشریحی در این آزمون ندارد.</p>';
                }

                echo '</div>';
            }
        }
        ?>
    </body>
    </html>
    <?php
    exit;
}

add_action('wp_ajax_wce_export_results_csv', 'wce_export_results_csv');
function wce_export_results_csv() {
    if (!current_user_can('manage_options')) {
        wp_die('دسترسی غیرمجاز');
    }

    global $wpdb;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=wce_exams_results_report.csv');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');

    fputcsv($output, array('شناسه تلاش', 'نام و نام خانوادگی', 'شماره تماس', 'عنوان آزمون', 'درصد تستی', 'درست', 'غلط', 'نزده', 'حد نصاب قبولی', 'نمره تشریحی', 'وضعیت'));

    $results = $wpdb->get_results("
        SELECT a.*, u.display_name, e.title as exam_title, e.passing_percentage 
        FROM {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " a
        JOIN {$wpdb->users} u ON a.user_id = u.ID
        JOIN {$wpdb->prefix}" . WCE_TABLE_EXAMS . " e ON a.exam_id = e.id
        ORDER BY a.id DESC
    ");

    foreach ($results as $row) {
        $phone_number = wce_get_user_phone($row->user_id);
        $full_name = wce_get_user_full_name($row->user_id);
        $is_passed = floatval($row->test_percentage) >= floatval($row->passing_percentage) ? 'قبول' : 'مردود';
        fputcsv($output, array(
            $row->id,
            $full_name,
            $phone_number,
            $row->exam_title,
            $row->test_percentage . '%',
            $row->correct_count,
            $row->wrong_count,
            $row->unanswered_count,
            $row->passing_percentage . '% (' . $is_passed . ')',
            $row->manual_score,
            $row->status
        ));
    }
    fclose($output);
    exit;
}

add_action('template_redirect', 'wce_frontend_exam_router');
function wce_frontend_exam_router() {
    $exam_id = get_query_var('wce_exam_id');
    if (!$exam_id) return;

    if (!is_user_logged_in()) {
        $current_url = home_url($_SERVER['REQUEST_URI']);
        $login_url = 'https://studycoach.ir/%d9%88%d8%b1%d9%88%d8%af/';
        $redirect_url = add_query_arg('redirect_to', urlencode($current_url), $login_url);
        wp_redirect($redirect_url);
        exit;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . WCE_TABLE_EXAMS;
    $exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d AND status = 'active'", $exam_id));

    if (!$exam) {
        wp_die('آزمون مورد نظر یافت نشد یا غیرفعال است.');
    }

    $current_user = wp_get_current_user();
    $attempt_table = $wpdb->prefix . WCE_TABLE_ATTEMPTS;
    $attempt = $wpdb->get_row($wpdb->prepare("
        SELECT * FROM {$attempt_table} 
        WHERE exam_id = %d AND user_id = %d ORDER BY id DESC LIMIT 1
    ", $exam_id, $current_user->ID));

    if ($attempt && $attempt->status === 'completed') {
        wce_render_exam_completed_screen($exam, $attempt);
        exit;
    }

    $now_timestamp = current_time('timestamp');
    $has_active_attempt = ($attempt && $attempt->status === 'running');

    if (!empty($exam->start_time) && $now_timestamp < strtotime($exam->start_time)) {
        wce_render_exam_not_started_screen($exam);
        exit;
    }

    if (!empty($exam->end_time) && $now_timestamp > strtotime($exam->end_time) && !$has_active_attempt) {
        wce_render_exam_expired_screen($exam);
        exit;
    }

    if (!$attempt) {
        $wpdb->insert(
            $attempt_table,
            array(
                'exam_id' => $exam_id,
                'user_id' => $current_user->ID,
                'start_time' => current_time('mysql'),
                'status' => 'running'
            ),
            array('%d', '%d', '%s', '%s')
        );
        $attempt_id = $wpdb->insert_id;
        $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$attempt_table} WHERE id = %d", $attempt_id));
    }

    $questions_cache_key = 'wce_exam_questions_mix_' . $exam_id . '_' . $attempt->id;
    $questions = get_transient($questions_cache_key);

    if (false === $questions || empty($questions)) {
        $q_table = $wpdb->prefix . WCE_TABLE_QUESTIONS;
        
        $questions_single = [];
        if ($exam->random_single_count > 0) {
            $questions_single = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$q_table} 
                WHERE bank_id = %d AND type = 'single' ORDER BY RAND() LIMIT %d
            ", $exam->bank_id, $exam->random_single_count));
        }

        $questions_desc = [];
        if ($exam->random_descriptive_count > 0) {
            $questions_desc = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$q_table} 
                WHERE bank_id = %d AND type = 'descriptive' ORDER BY RAND() LIMIT %d
            ", $exam->bank_id, $exam->random_descriptive_count));
        }

        $questions = array_merge($questions_single, $questions_desc);
        shuffle($questions);

        if (!empty($questions)) {
            set_transient($questions_cache_key, $questions, 3 * HOUR_IN_SECONDS);
        }
    }

    if (empty($questions)) {
        wp_die('هیچ سوالی برای این آزمون تنظیم نشده است.');
    }

    $start_timestamp = strtotime($attempt->start_time);
    $total_seconds_allowed = $exam->duration * 60;
    $elapsed_seconds = current_time('timestamp') - $start_timestamp;
    $remaining_seconds = $total_seconds_allowed - $elapsed_seconds;

    if ($remaining_seconds <= 0) {
        wce_auto_submit_attempt($attempt->id);
        $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$attempt_table} WHERE id = %d", $attempt->id));
        wce_render_exam_completed_screen($exam, $attempt);
        exit;
    }

    wce_render_exam_frontend_interface($exam, $attempt, $questions, $remaining_seconds);
    exit;
}

function wce_auto_submit_attempt($attempt_id) {
    global $wpdb;
    $attempt_table = $wpdb->prefix . WCE_TABLE_ATTEMPTS;
    $attempt = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$attempt_table} WHERE id = %d", $attempt_id));
    if (!$attempt || $attempt->status == 'completed') return;

    $exam_table = $wpdb->prefix . WCE_TABLE_EXAMS;
    $exam = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$exam_table} WHERE id = %d", $attempt->exam_id));

    $answer_table = $wpdb->prefix . WCE_TABLE_ANSWERS;
    $answers = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$answer_table} WHERE attempt_id = %d", $attempt_id));

    $q_table = $wpdb->prefix . WCE_TABLE_QUESTIONS;

    $total_test_questions = intval($exam->random_single_count);
    $correct = 0;
    $wrong = 0;

    foreach ($answers as $ans) {
        $q = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$q_table} WHERE id = %d", $ans->question_id));
        if ($q && $q->type == 'single') {
            $user_ans = trim($ans->answer_data);
            if (!empty($user_ans)) {
                if ($user_ans == trim($q->correct_answer)) {
                    $correct++;
                    $wpdb->update($answer_table, array('points_awarded' => 1, 'is_checked' => 1), array('id' => $ans->id));
                } else {
                    $wrong++;
                    $wpdb->update($answer_table, array('points_awarded' => 0, 'is_checked' => 1), array('id' => $ans->id));
                }
            }
        }
    }

    $unanswered = $total_test_questions - ($correct + $wrong);
    if ($unanswered < 0) $unanswered = 0;

    $test_percentage = 0.00;
    if ($total_test_questions > 0) {
        $numerator = ($correct * 3) - $wrong;
        $denominator = $total_test_questions * 3;
        $test_percentage = ($numerator / $denominator) * 100;
        $test_percentage = round($test_percentage, 2);
    }

    $wpdb->update(
        $attempt_table,
        array(
            'end_time' => current_time('mysql'),
            'status' => 'completed',
            'auto_score' => $test_percentage,
            'correct_count' => $correct,
            'wrong_count' => $wrong,
            'unanswered_count' => $unanswered,
            'test_percentage' => $test_percentage,
            'final_score' => $test_percentage + floatval($attempt->manual_score)
        ),
        array('id' => $attempt_id)
    );
}

add_action('wp_ajax_wce_save_answer', 'wce_save_answer_ajax');
function wce_save_answer_ajax() {
    check_ajax_referer('wce_exam_nonce', 'security');
    $attempt_id = intval($_POST['attempt_id']);
    if (!$attempt_id || !is_user_logged_in()) {
    wp_send_json_error();
}

global $wpdb;

$attempt = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}" . WCE_TABLE_ATTEMPTS . " WHERE id = %d AND user_id = %d",
        $attempt_id,
        get_current_user_id()
    )
);

if (!$attempt) {
    wp_send_json_error();
}
    $question_id = intval($_POST['question_id']);
    $answer_data = sanitize_textarea_field($_POST['answer_data']);

    if (!$attempt_id || !$question_id) {
        wp_send_json_error();
    }

    global $wpdb;
    $table = $wpdb->prefix . WCE_TABLE_ANSWERS;

    $wpdb->replace(
        $table,
        array(
            'attempt_id' => $attempt_id,
            'question_id' => $question_id,
            'answer_data' => $answer_data
        ),
        array('%d', '%d', '%s')
    );

    wp_send_json_success();
}

add_action('wp_ajax_wce_submit_exam', 'wce_submit_exam_ajax');
function wce_submit_exam_ajax() {
    check_ajax_referer('wce_exam_nonce', 'security');
    $attempt_id = intval($_POST['attempt_id']);
    if ($attempt_id) {
        wce_auto_submit_attempt($attempt_id);
        wp_send_json_success();
    }
    wp_send_json_error();
}

add_action('wp_ajax_wce_log_event', 'wce_log_event_ajax');
function wce_log_event_ajax() {
    check_ajax_referer('wce_exam_nonce', 'security');
    $attempt_id = intval($_POST['attempt_id']);
    $log_type = sanitize_text_field($_POST['log_type']);
    $details = sanitize_text_field($_POST['details']);

    if ($attempt_id) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . WCE_TABLE_LOGS,
            array(
                'attempt_id' => $attempt_id,
                'log_type' => $log_type,
                'details' => $details,
                'log_time' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    wp_send_json_success();
}

function wce_render_exam_completed_screen($exam, $attempt) {
    global $wpdb;
    $show_sheet = intval($exam->show_answer_sheet) === 1;
    $is_passed = floatval($attempt->test_percentage) >= floatval($exam->passing_percentage);
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>اتمام آزمون - <?php echo esc_html($exam->title); ?></title>
        <style>
            body { background: #f1f5f9; font-family: Tahoma, Arial, sans-serif; margin: 0; padding: 40px 20px; direction: rtl; }
            .completed-container { background: #ffffff; padding: 30px; border-radius: 12px; max-width: 750px; margin: 0 auto; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
            .success-icon { color: #22c55e; font-size: 50px; text-align: center; }
            h1 { text-align: center; font-size: 22px; color: #1e293b; }
            .score-card { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px; margin: 20px 0; text-align: center; color: #166534; }
            .score-num { font-size: 32px; font-weight: bold; }
            .badge-pass { background: #d1fae5; color: #065f46; padding: 4px 12px; border-radius: 20px; font-weight: bold; }
            .badge-fail { background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-weight: bold; }
            .btn { display: inline-block; background: #3b82f6; color: #fff; padding: 10px 25px; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 15px; }
            .sheet-box { margin-top: 30px; text-align: right; border-top: 2px solid #e2e8f0; padding-top: 20px; }
            .sheet-item { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="completed-container">
            <div class="success-icon">✓</div>
            <h1>آزمون شما با موفقیت پایان یافت!</h1>
            <p style="text-align:center;">پاسخ‌های شما در سیستم ذخیره گردید.</p>

            <div class="score-card">
                <div>درصد تستی کسب شده شما:</div>
                <div class="score-num"><?php echo floatval($attempt->test_percentage); ?>٪</div>
                <div style="margin-top:10px;">
                    حد نصاب قبولی آزمون: <strong><?php echo floatval($exam->passing_percentage); ?>٪</strong>
                    <span style="margin-right:10px;" class="<?php echo $is_passed ? 'badge-pass' : 'badge-fail'; ?>">
                        <?php echo $is_passed ? 'حد نصاب قبولی کسب شد' : 'حد نصاب قبولی کسب نشد'; ?>
                    </span>
                </div>
            </div>

            <?php if ($show_sheet): ?>
                <div class="sheet-box">
                    <h3>پاسخنامه آزمون شما:</h3>
                    <?php
                    $answers = $wpdb->get_results($wpdb->prepare("
                        SELECT sa.*, q.text as question_text, q.options as question_options, q.correct_answer as question_correct, q.type as question_type
                        FROM {$wpdb->prefix}" . WCE_TABLE_ANSWERS . " sa
                        JOIN {$wpdb->prefix}" . WCE_TABLE_QUESTIONS . " q ON sa.question_id = q.id
                        WHERE sa.attempt_id = %d
                    ", $attempt->id));

                    if ($answers) {
                        $idx = 1;
                        foreach ($answers as $ans) {
                            if ($ans->question_type === 'single') {
                                $u_ans = trim($ans->answer_data);
                                $c_ans = trim($ans->question_correct);
                                $status = ($u_ans == $c_ans) ? '<span style="color:green;">(صحیح)</span>' : (!empty($u_ans) ? '<span style="color:red;">(نادرست)</span>' : '<span style="color:orange;">(بدون پاسخ)</span>');
                                
                                echo '<div class="sheet-item">';
                                echo '<strong>سوال ' . $idx . ':</strong> ' . wp_kses_post($ans->question_text) . ' ' . $status;
                                echo '<div style="font-size:13px; margin-top:5px; color:#475569;">';
                                echo 'گزینه انتخابی شما: ' . ($u_ans ? $u_ans : 'پاسخ نداده‌اید') . ' | گزینه صحیح: ' . $c_ans;
                                echo '</div></div>';
                            }
                            $idx++;
                        }
                    }
                    ?>
                </div>
            <?php else: ?>
                <p style="text-align:center; color:#64748b; margin-top:20px; font-style:italic;">
                    پاسخنامه برای این آزمون فعال نیست و فقط توسط مدیر/استاد قابل مشاهده می‌باشد.
                </p>
            <?php endif; ?>

            <div style="text-align:center;">
                <a href="<?php echo esc_url(home_url()); ?>" class="btn">بازگشت به صفحه اصلی</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}

function wce_render_exam_not_started_screen($exam) {
    $shamsi_start = wce_gregorian_to_shamsi_string($exam->start_time);
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>آزمون هنوز آغاز نشده است - <?php echo esc_html($exam->title); ?></title>
        <style>
            body { background: #f1f5f9; font-family: Tahoma, Arial, sans-serif; margin: 0; padding: 40px; display: flex; align-items: center; justify-content: center; min-height: 100vh; direction: rtl; }
            .container { background: #fff; padding: 40px; border-radius: 12px; text-align: center; max-width: 500px; width: 100%; box-shadow:0 4px 6px rgba(0,0,0,0.05); }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>این آزمون هنوز آغاز نشده است</h2>
            <p>زمان شروع آزمون: <strong><?php echo esc_html($shamsi_start); ?></strong></p>
            <p>لطفاً در زمان مقرر مراجعه فرمایید.</p>
            <a href="<?php echo esc_url(home_url()); ?>" style="display:inline-block; margin-top:15px; color:#2563eb; text-decoration:none; font-weight:bold;">بازگشت به صفحه اصلی</a>
        </div>
    </body>
    </html>
    <?php
}

function wce_render_exam_expired_screen($exam) {
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>مهلت شرکت در آزمون به پایان رسیده است - <?php echo esc_html($exam->title); ?></title>
        <style>
            body { background: #f1f5f9; font-family: Tahoma, Arial, sans-serif; margin: 0; padding: 40px; display: flex; align-items: center; justify-content: center; min-height: 100vh; direction: rtl; }
            .container { background: #fff; padding: 40px; border-radius: 12px; text-align: center; max-width: 500px; width: 100%; box-shadow:0 4px 6px rgba(0,0,0,0.05); }
        </style>
    </head>
    <body>
        <div class="container">
            <h2 style="color:#ef4444;">مهلت ورود به این آزمون به پایان رسیده است</h2>
            <p>امکان شرکت در این آزمون در حال حاضر وجود ندارد.</p>
            <a href="<?php echo esc_url(home_url()); ?>" style="display:inline-block; margin-top:15px; color:#2563eb; text-decoration:none; font-weight:bold;">بازگشت به صفحه اصلی</a>
        </div>
    </body>
    </html>
    <?php
}

function wce_render_exam_frontend_interface($exam, $attempt, $questions, $remaining_seconds) {
    global $wpdb;
    $user = wp_get_current_user();
    $saved_answers_raw = $wpdb->get_results($wpdb->prepare("
        SELECT question_id, answer_data FROM {$wpdb->prefix}" . WCE_TABLE_ANSWERS . " WHERE attempt_id = %d
    ", $attempt->id));

    $existing_answers = array();
    if ($saved_answers_raw) {
        foreach ($saved_answers_raw as $sa) {
            $existing_answers[$sa->question_id] = $sa->answer_data;
        }
    }

    $allow_unanswered = intval($exam->allow_unanswered);
    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($exam->title); ?> - سامانه برگزاری آزمون آنلاین</title>
        <style>
            * { box-sizing: border-box; font-family: Tahoma, Arial, sans-serif; }
            body { background: #f8fafc; color: #0f172a; margin: 0; padding: 0; direction: rtl; user-select: none; -webkit-user-select: none; }
            header { background: #ffffff; border-bottom: 1px solid #e2e8f0; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
            .header-title { font-size: 18px; font-weight: bold; color: #1e293b; }
            .timer-badge { background: #fee2e2; color: #991b1b; padding: 8px 16px; border-radius: 20px; font-weight: bold; font-size: 16px; border: 1px solid #fca5a5; display: flex; align-items: center; gap: 6px; }
            .main-layout { max-width: 900px; margin: 30px auto; padding: 0 20px; }
            .q-card { background: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px; padding: 25px; margin-bottom: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: border-color 0.2s; }
            .q-card.unanswered-alert { border: 2px solid #ef4444; background: #fff5f5; }
            .q-title { font-size: 16px; font-weight: bold; margin-bottom: 20px; color: #0f172a; line-height: 1.6; }
            .q-type-tag { font-size: 12px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-weight: normal; margin-left: 8px; }
            .opt-label { display: flex; align-items: center; gap: 10px; padding: 12px 15px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 10px; cursor: pointer; transition: background 0.15s, border-color 0.15s; }
            .opt-label:hover { background: #f1f5f9; border-color: #cbd5e1; }
            .opt-label input[type="radio"] { width: 18px; height: 18px; cursor: pointer; }
            .desc-textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 12px; font-size: 14px; min-height: 120px; resize: vertical; outline: none; }
            .desc-textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
            .action-bar { text-align: center; margin: 40px 0; }
            .btn-submit { background: #059669; color: #ffffff; border: none; padding: 14px 40px; border-radius: 8px; font-size: 16px; font-weight: bold; cursor: pointer; transition: background 0.2s; box-shadow: 0 4px 6px -1px rgba(5, 150, 105, 0.3); }
            .btn-submit:hover { background: #047857; }
            .unanswered-warning-box { display: none; background: #fee2e2; border: 1px solid #f87171; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold; }
        </style>
    </head>
    <body oncontextmenu="return false;">
        <header>
            <div class="header-title"><?php echo esc_html($exam->title); ?> (داوطلب: <?php echo esc_html(wce_get_user_full_name($user->ID)); ?>)</div>
            <div class="timer-badge">
                ⏳ زمان باقیمانده: <span id="wce-timer">--:--</span>
            </div>
        </header>

        <div class="main-layout">
            <div id="unanswered-warning" class="unanswered-warning-box"></div>

            <form id="wce-exam-form">
                <?php
                $q_index = 1;
                foreach ($questions as $q) {
                    $saved_val = isset($existing_answers[$q->id]) ? $existing_answers[$q->id] : '';
                    echo '<div class="q-card" id="q-block-' . intval($q->id) . '" data-qid="' . intval($q->id) . '" data-qtype="' . esc_attr($q->type) . '">';
                    
                    if ($q->type === 'single') {
                        echo '<div class="q-title"><span class="q-type-tag">تستی</span> سوال ' . $q_index . ': ' . wp_kses_post($q->text) . '</div>';
                        $opts = json_decode($q->options, true);
                        if (is_array($opts)) {
                            foreach ($opts as $opt_idx => $opt_val) {
                                $opt_num = $opt_idx + 1;
                                $checked = ($saved_val == $opt_num) ? 'checked' : '';
                                echo '<label class="opt-label">';
                                echo '<input type="radio" name="q_' . intval($q->id) . '" value="' . $opt_num . '" ' . $checked . ' onchange="saveAnswer(' . intval($q->id) . ', this.value)" />';
                                echo '<span>' . esc_html($opt_val) . '</span>';
                                echo '</label>';
                            }
                        }
                    } else {
                        echo '<div class="q-title"><span class="q-type-tag">تشریحی</span> سوال ' . $q_index . ': ' . wp_kses_post($q->text) . '</div>';
                        echo '<textarea class="desc-textarea" name="q_' . intval($q->id) . '" placeholder="پاسخ تشریحی خود را اینجا وارد کنید..." onchange="saveAnswer(' . intval($q->id) . ', this.value)">' . esc_textarea($saved_val) . '</textarea>';
                    }

                    echo '</div>';
                    $q_index++;
                }
                ?>

                <div class="action-bar">
                    <button type="button" class="btn-submit" onclick="submitExamWithValidation()">ثبت و پایان آزمون</button>
                </div>
            </form>
        </div>

        <script>
            const attemptId = <?php echo intval($attempt->id); ?>;
            const ajaxUrl = "<?php echo admin_url('admin-ajax.php'); ?>";
            const nonce = "<?php echo wp_create_nonce('wce_exam_nonce'); ?>";
            const allowUnanswered = <?php echo $allow_unanswered; ?>;
            let remainingSeconds = <?php echo intval($remaining_seconds); ?>;

            // ساخت تایمر
            function updateTimerDisplay() {
                const minutes = Math.floor(remainingSeconds / 60);
                const seconds = remainingSeconds % 60;
                document.getElementById('wce-timer').innerText = 
                    (minutes < 10 ? '0' : '') + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

                if (remainingSeconds <= 0) {
                    alert('زمان آزمون شما به پایان رسید. پاسخ‌های شما به صورت خودکار ثبت می‌گردند.');
                    forceSubmitExam();
                } else {
                    remainingSeconds--;
                }
            }
            setInterval(updateTimerDisplay, 1000);
            updateTimerDisplay();

            // ذخیره آنلاین هر پاسخ
            function saveAnswer(questionId, answerData) {
                const formData = new FormData();
                formData.append('action', 'wce_save_answer');
                formData.append('security', nonce);
                formData.append('attempt_id', attemptId);
                formData.append('question_id', questionId);
                formData.append('answer_data', answerData);

                fetch(ajaxUrl, { method: 'POST', body: formData });
            }

            // برقراری لاگ امنیتی (خروج از صفحه/تغییر تب)
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    const formData = new FormData();
                    formData.append('action', 'wce_log_event');
                    formData.append('security', nonce);
                    formData.append('attempt_id', attemptId);
                    formData.append('log_type', 'خروج از صفحه / تغییر تب');
                    formData.append('details', 'کاربر در حین برگزاری آزمون از تب مرورگر خارج شد.');
                    fetch(ajaxUrl, { method: 'POST', body: formData });
                }
            });

            // بررسی الزامی بودن سوالات و ثبت
            function submitExamWithValidation() {
                const qCards = document.querySelectorAll('.q-card');
                let unansweredCount = 0;
                let unansweredNumbers = [];

                document.getElementById('unanswered-warning').style.display = 'none';
                qCards.forEach(card => card.classList.remove('unanswered-alert'));

                let index = 1;
                qCards.forEach(card => {
                    const qid = card.getAttribute('data-qid');
                    const qtype = card.getAttribute('data-qtype');
                    let isAnswered = false;

                    if (qtype === 'single') {
                        const checkedRadio = card.querySelector('input[type="radio"]:checked');
                        if (checkedRadio) isAnswered = true;
                    } else {
                        const txtArea = card.querySelector('textarea');
                        if (txtArea && txtArea.value.trim() !== '') isAnswered = true;
                    }

                    if (!isAnswered) {
                        unansweredCount++;
                        unansweredNumbers.push(index);
                        if (allowUnanswered === 0) {
                            card.classList.add('unanswered-alert');
                        }
                    }
                    index++;
                });

                // اگر سوالات نزده غیرمجاز است و سوالی بدون پاسخ مانده
                if (allowUnanswered === 0 && unansweredCount > 0) {
                    const warnBox = document.getElementById('unanswered-warning');
                    warnBox.innerText = 'پاسخ به تمامی سوالات الزامی است. شما به سوال(های) شماره [' + unansweredNumbers.join('، ') + '] پاسخ نداده‌اید.';
                    warnBox.style.display = 'block';
                    window.scrollTo({ top: warnBox.offsetTop - 100, behavior: 'smooth' });
                    return;
                }

                if (confirm('آیا از ثبت نهایی پاسخ‌ها و اتمام آزمون اطمینان دارید؟')) {
                    forceSubmitExam();
                }
            }

            function forceSubmitExam() {
                const formData = new FormData();
                formData.append('action', 'wce_submit_exam');
                formData.append('security', nonce);
                formData.append('attempt_id', attemptId);

                fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    window.location.reload();
                });
            }
        </script>
    </body>
    </html>
    <?php
}
