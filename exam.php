<?php
/*
* Plugin Name: Exam Management
* Plugin URI: https://example.com
* Description: A WordPress plugin for screening senior developer applicants with custom post types for students, exams, results.
* Version: 1.1.0
* Author: Sareen Masri
* Author URI: https://sareenmasri.com
* License: GPL-2.0+
*/

// Define constants
define( 'EM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Custom Post Types and Taxonomy
class EM_CPT {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_cpts' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomy' ) );
	}

	public static function register_cpts() {
		// Students
		register_post_type( 'em_student', array(
			'labels' => array(
				'name' => 'Students',
				'singular_name' => 'Student',
			),
			'public' => true,
			'capability_type' => 'post',
			'supports' => array( 'title', 'editor' ),
			'menu_icon' => 'dashicons-groups',
			'show_in_rest' => true,
		) );

		// Subjects
		register_post_type( 'em_subject', array(
			'labels' => array(
				'name' => 'Subjects',
				'singular_name' => 'Subject',
			),
			'public' => true,
			'capability_type' => 'post',
			'supports' => array( 'title' ),
			'menu_icon' => 'dashicons-book-alt',
			'show_in_rest' => true,
		) );

		// Exams
		register_post_type( 'em_exam', array(
			'labels' => array(
				'name' => 'Exams',
				'singular_name' => 'Exam',
			),
			'public' => true,
			'capability_type' => 'post',
			'supports' => array( 'title', 'editor' ),
			'menu_icon' => 'dashicons-book',
			'show_in_rest' => true,
		) );

		// Results
		register_post_type( 'em_result', array(
			'labels' => array(
				'name' => 'Results',
				'singular_name' => 'Result',
			),
			'public' => true,
			'capability_type' => 'post',
			'supports' => array( 'title' ),
			'menu_icon' => 'dashicons-performance',
			'show_in_rest' => true,
		) );
	}

	public static function register_taxonomy() {
		// Academic Terms
		register_taxonomy( 'em_term', array( 'em_exam' ), array(
			'labels' => array(
				'name' => 'Terms',
				'singular_name' => 'Term',
			),
			'public' => true,
			'hierarchical' => false,
			'show_ui' => true,
			'show_in_rest' => true,
		) );
	}
}

// Term Meta Fields (Start & End Date) - Admin Term Creation
add_action( 'em_term_add_form_fields', function () {
	?>
	<div class="form-field">
		<label for="em_term_start">Start Date</label>
		<input type="date" name="em_term_start" id="em_term_start" required>
	</div>
	<div class="form-field">
		<label for="em_term_end">End Date</label>
		<input type="date" name="em_term_end" id="em_term_end" required>
	</div>
	<?php
});
add_action( 'em_term_edit_form_fields', function ( $term ) {
	$start = get_term_meta( $term->term_id, 'em_term_start', true );
	$end   = get_term_meta( $term->term_id, 'em_term_end', true );
	?>
	<tr class="form-field">
		<th><label for="em_term_start">Start Date</label></th>
		<td><input type="date" name="em_term_start" value="<?php echo esc_attr( $start ); ?>"></td>
	</tr>
	<tr class="form-field">
		<th><label for="em_term_end">End Date</label></th>
		<td><input type="date" name="em_term_end" value="<?php echo esc_attr( $end ); ?>"></td>
	</tr>
	<?php
});
add_action( 'created_em_term', 'em_save_term_dates' );
add_action( 'edited_em_term', 'em_save_term_dates' );
function em_save_term_dates( $term_id ) {
	if ( isset( $_POST['em_term_start'], $_POST['em_term_end'] ) ) {
		update_term_meta( $term_id, 'em_term_start', sanitize_text_field( $_POST['em_term_start'] ) );
		update_term_meta( $term_id, 'em_term_end', sanitize_text_field( $_POST['em_term_end'] ) );
	}
}

