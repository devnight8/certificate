<?php
/*
Plugin Name: Student Certificates
Description: Create and display beautiful course completion certificates.
Version: 6.1
Author: siteteme
*/

// ثبت نوع پست سفارشی
add_action('init', function () {
    register_post_type('student_certificate', [
        'labels' => [
            'name' => 'Certificates',
            'singular_name' => 'Certificate'
        ],
        'public' => true,
        'has_archive' => true,
        'supports' => ['title'],
        'menu_icon' => 'dashicons-awards',
    ]);
});

// افزودن متاباکس
add_action('add_meta_boxes', function () {
    add_meta_box('sc_certificate_details', 'Certificate Details', 'sc_certificate_meta_box_callback', 'student_certificate', 'normal', 'high');
});

function sc_certificate_meta_box_callback($post)
{
    wp_nonce_field('sc_save_meta', 'sc_meta_nonce');
    $fields = ['student_name', 'course_title', 'instructor_name', 'certificate_id', 'completion_date'];
    foreach ($fields as $field) {
        $$field = get_post_meta($post->ID, $field, true);
    }
    foreach ($fields as $field) {
        $label = ucwords(str_replace('_', ' ', $field));
        $type = $field === 'completion_date' ? 'date' : 'text';
        echo '<div class="sc-field">
                <label for="' . esc_attr($field) . '">' . esc_html($label) . '</label>
                <input type="' . $type . '" name="' . esc_attr($field) . '" id="' . esc_attr($field) . '" value="' . esc_attr($$field) . '" style="width:100%;padding:5px;margin-bottom:10px;">
            </div>';
    }
}

// ذخیره متا دیتا با بررسی امنیت
add_action('save_post', function ($post_id) {
    if (get_post_type($post_id) !== 'student_certificate') return;
    if (!isset($_POST['sc_meta_nonce']) || !wp_verify_nonce($_POST['sc_meta_nonce'], 'sc_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $fields = ['student_name', 'course_title', 'instructor_name', 'certificate_id', 'completion_date'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
        }
    }
});

// استایل
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('certificate-style', plugin_dir_url(__FILE__) . 'assets/style.css');
});

// شورتکد نمایش گواهی
add_shortcode('student_certificate', function ($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $post_id = (int)$atts['id'];
    if (get_post_type($post_id) !== 'student_certificate') return '';

    $meta = function ($key) use ($post_id) {
        return esc_html(get_post_meta($post_id, $key, true));
    };

    ob_start();
    ?>
    <div class="certificate-container">
        <div class="certificate">
            <div class="ribbon">
                <img src="<?= plugin_dir_url(__FILE__) ?>assets/gold-badge.png" alt="Gold Badge" />
            </div>
            <h2 class="certificate-title">CERTIFICATE</h2>
            <p class="certificate-subtitle">OF ACHIEVEMENT</p>
            <h1 class="student-name"><?= $meta('student_name') ?></h1>
            <p class="certificate-text">
                گواهی می‌شود که <strong><?= $meta('student_name') ?></strong> با موفقیت دوره <strong><?= $meta('course_title') ?></strong> را به پایان رسانده است.<br>
                تاریخ صدور: <strong><?= $meta('completion_date') ?></strong>
            </p>
            <div class="certificate-footer">
                <div class="signature"><?= $meta('instructor_name') ?></div>
            </div>
            <p class="certificate-id">Certificate ID: <?= $meta('certificate_id') ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
});

