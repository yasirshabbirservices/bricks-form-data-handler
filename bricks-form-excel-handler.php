<?php

/**
 * Plugin Name: Bricks Form Data Manager
 * Plugin URI: https://yasirshabbir.com
 * Description: Professional form submission handler for Bricks Builder - Saves data to Excel files for multiple forms
 * Version: 2.11.0
 * Author: Yasir Shabbir
 * Author URI: https://yasirshabbir.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

// Load PhpSpreadsheet library
require_once __DIR__ . '/autoload.php';

class Bricks_Form_Data_Manager
{
    private $data_dir;
    private $version = '2.11.0';

    // Form configurations - Map form IDs to their respective XLSX files
    private $form_configs = array(
        // Original forms (page 1)
        'rwffis' => 'motodinamiki.xlsx', // New Entry Form (original)
        'xaiama' => 'motodinamiki.xlsx', // Update Entry Form (original)

        // New forms (page 2)
        'dfejuq' => 'motodiktio.xlsx', // New Entry Form (new page)
        'zqowog' => 'motodiktio.xlsx', // Update Entry Form (new page)
    );

    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->data_dir = $upload_dir['basedir'] . '/form-data';

        // Create directory if it doesn't exist
        if (!file_exists($this->data_dir)) {
            wp_mkdir_p($this->data_dir);
            $this->protect_directory();
        }

        // Add custom capability on plugin activation
        register_activation_hook(__FILE__, array($this, 'add_custom_capability'));

        // Hook into Bricks form actions
        add_action('bricks/form/custom_action', array($this, 'handle_form_submission'), 10, 1);

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'handle_download'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        // Add token generation hook for debugging
        add_action('wp_head', array($this, 'debug_token_generation'));
    }

    /**
     * Debug token generation in console
     */
    public function debug_token_generation()
    {
        if (current_user_can('manage_options')) {
            echo '<script>
            console.log("Bricks Form Data Manager v' . $this->version . ' loaded");
            </script>';
        }
    }

    /**
     * Add custom capability on plugin activation
     */
    public function add_custom_capability()
    {
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('view_form_submissions');
        }
    }

    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook)
    {
        if ($hook !== 'toplevel_page_form-submissions') {
            return;
        }
        wp_enqueue_style('lato-font', 'https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap');
    }

    /**
     * Protect the directory
     */
    private function protect_directory()
    {
        $htaccess_content = "# Protect form submission files\nOrder Deny,Allow\nDeny from all\n<Files *.xlsx>\nAllow from all\n</Files>\n<Files *.xls>\nAllow from all\n</Files>\n<Files *.xml>\nAllow from all\n</Files>\n<Files *.csv>\nAllow from all\n</Files>";
        file_put_contents($this->data_dir . '/.htaccess', $htaccess_content);
        file_put_contents($this->data_dir . '/index.php', '<?php // Silence is golden');
    }

    /**
     * Get XLSX file path based on form ID
     */
    private function get_xlsx_file_path($form_id)
    {
        $filename = isset($this->form_configs[$form_id]) ? $this->form_configs[$form_id] : 'motodinamiki.xlsx';
        return $this->data_dir . '/' . $filename;
    }

    /**
     * Get token field ID based on form ID
     */
    private function get_token_field_id($form_id)
    {
        // Map form IDs to their respective token field IDs
        $token_fields = array(
            'rwffis' => 'hkxzqe',
            'xaiama' => 'ftmsoi',
            'dfejuq' => 'plqrnx',
            'zqowog' => 'sdxbvh'
        );

        return isset($token_fields[$form_id]) ? $token_fields[$form_id] : '';
    }

    /**
     * Handle Bricks form submission
     */
    public function handle_form_submission($form)
    {
        $fields = $_POST;

        // Get form ID from submitted data
        $form_id = isset($fields['formId']) ? sanitize_text_field($fields['formId']) : '';

        // Debug: Log form ID and all fields
        error_log('Form submission received. Form ID: ' . $form_id);

        if (empty($form_id) || !isset($this->form_configs[$form_id])) {
            $filename = isset($this->form_configs[$form_id]) ? $this->form_configs[$form_id] : 'motodinamiki.xlsx';
            error_log('Unknown form ID: ' . $form_id . '. Using default file: ' . $filename);
        } else {
            $filename = $this->form_configs[$form_id];
            error_log('Form ID recognized: ' . $form_id . '. Using file: ' . $filename);
        }

        // Get the appropriate XLSX file for this form
        $xlsx_file = $this->get_xlsx_file_path($form_id);

        // Get token field ID for this form
        $token_field_id = $this->get_token_field_id($form_id);

        // Regular form submission - CAPTURE ENTRY ID AND TOKEN
        $data = array(
            'entry_id' => isset($fields['form-field-ehvmdc']) ? sanitize_text_field($fields['form-field-ehvmdc']) : '',
            'timestamp' => current_time('Y-m-d H:i:s'),
            'email' => isset($fields['form-field-2ba381']) ? sanitize_email($fields['form-field-2ba381']) : '',
            'phone' => isset($fields['form-field-vsjpsv']) ? sanitize_text_field($fields['form-field-vsjpsv']) : '',
            'newsletter_consent' => '',
            'email_consent' => 'No',
            'phone_sms_consent' => 'No',
            'mail_consent' => 'No',
            'terms_accepted' => 'No',
            'token' => '' // Initialize token field
        );

        // Capture token if the field exists for this form
        if ($token_field_id && isset($fields['form-field-' . $token_field_id])) {
            $data['token'] = sanitize_text_field($fields['form-field-' . $token_field_id]);
            error_log('Token captured from form field: ' . $data['token'] . ' from field: form-field-' . $token_field_id);
        } else {
            // Check if token is in other possible field names
            $possible_token_fields = array(
                'form-field-hkxzqe',  // rwffis form
                'form-field-ftmsoi',  // xaiama form
                'form-field-plqrnx',  // dfejuq form
                'form-field-sdxbvh'   // zqowog form
            );

            foreach ($possible_token_fields as $field_name) {
                if (isset($fields[$field_name]) && !empty($fields[$field_name])) {
                    $data['token'] = sanitize_text_field($fields[$field_name]);
                    error_log('Token captured from alternative field: ' . $data['token'] . ' from field: ' . $field_name);
                    break;
                }
            }

            // If still no token, generate one (fallback)
            if (empty($data['token'])) {
                $data['token'] = substr(wp_generate_password(8, false), 0, 8);
                error_log('No token found in form fields. Generated fallback token: ' . $data['token']);
            }
        }

        error_log('Processing submission for email: ' . $data['email'] . ' with entry ID: ' . $data['entry_id'] . ' token: ' . $data['token'] . ' to file: ' . basename($xls_file));

        // Helper function to get radio button value
        function get_radio_value($field_value)
        {
            if (is_array($field_value)) {
                return isset($field_value[0]) ? sanitize_text_field($field_value[0]) : '';
            }
            return sanitize_text_field($field_value);
        }

        // Handle newsletter consent - handle both array and string
        $newsletter_value = '';
        if (isset($fields['form-field-wfejpt'])) {
            $newsletter_value = get_radio_value($fields['form-field-wfejpt']);
            $data['newsletter_consent'] = $newsletter_value;
        }

        // ==================================================
        // CRITICAL BUSINESS LOGIC:
        // If user selects "I do not agree" for newsletter,
        // ALL communication channels must be "No"
        // ==================================================
        $user_disagreed = ($newsletter_value === 'I do not agree');

        if ($user_disagreed) {
            // User selected "I do not agree" - ALL communication channels must be "No"
            $data['email_consent'] = 'No';
            $data['phone_sms_consent'] = 'No';
            $data['mail_consent'] = 'No';
        } else {
            // User agreed or hasn't selected anything - process communication channels normally

            // Handle E-Mail consent (form-field-vkpqeq)
            if (isset($fields['form-field-vkpqeq'])) {
                $email_consent = get_radio_value($fields['form-field-vkpqeq']);
                $data['email_consent'] = ($email_consent === 'Yes') ? 'Yes' : 'No';
            }

            // Handle Telephone/SMS consent (form-field-gakgwk)
            if (isset($fields['form-field-gakgwk'])) {
                $phone_consent = get_radio_value($fields['form-field-gakgwk']);
                $data['phone_sms_consent'] = ($phone_consent === 'Yes') ? 'Yes' : 'No';
            }

            // Handle Mail consent (form-field-ytzddf)
            if (isset($fields['form-field-ytzddf'])) {
                $mail_consent = get_radio_value($fields['form-field-ytzddf']);
                $data['mail_consent'] = ($mail_consent === 'Yes') ? 'Yes' : 'No';
            }
        }

        // Handle terms acceptance
        if (isset($fields['form-field-csfous'])) {
            $terms_value = get_radio_value($fields['form-field-csfous']);
            // Check if any value is set (not empty string)
            $data['terms_accepted'] = (!empty($terms_value)) ? 'Yes' : 'No';
        }

        // Debug: Log final data
        error_log('Final data to save: ' . print_r($data, true));

        $this->save_to_xlsx($data, $xlsx_file);
    }

    /**
     * Save data to XLSX file with duplicate prevention based on email
     */
    private function save_to_xlsx($data, $xlsx_file)
    {
        $file_exists = file_exists($xlsx_file);
        $existing_data = array();
        $headers = array('Entry ID', 'Timestamp', 'Email', 'Phone', 'Newsletter Consent', 'E-Mail Consent', 'Phone/SMS Consent', 'Mail Consent', 'Terms Accepted', 'Token');

        // Read existing data if file exists
        if ($file_exists && filesize($xlsx_file) > 0) {
            try {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                $spreadsheet = $reader->load($xlsx_file);
                $worksheet = $spreadsheet->getActiveSheet();
                
                // Get the highest row number
                $highestRow = $worksheet->getHighestRow();
                
                // Read headers from first row
                $headers = array();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $headers[] = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
                }
                
                // Read data rows (skip header row 1)
                for ($row = 2; $row <= $highestRow; $row++) {
                    $row_data = array();
                    for ($col = 1; $col <= $highestColumnIndex; $col++) {
                        $row_data[] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                    }
                    $existing_data[] = $row_data;
                }
            } catch (Exception $e) {
                error_log('Error reading XLSX file: ' . $e->getMessage());
                $existing_data = array();
            }
        }

        // If file exists but doesn't have Token column, add it
        if ($file_exists && count($headers) == 9) {
            $headers[] = 'Token';
            // Add empty token value to existing data
            foreach ($existing_data as &$row) {
                $row[] = '';
            }
        }

        $updated = false;
        $submission_email = strtolower(trim($data['email']));

        // Check if this email already exists in existing data
        foreach ($existing_data as $index => $row) {
            // Email is at index 2 (0-based index)
            if (isset($row[2])) {
                $existing_email = strtolower(trim($row[2]));
                if ($existing_email === $submission_email) {
                    // Update existing entry - use new entry ID from form
                    $updated_row = array(
                        $data['entry_id'], // Use the NEW entry ID from current submission
                        $data['timestamp'],
                        $data['email'],
                        $data['phone'],
                        $data['newsletter_consent'],
                        $data['email_consent'],
                        $data['phone_sms_consent'],
                        $data['mail_consent'],
                        $data['terms_accepted']
                    );

                    // Add token (index 9 if we have token column)
                    if (count($headers) > 9) {
                        // Keep existing token if available, otherwise use new one
                        $updated_row[] = isset($row[9]) && !empty($row[9]) ? $row[9] : $data['token'];
                    } else {
                        $updated_row[] = $data['token'];
                    }

                    $existing_data[$index] = $updated_row;
                    $updated = true;
                    error_log('Updated existing entry for email: ' . $submission_email . ' with new entry ID: ' . $data['entry_id'] . ' token: ' . $data['token'] . ' in file: ' . basename($xlsx_file));
                    break;
                }
            }
        }

        // If not updated, add as new entry
        if (!$updated) {
            $new_row = array(
                $data['entry_id'],
                $data['timestamp'],
                $data['email'],
                $data['phone'],
                $data['newsletter_consent'],
                $data['email_consent'],
                $data['phone_sms_consent'],
                $data['mail_consent'],
                $data['terms_accepted'],
                $data['token']
            );
            $existing_data[] = $new_row;
            error_log('Added NEW entry for email: ' . $submission_email . ' with entry ID: ' . $data['entry_id'] . ' token: ' . $data['token'] . ' to file: ' . basename($xlsx_file));
        }

        // Write all data back to XLSX
        $this->write_xlsx_file($headers, $existing_data, $xlsx_file);

        return true;
    }

    /**
     * Write data to XLSX file using PhpSpreadsheet
     */
    private function write_xlsx_file($headers, $data, $xlsx_file)
    {
        try {
            // Create new Spreadsheet object
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $worksheet = $spreadsheet->getActiveSheet();
            $worksheet->setTitle('Form Submissions');

            // Write headers
            $col = 1;
            foreach ($headers as $header) {
                $worksheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }

            // Style header row
            $headerStyle = $worksheet->getStyle('A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE0E0E0');

            // Write data rows
            $row = 2;
            foreach ($data as $rowData) {
                $col = 1;
                foreach ($rowData as $cellValue) {
                    $worksheet->setCellValueByColumnAndRow($col, $row, $cellValue);
                    $col++;
                }
                $row++;
            }

            // Auto-size columns
            foreach (range(1, count($headers)) as $col) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                $worksheet->getColumnDimension($columnLetter)->setAutoSize(true);
            }

            // Save to file
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save($xlsx_file);
            chmod($xlsx_file, 0644);

            error_log('XLSX file saved successfully with ' . count($data) . ' entries to: ' . basename($xlsx_file));
            return true;
        } catch (Exception $e) {
            error_log('Error writing XLSX file: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_menu_page(
            'Form Submissions',
            'Form Data',
            'view_form_submissions',
            'form-submissions',
            array($this, 'admin_page'),
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTQgMkg2QzQuOSAyIDQgMi45IDQgNFYyMEM0IDIxLjEgNC44OSAyMiA1Ljk5IDIySDE4QzE5LjEgMjIgMjAgMjEuMSAyMCAyMFY4TDE0IDJaMTYgMThIOFYxNkg4VjE4SDE2Wk0xNiAxNEg4VjEySDhWMTRIx2Wk0xMyA5VjMuNUwxOC41IDlaIiBmaWxsPSIjMTZlNzkxIi8+PC9zdmc+',
            30
        );
    }

    /**
     * Admin page
     */
    public function admin_page()
    {
        // Get file information for both files
        $files_info = array(
            'motodinamiki.xlsx' => array(
                'name' => 'motodinamiki.xlsx',
                'path' => $this->data_dir . '/motodinamiki.xlsx',
                'label' => 'Original Forms Data',
                'forms' => 'Forms: rwffis, xaiama'
            ),
            'motodiktio.xlsx' => array(
                'name' => 'motodiktio.xlsx',
                'path' => $this->data_dir . '/motodiktio.xlsx',
                'label' => 'New Page Forms Data',
                'forms' => 'Forms: dfejuq, zqowog'
            )
        );

        $success_message = '';
        if (isset($_GET['cleared']) && $_GET['cleared'] == '1') {
            $cleared_file = isset($_GET['file']) ? sanitize_text_field($_GET['file']) : '';
            if ($cleared_file) {
                $success_message = '<div class="ys-alert ys-alert-success">' . esc_html($cleared_file) . ' cleared successfully.</div>';
            } else {
                $success_message = '<div class="ys-alert ys-alert-success">File cleared successfully.</div>';
            }
        }

        $this->render_admin_page($files_info, $success_message);
    }

    /**
     * Render admin page
     */
    private function render_admin_page($files_info, $success_message)
    {
?>
        <div class="ys-wrapper">
            <div class="ys-container">
                <div class="ys-header">
                    <div class="ys-header-content">
                        <div class="ys-brand">
                            <svg class="ys-logo" width="32" height="32" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M14 2H6C4.9 2 4 2.9 4 4V20C4 21.1 4.89 22 5.99 22H18C19.1 22 20 21.1 20 20V8L14 2ZM16 18H8V16H16V18ZM16 14H8V12H16V14ZM13 9V3.5L18.5 9H13Z"
                                    fill="#16e791" />
                            </svg>
                            <div>
                                <h1 class="ys-title">Form Data Manager</h1>
                                <p class="ys-subtitle">Manage your form submissions</p>
                            </div>
                        </div>
                        <div class="ys-version">
                            <span>v<?php echo esc_html($this->version); ?></span>
                        </div>
                    </div>
                </div>

                <?php echo $success_message; ?>

                <div class="ys-grid">
                    <?php
                    foreach ($files_info as $file_info) {
                        $file_exists = file_exists($file_info['path']);
                        $total_submissions = 0;
                        $latest_submission = 'N/A';
                        $file_size = 0;

                        if ($file_exists) {
                            try {
                                // Read XLSX file using PhpSpreadsheet to count rows
                                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
                                $spreadsheet = $reader->load($file_info['path']);
                                $worksheet = $spreadsheet->getActiveSheet();
                                
                                $highestRow = $worksheet->getHighestRow();
                                $total_submissions = $highestRow - 1; // Subtract header row

                                if ($total_submissions > 0) {
                                    // Get latest timestamp from column 2 (Timestamp column)
                                    $latest_submission = $worksheet->getCellByColumnAndRow(2, $highestRow)->getValue();
                                }
                            } catch (Exception $e) {
                                error_log('Error reading XLSX file: ' . $e->getMessage());
                            }

                            $file_size = size_format(filesize($file_info['path']));
                        }

                        // Determine card type based on file existence
                        $card_type = $file_exists ? 'info' : 'warning';
                        $icon_path = $file_exists ? 'M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM9 17H7V10H9V17ZM13 17H11V7H13V17ZM17 17H15V13H17V17Z' : 'M19 5V19H5V5H19ZM19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM7 7H9V9H7V7ZM7 11H9V13H7V11ZM7 15H9V17H7V15ZM13 7H11V9H13V7ZM13 11H11V13H13V11ZM13 15H11V17H13V15ZM17 7H15V9H17V7ZM17 11H15V13H17V11ZM17 15H15V17H17V15Z';

                        $this->render_stat_card(
                            $card_type,
                            $file_info['label'],
                            $file_exists ? number_format($total_submissions) : 'No data',
                            $icon_path,
                            $file_exists ? $file_size : '0 B',
                            $file_info['forms']
                        );
                    }
                    ?>
                </div>

                <?php
                $any_file_exists = false;
                foreach ($files_info as $file_info) {
                    if (file_exists($file_info['path'])) {
                        $any_file_exists = true;
                        break;
                    }
                }

                if ($any_file_exists): ?>

                    <?php foreach ($files_info as $file_info): ?>
                        <?php if (file_exists($file_info['path'])): ?>
                            <div class="ys-card">
                                <div class="ys-card-header">
                                    <h2 class="ys-card-title"><?php echo esc_html($file_info['label']); ?></h2>
                                    <span class="ys-badge"><?php echo esc_html($file_info['forms']); ?></span>
                                </div>
                                <div class="ys-card-body">
                                    <div class="ys-actions-grid">
                                        <form method="post" action="">
                                            <?php wp_nonce_field('download_xls', 'download_xls_nonce'); ?>
                                            <input type="hidden" name="action" value="download_xls">
                                            <input type="hidden" name="file" value="<?php echo esc_attr($file_info['name']); ?>">
                                            <button type="submit" class="ys-btn ys-btn-primary">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                                    <path d="M19 9H15V3H9V9H5L12 16L19 9ZM5 18V20H19V18H5Z" fill="currentColor" />
                                                </svg>
                                                Download <?php echo esc_html($file_info['name']); ?>
                                            </button>
                                        </form>
                                        <form method="post" action=""
                                            onsubmit="return confirm('Are you absolutely sure? This will permanently delete ALL submissions from <?php echo esc_attr($file_info['label']); ?>!');">
                                            <?php wp_nonce_field('clear_submissions', 'clear_submissions_nonce'); ?>
                                            <input type="hidden" name="action" value="clear_submissions">
                                            <input type="hidden" name="file" value="<?php echo esc_attr($file_info['name']); ?>">
                                            <button type="submit" class="ys-btn ys-btn-danger">
                                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                                    <path
                                                        d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM19 4H15.5L14.5 3H9.5L8.5 4H5V6H19V4Z"
                                                        fill="currentColor" />
                                                </svg>
                                                Clear <?php echo esc_html($file_info['name']); ?>
                                            </button>
                                        </form>
                                    </div>

                                    <div class="ys-card" style="margin-top: 20px;">
                                        <div class="ys-card-header">
                                            <h3 class="ys-card-title">Recent Submissions (<?php echo esc_html($file_info['name']); ?>)</h3>
                                            <span class="ys-badge">Last 10 entries</span>
                                        </div>
                                        <div class="ys-card-body ys-table-container">
                                            <?php $this->show_preview($file_info['path']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>

                <?php else: ?>
                    <div class="ys-empty-state">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19Z"
                                fill="#16e791" opacity="0.3" />
                            <path d="M7 10H9V17H7V10ZM11 7H13V17H11V7ZM15 13H17V17H15V13Z" fill="#16e791" />
                        </svg>
                        <h3>No Submissions Yet</h3>
                        <p>The data files will be created automatically when your first forms are submitted.</p>
                    </div>
                <?php endif; ?>

                <div class="ys-footer">
                    <p>© <?php echo date('Y'); ?> Bricks Form Data Manager</p>
                </div>
            </div>
        </div>
    <?php
        $this->render_styles();
    }

    /**
     * Render stat card
     */
    private function render_stat_card($type, $label, $value, $icon_path, $file_size = '', $forms_info = '')
    {
    ?>
        <div class="ys-stat-card">
            <div class="ys-stat-icon ys-stat-icon-<?php echo esc_attr($type); ?>">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="<?php echo esc_attr($icon_path); ?>" fill="currentColor" />
                </svg>
            </div>
            <div class="ys-stat-content">
                <div class="ys-stat-value"><?php echo $value; ?></div>
                <div class="ys-stat-label"><?php echo esc_html($label); ?></div>
                <?php if ($file_size): ?>
                    <div class="ys-stat-meta">Size: <?php echo esc_html($file_size); ?></div>
                <?php endif; ?>
                <?php if ($forms_info): ?>
                    <div class="ys-stat-forms"><?php echo esc_html($forms_info); ?></div>
                <?php endif; ?>
            </div>
        </div>
<?php
    }

    /**
     * Show data preview from XLSX file
     */
    private function show_preview($xlsx_file)
    {
        if (!file_exists($xlsx_file)) {
            echo '<div class="ys-empty-state" style="padding: 32px;"><p>No submissions to preview.</p></div>';
            return;
        }

        try {
            // Read XLSX file using PhpSpreadsheet
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($xlsx_file);
            $worksheet = $spreadsheet->getActiveSheet();
            
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            if ($highestRow <= 1) { // Only header row
                echo '<div class="ys-empty-state" style="padding: 32px;"><p>No submissions to preview.</p></div>';
                return;
            }

            // Get headers from first row
            $headers = array();
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $headers[] = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
            }

            // Get last 10 rows (excluding header)
            $start = max(2, $highestRow - 9); // Start from row 2 (after header)
            $recent_rows = array();
            
            for ($row = $start; $row <= $highestRow; $row++) {
                $row_data = array();
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $row_data[] = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                }
                $recent_rows[] = $row_data;
            }

            $recent_rows = array_reverse($recent_rows);

            echo '<table class="ys-table"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>' . esc_html($header) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($recent_rows as $row_data) {
                echo '<tr>';
                $cellIndex = 0;
                foreach ($row_data as $cellValue) {
                    // Apply special styling for consent columns
                    if ($cellIndex == 4) { // Newsletter Consent column
                        if (trim($cellValue) === 'I do not agree') {
                            echo '<td class="ys-consent-no"><strong>' . esc_html($cellValue) . '</strong></td>';
                        } else {
                            echo '<td>' . esc_html($cellValue) . '</td>';
                        }
                    } elseif ($cellIndex >= 5 && $cellIndex <= 7) { // Communication channels columns
                        $class = (trim($cellValue) === 'Yes') ? 'ys-consent-yes' : 'ys-consent-no';
                        echo '<td class="' . $class . '">' . esc_html($cellValue) . '</td>';
                    } elseif ($cellIndex == 8) { // Terms Accepted column
                        $class = (trim($cellValue) === 'Yes') ? 'ys-consent-yes' : 'ys-consent-no';
                        echo '<td class="' . $class . '">' . esc_html($cellValue) . '</td>';
                    } elseif ($cellIndex == 9) { // Token column
                        $display_cell = strlen($cellValue) > 20 ? substr($cellValue, 0, 20) . '...' : $cellValue;
                        echo '<td title="' . esc_attr($cellValue) . '"><code>' . esc_html($display_cell) . '</code></td>';
                    } else {
                        $display_cell = strlen($cellValue) > 50 ? substr($cellValue, 0, 50) . '...' : $cellValue;
                        echo '<td title="' . esc_attr($cellValue) . '">' . esc_html($display_cell) . '</td>';
                    }
                    $cellIndex++;
                }
                echo '</tr>';
            }

            echo '</tbody></table>';
        } catch (Exception $e) {
            echo '<div class="ys-empty-state" style="padding: 32px;"><p>Error reading data file.</p></div>';
            error_log('Error reading XLSX for preview: ' . $e->getMessage());
        }
    }

    /**
     * Handle downloads and actions
     */
    public function handle_download()
    {
        if (isset($_POST['action']) && $_POST['action'] == 'download_xls') {
            if (!isset($_POST['download_xls_nonce']) || !wp_verify_nonce($_POST['download_xls_nonce'], 'download_xls')) {
                wp_die('Security check failed');
            }

            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized access');
            }

            $filename = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : 'motodinamiki.xlsx';
            $file_path = $this->data_dir . '/' . $filename;

            if (file_exists($file_path)) {
                header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                header('Content-Disposition: attachment; filename="' . basename($file_path, '.xlsx') . '-' . date('Y-m-d-His') . '.xlsx"');
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($file_path);
                exit;
            } else {
                wp_die('File not found: ' . esc_html($filename));
            }
        }

        if (isset($_POST['action']) && $_POST['action'] == 'clear_submissions') {
            if (!isset($_POST['clear_submissions_nonce']) || !wp_verify_nonce($_POST['clear_submissions_nonce'], 'clear_submissions')) {
                wp_die('Security check failed');
            }

            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized access');
            }

            $filename = isset($_POST['file']) ? sanitize_text_field($_POST['file']) : 'motodinamiki.xlsx';
            $file_path = $this->data_dir . '/' . $filename;

            if (file_exists($file_path)) {
                unlink($file_path);
                wp_redirect(admin_url('admin.php?page=form-submissions&cleared=1&file=' . urlencode($filename)));
                exit;
            }
        }
    }

    /**
     * Render CSS styles
     */
    private function render_styles()
    {
        include dirname(__FILE__) . '/assets/admin-styles.php';
    }
}

new Bricks_Form_Data_Manager();