// Exam Meta Box - Admin Exam Creation
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'em_exam_details',
		'Exam Details',
		'em_exam_details_metabox',
		'em_exam',
		'normal',
		'default'
	);
});
function em_exam_details_metabox( $post ) {
	wp_nonce_field( 'em_exam_save', 'em_exam_nonce' );

	$start   = get_post_meta( $post->ID, 'em_exam_start', true );
	$end     = get_post_meta( $post->ID, 'em_exam_end', true );
	$subject = get_post_meta( $post->ID, 'em_exam_subject', true );
	$subjects = get_posts( array( 'post_type'=>'em_subject', 'posts_per_page'=>-1 ) );
	?>

	<p><label><strong>Start Date & Time</strong></label><br>
	<input type="datetime-local" name="em_exam_start" value="<?php echo esc_attr($start); ?>" required></p>
	<p><label><strong>End Date & Time</strong></label><br>
	<input type="datetime-local" name="em_exam_end" value="<?php echo esc_attr($end); ?>" required></p>
	<p><label><strong>Subject</strong></label><br>
	<select name="em_exam_subject" required>
		<option value="">Select Subject</option>
		<?php foreach ($subjects as $sub): ?>
			<option value="<?php echo $sub->ID; ?>" <?php selected($subject,$sub->ID); ?>><?php echo esc_html($sub->post_title); ?></option>
		<?php endforeach; ?>
	</select></p>

	<?php
}
add_action( 'save_post_em_exam', 'em_save_exam_details' );
function em_save_exam_details( $post_id ) {
	if ( ! isset($_POST['em_exam_nonce']) || ! wp_verify_nonce($_POST['em_exam_nonce'], 'em_exam_save') ) return;
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( ! current_user_can('edit_post', $post_id) ) return;

	if ( isset($_POST['em_exam_start']) ) update_post_meta($post_id,'em_exam_start',sanitize_text_field($_POST['em_exam_start']));
	if ( isset($_POST['em_exam_end']) ) update_post_meta($post_id,'em_exam_end',sanitize_text_field($_POST['em_exam_end']));
	if ( isset($_POST['em_exam_subject']) ) update_post_meta($post_id,'em_exam_subject',absint($_POST['em_exam_subject']));
}

// Result Meta Box - Result Creation
add_action( 'add_meta_boxes', function () {
	add_meta_box(
		'em_result_entry',
		'Enter Exam Results',
		'em_result_metabox',
		'em_result',
		'normal',
		'default'
	);
});
function em_result_metabox( $post ) {
	wp_nonce_field( 'em_result_save', 'em_result_nonce' );

	$exams    = get_posts( array('post_type'=>'em_exam','posts_per_page'=>-1) );
	$students = get_posts( array('post_type'=>'em_student','posts_per_page'=>-1) );

	$saved_exam  = get_post_meta( $post->ID, 'em_exam_id', true );
	$saved_marks = get_post_meta( $post->ID, 'em_marks', true );
	if ( ! is_array( $saved_marks ) ) {
		$saved_marks = [];
	}
	?>
	<p>
		<label><strong>Select Exam</strong></label><br>
		<select name="em_result_exam" required>
			<option value="">Select Exam</option>
			<?php foreach ($exams as $exam): ?>
				<option value="<?php echo esc_attr($exam->ID); ?>"
					<?php selected( $saved_exam, $exam->ID ); ?>>
					<?php echo esc_html($exam->post_title); ?>
				</option>
			<?php endforeach; ?>
		</select>
	</p>
	<hr>
	<h4>Student Marks (out of 100)</h4>
	<?php foreach ($students as $student): ?>
		<p>
			<label><?php echo esc_html($student->post_title); ?></label><br>
			<input type="number"name="em_marks[<?php echo esc_attr($student->ID); ?>]"min="0"max="100"value="<?php echo esc_attr( $saved_marks[$student->ID] ?? '' ); ?>">
		</p>
	<?php endforeach; ?>
	<p>
		<button type="submit" class="button button-primary">Save Results</button>
	</p>
	<?php
}
add_action( 'save_post_em_result', 'em_handle_results_submission' );
function em_handle_results_submission( $post_id ) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
	if ( ! isset($_POST['em_result_nonce']) || ! wp_verify_nonce($_POST['em_result_nonce'], 'em_result_save') ) return;
	if ( ! current_user_can('edit_post', $post_id) ) return;
	if ( empty($_POST['em_result_exam']) || empty($_POST['em_marks']) ) return;

	$exam_id = absint($_POST['em_result_exam']);
	update_post_meta( $post_id, 'em_exam_id', $exam_id );

	$marks = [];
	foreach ($_POST['em_marks'] as $student_id => $mark) {
		if ($mark === '') continue;

		$marks[ absint($student_id) ] = min(100, max(0, intval($mark)));
	}
	update_post_meta( $post_id, 'em_marks', $marks );
}

