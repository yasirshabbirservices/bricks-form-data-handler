<?php

/**
 * Plugin Name: Bricks Form Data Manager
 * Plugin URI: https://yasirshabbir.com
 * Description: Professional form submission handler for Bricks Builder - Saves data to Excel files
 * Version: 2.7.1
 * Author: Yasir Shabbir
 * Author URI: https://yasirshabbir.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) exit;

class Bricks_Form_Data_Manager
{
    private $data_dir;
    private $xml_file;
    private $version = '2.7.1';

    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->data_dir = $upload_dir['basedir'] . '/form-data';
        $this->xml_file = $this->data_dir . '/submissions.xml';

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
        $htaccess_content = "# Protect form submission files\nOrder Deny,Allow\nDeny from all\n<Files *.xml>\nAllow from all\n</Files>\n<Files *.csv>\nAllow from all\n</Files>";
        file_put_contents($this->data_dir . '/.htaccess', $htaccess_content);
        file_put_contents($this->data_dir . '/index.php', '<?php // Silence is golden');
    }

    /**
     * Handle Bricks form submission
     */
    public function handle_form_submission($form)
    {
        $fields = $_POST;

        // Debug: Log all form fields
        error_log('Form submission received. Fields: ' . print_r($fields, true));

        // Regular form submission - CAPTURE ENTRY ID
        $data = array(
            'entry_id' => isset($fields['form-field-ehvmdc']) ? sanitize_text_field($fields['form-field-ehvmdc']) : '',
            'timestamp' => current_time('Y-m-d H:i:s'),
            'email' => isset($fields['form-field-2ba381']) ? sanitize_email($fields['form-field-2ba381']) : '',
            'phone' => isset($fields['form-field-vsjpsv']) ? sanitize_text_field($fields['form-field-vsjpsv']) : '',
            'newsletter_consent' => '',
            'email_consent' => 'No',
            'phone_sms_consent' => 'No',
            'mail_consent' => 'No',
            'terms_accepted' => 'No'
        );

        error_log('Processing submission for email: ' . $data['email'] . ' with entry ID: ' . $data['entry_id']);

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

        $this->save_to_xml($data);
    }

    /**
     * Save data to XML file with duplicate prevention based on email
     */
    private function save_to_xml($data)
    {
        $file_exists = file_exists($this->xml_file);
        $existing_data = array();
        $headers = array();

        // Read existing data if file exists
        if ($file_exists && filesize($this->xml_file) > 0) {
            try {
                $xml = simplexml_load_file($this->xml_file);
                if ($xml && isset($xml->Worksheet->Table->Row)) {
                    $rows = $xml->Worksheet->Table->Row;

                    // Get headers from first row
                    $rowIndex = 0;
                    foreach ($rows as $row) {
                        if ($rowIndex == 0) {
                            // Header row
                            foreach ($row->Cell as $cell) {
                                $headers[] = (string)$cell->Data;
                            }
                        } else {
                            // Data rows
                            $row_data = array();
                            foreach ($row->Cell as $cell) {
                                $row_data[] = (string)$cell->Data;
                            }
                            $existing_data[] = $row_data;
                        }
                        $rowIndex++;
                    }
                }
            } catch (Exception $e) {
                error_log('Error reading XML file: ' . $e->getMessage());
                $existing_data = array();
            }
        } else {
            // Default headers if file doesn't exist
            $headers = array('Entry ID', 'Timestamp', 'Email', 'Phone', 'Newsletter Consent', 'E-Mail Consent', 'Phone/SMS Consent', 'Mail Consent', 'Terms Accepted');
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
                    $existing_data[$index] = array(
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
                    $updated = true;
                    error_log('Updated existing entry for email: ' . $submission_email . ' with new entry ID: ' . $data['entry_id']);
                    break;
                }
            }
        }

        // If not updated, add as new entry
        if (!$updated) {
            $existing_data[] = array(
                $data['entry_id'],
                $data['timestamp'],
                $data['email'],
                $data['phone'],
                $data['newsletter_consent'],
                $data['email_consent'],
                $data['phone_sms_consent'],
                $data['mail_consent'],
                $data['terms_accepted']
            );
            error_log('Added NEW entry for email: ' . $submission_email . ' with entry ID: ' . $data['entry_id']);
        }

        // Write all data back to XML
        $this->write_xml_file($headers, $existing_data);

        return true;
    }

    /**
     * Write data to XML file
     */
    private function write_xml_file($headers, $data)
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Create processing instruction for Excel
        $pi = $xml->createProcessingInstruction('mso-application', 'progid="Excel.Sheet"');
        $xml->appendChild($pi);

        // Create Workbook
        $workbook = $xml->createElement('Workbook');
        $workbook->setAttribute('xmlns', 'urn:schemas-microsoft-com:office:spreadsheet');
        $workbook->setAttribute('xmlns:ss', 'urn:schemas-microsoft-com:office:spreadsheet');

        // Create Worksheet
        $worksheet = $xml->createElement('Worksheet');
        $worksheet->setAttribute('ss:Name', 'Form Submissions');

        // Create Table
        $table = $xml->createElement('Table');

        // Add header row
        $headerRow = $xml->createElement('Row');
        $headerRow->setAttribute('ss:Index', '1');
        foreach ($headers as $header) {
            $cell = $xml->createElement('Cell');
            $dataElement = $xml->createElement('Data');
            $dataElement->setAttribute('ss:Type', 'String');

            // Make header bold
            $bold = $xml->createElement('ss:Bold');
            $bold->setAttribute('xmlns:ss', 'urn:schemas-microsoft-com:office:spreadsheet');
            $dataElement->appendChild($bold);

            $dataText = $xml->createTextNode($header);
            $dataElement->appendChild($dataText);
            $cell->appendChild($dataElement);
            $headerRow->appendChild($cell);
        }
        $table->appendChild($headerRow);

        // Add data rows
        $rowIndex = 2;
        foreach ($data as $row) {
            $dataRow = $xml->createElement('Row');
            $dataRow->setAttribute('ss:Index', $rowIndex);

            foreach ($row as $cellValue) {
                $cell = $xml->createElement('Cell');
                $dataElement = $xml->createElement('Data');
                $dataElement->setAttribute('ss:Type', 'String');

                $cellText = $xml->createTextNode(htmlspecialchars($cellValue, ENT_XML1, 'UTF-8'));
                $dataElement->appendChild($cellText);
                $cell->appendChild($dataElement);
                $dataRow->appendChild($cell);
            }

            $table->appendChild($dataRow);
            $rowIndex++;
        }

        $worksheet->appendChild($table);
        $workbook->appendChild($worksheet);
        $xml->appendChild($workbook);

        // Save to file
        $xml->save($this->xml_file);
        chmod($this->xml_file, 0644);

        error_log('XML file saved successfully with ' . count($data) . ' entries');
        return true;
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
            'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyNCAyNCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cGF0aCBkPSJNMTQgMkg2QzQuOSAyIDQgMi45IDQgNFYyMEM0IDIxLjEgNC44OSAyMiA1Ljk5IDIySDE4QzE5LjEgMjIgMjAgMjEuMSAyMCAyMFY4TDE0IDJaTTE2IDE4SDhWMTZIOFYxOEgxNlpNMTYgMTRIOFYxMkg4VjE0SDE2Wk0xMyA5VjMuNUwxOC41IDlaIiBmaWxsPSIjMTZlNzkxIi8+PC9zdmc+',
            30
        );
    }

    /**
     * Admin page
     */
    public function admin_page()
    {
        $file_exists = file_exists($this->xml_file);
        $total_submissions = 0;
        $latest_submission = 'N/A';
        $file_size = 0;

        if ($file_exists) {
            try {
                $xml = simplexml_load_file($this->xml_file);
                if ($xml && isset($xml->Worksheet->Table->Row)) {
                    $rows = $xml->Worksheet->Table->Row;
                    $total_submissions = count($rows) - 1; // Subtract header row

                    if ($total_submissions > 0) {
                        // Get last row (excluding header)
                        $lastRow = $rows[count($rows) - 1];
                        if ($lastRow->Cell[1]->Data) {
                            $latest_submission = (string)$lastRow->Cell[1]->Data;
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Error reading XML file: ' . $e->getMessage());
            }

            $file_size = size_format(filesize($this->xml_file));
        }

        $success_message = '';
        if (isset($_GET['cleared']) && $_GET['cleared'] == '1') {
            $success_message = '<div class="ys-alert ys-alert-success">All submissions cleared successfully.</div>';
        }

        $this->render_admin_page(
            $file_exists,
            $total_submissions,
            $latest_submission,
            $file_size,
            $success_message
        );
    }

    /**
     * Render admin page
     */
    private function render_admin_page(
        $file_exists,
        $total_submissions,
        $latest_submission,
        $file_size,
        $success_message
    ) {
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
            <?php $this->render_stat_card('primary', 'Total Submissions', number_format($total_submissions), 'M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM9 17H7V10H9V17ZM13 17H11V7H13V17ZM17 17H15V13H17V17Z'); ?>
            <?php $this->render_stat_card('info', 'Latest Submission', esc_html($latest_submission), 'M11.99 2C6.47 2 2 6.48 2 12C2 17.52 6.47 22 11.99 22C17.52 22 22 17.52 22 12C22 6.48 17.52 2 11.99 2ZM12 20C7.58 20 4 16.42 4 12C4 7.58 7.58 4 12 4C16.42 4 20 7.58 20 12C20 16.42 16.42 20 12 20ZM12.5 7H11V13L16.25 16.15L17 14.92L12.5 12.25V7Z'); ?>
            <?php $this->render_stat_card($file_exists ? 'success' : 'warning', 'File Size', $file_exists ? $file_size : '0 B', 'M13 2.05V5.08C16.39 5.57 19 8.47 19 12C19 12.9 18.82 13.75 18.5 14.54L20.61 16.65C21.49 15.29 22 13.7 22 12C22 7.03 18.27 2.92 13 2.05ZM12 19C8.13 19 5 15.87 5 12C5 8.47 7.61 5.57 11 5.08V2.05C5.73 2.92 2 7.03 2 12C2 17.52 6.48 22 12 22C15.3 22 18.23 20.39 20.05 17.91L17.95 15.81C16.58 17.88 14.43 19 12 19Z'); ?>
        </div>

        <?php if ($file_exists): ?>
        <div class="ys-card">
            <div class="ys-card-header">
                <h2 class="ys-card-title">Quick Actions</h2>
            </div>
            <div class="ys-card-body">
                <div class="ys-actions-grid">
                    <form method="post" action="">
                        <?php wp_nonce_field('download_xml', 'download_xml_nonce'); ?>
                        <input type="hidden" name="action" value="download_xml">
                        <button type="submit" class="ys-btn ys-btn-primary">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path d="M19 9H15V3H9V9H5L12 16L19 9ZM5 18V20H19V18H5Z" fill="currentColor" />
                            </svg>
                            Download XML File (.xml)
                        </button>
                    </form>
                    <button class="ys-btn ys-btn-secondary" onclick="window.location.reload()">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M17.65 6.35C16.2 4.9 14.21 4 12 4C7.58 4 4.01 7.58 4.01 12C4.01 16.42 7.58 20 12 20C15.73 20 18.84 17.45 19.73 14H17.65C16.83 16.33 14.61 18 12 18C8.69 18 6 15.31 6 12C6 8.69 8.69 6 12 6C13.66 6 15.14 6.69 16.22 7.78L13 11H20V4L17.65 6.35Z"
                                fill="currentColor" />
                        </svg>
                        Refresh Data
                    </button>
                </div>
            </div>
        </div>

        <div class="ys-card">
            <div class="ys-card-header">
                <h2 class="ys-card-title">Recent Submissions</h2>
                <span class="ys-badge">Last 10 entries</span>
            </div>
            <div class="ys-card-body ys-table-container">
                <?php $this->show_preview(); ?>
            </div>
        </div>

        <div class="ys-card ys-card-danger">
            <div class="ys-card-header">
                <h2 class="ys-card-title">Danger Zone</h2>
            </div>
            <div class="ys-card-body">
                <div class="ys-danger-content">
                    <div>
                        <strong>Clear All Submissions</strong>
                        <p>Permanently delete all form submissions. This action cannot be undone.</p>
                    </div>
                    <form method="post" action=""
                        onsubmit="return confirm('Are you absolutely sure? This will permanently delete ALL submissions!');">
                        <?php wp_nonce_field('clear_submissions', 'clear_submissions_nonce'); ?>
                        <input type="hidden" name="action" value="clear_submissions">
                        <button type="submit" class="ys-btn ys-btn-danger">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                                <path
                                    d="M6 19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7H6V19ZM19 4H15.5L14.5 3H9.5L8.5 4H5V6H19V4Z"
                                    fill="currentColor" />
                            </svg>
                            Clear All Data
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="ys-empty-state">
            <svg width="64" height="64" viewBox="0 0 24 24" fill="none">
                <path
                    d="M19 3H5C3.9 3 3 3.9 3 5V19C3 20.1 3.9 21 5 21H19C20.1 21 21 20.1 21 19V5C21 3.9 20.1 3 19 3ZM19 19H5V5H19V19Z"
                    fill="#16e791" opacity="0.3" />
                <path d="M7 10H9V17H7V10ZM11 7H13V17H11V7ZM15 13H17V17H15V13Z" fill="#16e791" />
            </svg>
            <h3>No Submissions Yet</h3>
            <p>The data file will be created automatically when your first form is submitted.</p>
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
    private function render_stat_card($type, $label, $value, $icon_path)
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
    </div>
</div>
<?php
    }

    /**
     * Show data preview from XML
     */
    private function show_preview()
    {
        if (!file_exists($this->xml_file)) {
            echo '<div class="ys-empty-state" style="padding: 32px;"><p>No submissions to preview.</p></div>';
            return;
        }

        try {
            $xml = simplexml_load_file($this->xml_file);
            if (!$xml || !isset($xml->Worksheet->Table->Row)) {
                echo '<div class="ys-empty-state" style="padding: 32px;"><p>No submissions to preview.</p></div>';
                return;
            }

            $rows = $xml->Worksheet->Table->Row;
            $total_rows = count($rows);

            if ($total_rows <= 1) { // Only header row
                echo '<div class="ys-empty-state" style="padding: 32px;"><p>No submissions to preview.</p></div>';
                return;
            }

            // Get last 10 rows (excluding header)
            $start = max(1, $total_rows - 10); // Start from row 1 (after header)
            $recent_rows = array();

            for ($i = $start; $i < $total_rows; $i++) {
                $recent_rows[] = $rows[$i];
            }

            $recent_rows = array_reverse($recent_rows);

            // Get headers from first row
            $headers = array();
            foreach ($rows[0]->Cell as $cell) {
                $headers[] = (string)$cell->Data;
            }

            echo '<table class="ys-table"><thead><tr>';
            foreach ($headers as $header) {
                echo '<th>' . esc_html($header) . '</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($recent_rows as $row) {
                echo '<tr>';
                $cellIndex = 0;
                foreach ($row->Cell as $cell) {
                    $cellValue = (string)$cell->Data;

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
            error_log('Error reading XML for preview: ' . $e->getMessage());
        }
    }

    /**
     * Handle downloads and actions
     */
    public function handle_download()
    {
        if (isset($_POST['action']) && $_POST['action'] == 'download_xml') {
            if (!isset($_POST['download_xml_nonce']) || !wp_verify_nonce($_POST['download_xml_nonce'], 'download_xml')) {
                wp_die('Security check failed');
            }

            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized access');
            }

            if (file_exists($this->xml_file)) {
                header('Content-Type: application/xml');
                header('Content-Disposition: attachment; filename="form-submissions-' . date('Y-m-d-His') . '.xml"');
                header('Pragma: no-cache');
                header('Expires: 0');
                readfile($this->xml_file);
                exit;
            }
        }

        if (isset($_POST['action']) && $_POST['action'] == 'clear_submissions') {
            if (!isset($_POST['clear_submissions_nonce']) || !wp_verify_nonce($_POST['clear_submissions_nonce'], 'clear_submissions')) {
                wp_die('Security check failed');
            }

            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized access');
            }

            if (file_exists($this->xml_file)) {
                unlink($this->xml_file);
                wp_redirect(admin_url('admin.php?page=form-submissions&cleared=1'));
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