// شورتکد فرم ایجاد گواهی و تولید QR
add_shortcode('certificate_form', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_name'])) {
        $student_name = sanitize_text_field($_POST['student_name']);
        $course_title = sanitize_text_field($_POST['course_title']);
        $instructor_name = sanitize_text_field($_POST['instructor_name']);
        $completion_date = sanitize_text_field($_POST['completion_date']);
        $certificate_id = strtoupper(uniqid('CERT-'));

        // ذخیره در دیتابیس به عنوان پست
        $post_id = wp_insert_post([
            'post_title' => $student_name . ' - ' . $course_title,
            'post_type' => 'student_certificate',
            'post_status' => 'publish'
        ]);

        update_post_meta($post_id, 'student_name', $student_name);
        update_post_meta($post_id, 'course_title', $course_title);
        update_post_meta($post_id, 'instructor_name', $instructor_name);
        update_post_meta($post_id, 'completion_date', $completion_date);
        update_post_meta($post_id, 'certificate_id', $certificate_id);

        // تولید QR Code
        require_once plugin_dir_path(__FILE__) . 'phpqrcode/qrlib.php';
        $verify_url = site_url('/certificate-verify/?cert_id=' . urlencode($certificate_id));
        $upload_dir = wp_upload_dir();
        $qr_file = $upload_dir['basedir'] . "/qr_$certificate_id.png";
        QRcode::png($verify_url, $qr_file, QR_ECLEVEL_L, 4);
        $qr_url = $upload_dir['baseurl'] . "/qr_$certificate_id.png";

        ob_start();
        ?>
        <div class="certificate-container">
            <div class="certificate">
                <div class="ribbon">
                    <img src="<?= plugin_dir_url(__FILE__) ?>assets/gold-badge.png" alt="Gold Badge" />
                </div>
                <h2 class="certificate-title">CERTIFICATE</h2>
                <p class="certificate-subtitle">OF ACHIEVEMENT</p>
                <h1 class="student-name"><?= esc_html($student_name) ?></h1>
                <p class="certificate-text">
                    گواهی می‌شود که <strong><?= esc_html($student_name) ?></strong> با موفقیت دوره <strong><?= esc_html($course_title) ?></strong> را به پایان رسانده است.<br>
                    تاریخ صدور: <strong><?= esc_html($completion_date) ?></strong>
                </p>
                <div class="certificate-footer">
                    <div class="signature"><?= esc_html($instructor_name) ?></div>
                </div>
                <p class="certificate-id">Certificate ID: <?= esc_html($certificate_id) ?></p>
            </div>
        </div>
        <div class="qr-code" style="text-align:center; margin-top: 20px;">
            <p style="font-size:14px; color:#777;">برای تایید اعتبار گواهی، QR کد زیر را اسکن کنید:</p>
            <img src="<?= esc_url($qr_url) ?>" alt="QR Code" width="120">
        </div>
        <div class="download-btn-wrapper">
            <button class="download-btn-cer" onclick="window.print()">دانلود و پرینت</button>
        </div>
        <?php
        return ob_get_clean();
    } else {
        ob_start();
        ?>
        <form method="post" class="certificate-form">
            <label>نام دانشجو:<br><input type="text" name="student_name" required></label><br>
            <label>نام دوره:<br><input type="text" name="course_title" required></label><br>
            <label>تاریخ صدور:<br><input type="date" name="completion_date" required></label><br>
            <label>نام مربی:<br><input type="text" name="instructor_name" required></label><br>
            <button type="submit">ایجاد گواهی</button>
        </form>
        <?php
        return ob_get_clean();
    }
});

// شورتکد تایید گواهی
add_shortcode('certificate_verify', function () {
    $cert_id = sanitize_text_field($_GET['cert_id'] ?? '');
    if (!$cert_id) return '<p class="code-empty">کدی وارد نشده است.</p>';

    $args = [
        'post_type' => 'student_certificate',
        'meta_query' => [[
            'key' => 'certificate_id',
            'value' => $cert_id,
            'compare' => '='
        ]]
    ];
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        $post = $query->posts[0];
        $name = get_post_meta($post->ID, 'student_name', true);
        $course = get_post_meta($post->ID, 'course_title', true);
        $date = get_post_meta($post->ID, 'completion_date', true);
        return "<div class='ver-govahi'>
		<p>✅ این گواهی معتبر است.</p>
                <p>نام: <strong>$name</strong><br>دوره: <strong>$course</strong><br></p>
		</div>";
    } else {
        return "<p>❌ گواهی با این کد پیدا نشد یا نامعتبر است.</p>";
    }
});