// AJAX Exam List
add_action('wp_ajax_em_get_exams', 'em_get_exams_ajax');
add_action('wp_ajax_nopriv_em_get_exams', 'em_get_exams_ajax');

function em_get_exams_ajax() {
	// Security check
	if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'em_ajax_nonce') ) {
		wp_send_json_error('Invalid nonce');
	}

	$page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
	$per_page = 10;
	$offset = ($page-1) * $per_page;

	$current_time = current_time('Y-m-d H:i:s');

	$args = array(
		'post_type' => 'em_exam',
		'posts_per_page' => $per_page,
		'offset' => $offset,
	);

	$exams = get_posts($args);
	$data = array();

	foreach ($exams as $exam) {
		$start = get_post_meta($exam->ID,'em_exam_start',true);
		$end   = get_post_meta($exam->ID,'em_exam_end',true);
		$status = 'past';

		if ($current_time >= $start && $current_time <= $end) $status = 'current';
		elseif ($current_time < $start) $status = 'upcoming';

		$data[] = array(
			'id' => $exam->ID,
			'title' => $exam->post_title,
			'start' => $start,
			'end' => $end,
			'status' => $status,
		);
	}
    // Order exams: current -> upcoming -> past
    usort($data, function ($a, $b) {
        $order = array(
            'current'  => 1,
            'upcoming' => 2,
            'past'     => 3,
        );
	return $order[$a['status']] <=> $order[$b['status']];
});
    wp_send_json_success($data);
}
add_action('wp_enqueue_scripts','em_enqueue_scripts');
function em_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script(
        'em-ajax',
        EM_PLUGIN_URL . 'js/em-ajax.js',
        array('jquery'),
        filemtime( EM_PLUGIN_DIR . 'js/em-ajax.js' ),
        true
    );
    wp_localize_script('em-ajax', 'em_ajax_obj', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('em_ajax_nonce'),
    ));
}

// Shortcode
add_shortcode('em_top_students', 'em_top_students_shortcode');
function em_top_students_shortcode() {
    $terms = get_terms(array(
        'taxonomy'   => 'em_term',
        'hide_empty' => false,
        'meta_key'   => 'em_term_start',
        'orderby'    => 'meta_value',
        'order'      => 'DESC',
    ));

    if (empty($terms) || is_wp_error($terms)) {
        return '<p>No terms found.</p>';
    }

    $output = '';

    foreach ($terms as $term) {

        // Exams in this term
        $exam_ids = get_posts(array(
            'post_type'      => 'em_exam',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'tax_query'      => array(
                array(
                    'taxonomy' => 'em_term',
                    'field'    => 'term_id',
                    'terms'    => $term->term_id,
                ),
            ),
            'fields' => 'ids',
        ));

        if (empty($exam_ids)) continue;

        // Results related to those exams
        $results = get_posts(array(
            'post_type'      => 'em_result',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'em_exam_id',
                    'value'   => $exam_ids,
                    'compare' => 'IN',
                ),
            ),
            'fields'        => 'ids',
            'no_found_rows' => true,
        ));

        if (empty($results)) continue;

        $student_totals = [];

        foreach ($results as $result_id) {
            $marks = get_post_meta($result_id, 'em_marks', true);
            if (!is_array($marks)) continue;

            foreach ($marks as $student_id => $mark) {
                $student_id = (int) $student_id;
                $mark = (int) $mark;

                if ($mark <= 0) continue;

                if (!isset($student_totals[$student_id])) {
                    $student_totals[$student_id] = 0;
                }

                $student_totals[$student_id] += $mark;
            }
        }

        if (empty($student_totals)) continue;

        arsort($student_totals);
        $top_students = array_slice($student_totals, 0, 3, true);

        $output .= '<h3>' . esc_html($term->name) . '</h3><ol>';

        foreach ($top_students as $student_id => $total) {
            $student = get_post($student_id);
            if ($student) {
                $output .= '<li>' .
                    esc_html($student->post_title) .
                    ' – <strong>' . esc_html($total) . '</strong>' .
                '</li>';
            }
        }
        $output .= '</ol>';
    }
    return $output ?: '<p>No results found.</p>';
}

// Bulk Import Menu
add_action('admin_menu','em_bulk_import_menu');
function em_bulk_import_menu(){
    add_submenu_page(
        'edit.php?post_type=em_result',
        'Bulk Import Results',           
        'Bulk Import Results',           
        'manage_options',                
        'em_bulk_import',                
        'em_bulk_import_page'            
    );
}
function em_bulk_import_page(){
    ?>
    <div class="wrap">
        <h1>Bulk Import Exam Results</h1>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="em_csv_file" accept=".csv" required>
            <?php submit_button('Import Results'); ?>
        </form>
    </div>
    <?php

    if(isset($_FILES['em_csv_file'])){
        em_handle_csv_import($_FILES['em_csv_file']);
    }
}
function em_handle_csv_import($file){

    if (!file_exists($file['tmp_name'])) return;
    $handle = fopen($file['tmp_name'], 'r');
    if (!$handle) return;
    $row = 0;
    $errors = [];
    $exam_results = [];

    while (($data = fgetcsv($handle, 1000, ',')) !== false) {
        $row++;

        if ($row === 1) continue;

        if (count($data) < 3) {
            $errors[] = "Row $row: Invalid format";
            continue;
        }

        [$student_id, $exam_id, $mark] = $data;

        $student_id = absint($student_id);
        $exam_id    = absint($exam_id);
        $mark       = min(100, max(0, intval($mark)));

        $student = get_post($student_id);
        if (!$student || $student->post_type !== 'em_student') {
            $errors[] = "Row $row: Student ID $student_id not found";
            continue;
        }

        $exam = get_post($exam_id);
        if (!$exam || $exam->post_type !== 'em_exam') {
            $errors[] = "Row $row: Exam ID $exam_id not found";
            continue;
        }

        if (!isset($exam_results[$exam_id])) {
            $exam_results[$exam_id] = [];
        }

        $exam_results[$exam_id][$student_id] = $mark;
    }

    fclose($handle);
    $imported = 0;

    foreach ($exam_results as $exam_id => $marks) {

        $existing = get_posts([
            'post_type'      => 'em_result',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => 'em_exam_id',
                    'value' => $exam_id,
                ],
            ],
            'fields' => 'ids',
        ]);

        if (!empty($existing)) {
            $result_id = $existing[0];
            $old_marks = get_post_meta($result_id, 'em_marks', true);

            if (!is_array($old_marks)) {
                $old_marks = [];
            }

            $new_marks = array_merge($old_marks, $marks);
            update_post_meta($result_id, 'em_marks', $new_marks);
        } else {
            $exam = get_post($exam_id);
            $exam_title = $exam ? $exam->post_title : 'Exam #' . $exam_id;

            $result_id = wp_insert_post([
                'post_type'   => 'em_result',
                'post_status' => 'publish',
                'post_title'  => 'Results – ' . $exam_title ,
                'meta_input'  => [
                    'em_exam_id' => $exam_id,
                    'em_marks'   => $marks,
                ],
            ]);
        }

        if ($result_id) {
            $imported += count($marks);
        }
    }
    echo '<div class="notice notice-success"><p>';
    echo 'Imported / updated <strong>' . esc_html($imported) . '</strong> student marks successfully.';
    echo '</p></div>';

    if (!empty($errors)) {
        echo '<div class="notice notice-error"><p>';
        echo implode('<br>', array_map('esc_html', $errors));
        echo '</p></div>';
    }
}

// Student Statistics Report
add_action('admin_menu','em_student_stats_menu');
function em_student_stats_menu(){
    add_submenu_page(
        'edit.php?post_type=em_result',
        'Student Statistics',
        'Student Statistics',
        'manage_options',
        'em_student_stats',
        'em_student_stats_page'
    );
}
function em_student_stats_page(){

    $terms = get_terms([
        'taxonomy'   => 'em_term',
        'hide_empty' => false,
        'meta_key'   => 'em_term_start',
        'orderby'    => 'meta_value',
        'order'      => 'DESC',
    ]);

    $students = get_posts([
        'post_type'   => 'em_student',
        'numberposts' => -1,
    ]);

    echo '<div class="wrap"><h1>Student Statistics Report</h1>';

    echo '<form method="post" action="'.admin_url('admin-post.php').'">';
    echo '<input type="hidden" name="action" value="em_export_pdf">';
    submit_button('Export as PDF');
    echo '</form>';

    echo '<table class="widefat striped">';
    echo '<thead><tr><th>Student</th>';

    foreach ($terms as $term) {
        echo '<th>'.esc_html($term->name).' (Total)</th>';
    }

    echo '<th>Average</th></tr></thead><tbody>';

    foreach ($students as $student) {

        echo '<tr><td>'.esc_html($student->post_title).'</td>';

        $grand_total = 0;
        $term_count  = 0;

        foreach ($terms as $term) {

            // Exams in this term
            $exam_ids = get_posts([
                'post_type'   => 'em_exam',
                'numberposts' => -1,
                'tax_query'   => [
                    [
                        'taxonomy' => 'em_term',
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ],
                ],
                'fields' => 'ids',
            ]);

            $term_total = 0;

            if ($exam_ids) {

                // Results for those exams
                $results = get_posts([
                    'post_type'   => 'em_result',
                    'numberposts' => -1,
                    'meta_query'  => [
                        [
                            'key'     => 'em_exam_id',
                            'value'   => $exam_ids,
                            'compare' => 'IN',
                        ],
                    ],
                    'fields' => 'ids',
                ]);

                foreach ($results as $result_id) {
                    $marks = get_post_meta($result_id, 'em_marks', true);
                    if (is_array($marks) && isset($marks[$student->ID])) {
                        $term_total += (int) $marks[$student->ID];
                    }
                }
            }

            echo '<td>'.$term_total.'</td>';

            $grand_total += $term_total;
            $term_count++;
        }

        $average = $term_count ? round($grand_total / $term_count, 2) : 0;
        echo '<td>'.$average.'</td></tr>';
    }

    echo '</tbody></table></div>';
}
// Export PDF Handler
add_action('admin_post_em_export_pdf', 'em_export_pdf_handler');
function em_export_pdf_handler(){

    if (!current_user_can('manage_options')) wp_die('Unauthorized');

    while (ob_get_level()) ob_end_clean();
    ob_start();

    if (!class_exists('TCPDF')) {
        require_once EM_PLUGIN_DIR.'lib/tcpdf/tcpdf.php';
    }

    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->SetFont('helvetica','',11);

    $terms = get_terms([
        'taxonomy'   => 'em_term',
        'hide_empty' => false,
        'meta_key'   => 'em_term_start',
        'orderby'    => 'meta_value',
        'order'      => 'DESC',
    ]);

    $students = get_posts([
        'post_type'   => 'em_student',
        'numberposts' => -1,
    ]);

    $html = '<h1>Student Statistics Report</h1><table border="1" cellpadding="4">';
    $html .= '<tr><th>Student</th>';

    foreach ($terms as $term) {
        $html .= '<th>'.$term->name.'</th>';
    }

    $html .= '<th>Average</th></tr>';

    foreach ($students as $student) {

        $html .= '<tr><td>'.$student->post_title.'</td>';

        $grand_total = 0;
        $term_count  = 0;

        foreach ($terms as $term) {

            $exam_ids = get_posts([
                'post_type'   => 'em_exam',
                'numberposts' => -1,
                'tax_query'   => [
                    [
                        'taxonomy' => 'em_term',
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ],
                ],
                'fields' => 'ids',
            ]);

            $term_total = 0;

            if ($exam_ids) {
                $results = get_posts([
                    'post_type'   => 'em_result',
                    'numberposts' => -1,
                    'meta_query'  => [
                        [
                            'key'     => 'em_exam_id',
                            'value'   => $exam_ids,
                            'compare' => 'IN',
                        ],
                    ],
                    'fields' => 'ids',
                ]);

                foreach ($results as $result_id) {
                    $marks = get_post_meta($result_id, 'em_marks', true);
                    if (is_array($marks) && isset($marks[$student->ID])) {
                        $term_total += (int) $marks[$student->ID];
                    }
                }
            }

            $html .= '<td>'.$term_total.'</td>';
            $grand_total += $term_total;
            $term_count++;
        }

        $average = $term_count ? round($grand_total / $term_count, 2) : 0;
        $html .= '<td>'.$average.'</td></tr>';
    }

    $html .= '</table>';

    $pdf->writeHTML($html);
    ob_end_clean();
    $pdf->Output('student_statistics.pdf','D');
    exit;
}

// Initialize plugin
function em_init() {
	EM_CPT::init();
}
add_action('plugins_loaded','em_init');