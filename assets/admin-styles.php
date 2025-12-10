<?php
// Prevent direct access
if (!defined('ABSPATH')) exit;
?>
<style>
:root {
    --accent-color: #16e791;
    --primary-text: #ffffff;
    --secondary-text: #e0e0e0;
    --background-dark: #121212;
    --background-medium: #1e1e1e;
    --background-light: #2a2a2a;
    --border-color: #333333;
    --border-light: #444444;
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
    --info-color: #17a2b8;
    --radius: 3px;
}

/* Remove WordPress admin white background */
#wpwrap {
    background-color: var(--background-dark) !important;
}

#wpcontent {
    background-color: var(--background-dark) !important;
}

.ys-wrapper {
    font-family: 'Lato', sans-serif;
    background: var(--background-dark) !important;
    min-height: 100vh;
    margin: -20px -20px -20px -2px;
    padding: 0;
}

.ys-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px;
    background: var(--background-dark);
}

.ys-header {
    background: linear-gradient(135deg, var(--background-medium) 0%, var(--background-light) 100%);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 24px 32px;
    margin-bottom: 32px;
}

.ys-header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
}

.ys-brand {
    display: flex;
    align-items: center;
    gap: 16px;
}

.ys-logo {
    flex-shrink: 0;
}

.ys-title {
    color: var(--primary-text);
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    line-height: 1.2;
}

.ys-subtitle {
    color: var(--secondary-text);
    font-size: 14px;
    margin: 4px 0 0 0;
}

.ys-version {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--secondary-text);
    font-size: 14px;
}

.ys-divider {
    color: var(--border-color);
}

.ys-alert {
    padding: 16px 20px;
    border-radius: var(--radius);
    margin-bottom: 24px;
    border: 1px solid;
}

.ys-alert-success {
    background: rgba(40, 167, 69, 0.1);
    border-color: var(--success-color);
    color: var(--success-color);
}

.ys-alert-danger {
    background: rgba(220, 53, 69, 0.1);
    border-color: var(--danger-color);
    color: var(--danger-color);
}

.ys-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 32px;
}

.ys-stat-card {
    background: var(--background-medium);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
}

.ys-stat-card:hover {
    border-color: var(--accent-color);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(22, 231, 145, 0.1);
}

.ys-stat-icon {
    width: 56px;
    height: 56px;
    border-radius: var(--radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.ys-stat-icon-primary {
    background: rgba(22, 231, 145, 0.1);
    color: var(--accent-color);
}

.ys-stat-icon-info {
    background: rgba(23, 162, 184, 0.1);
    color: var(--info-color);
}

.ys-stat-icon-success {
    background: rgba(40, 167, 69, 0.1);
    color: var(--success-color);
}

.ys-stat-icon-warning {
    background: rgba(255, 193, 7, 0.1);
    color: var(--warning-color);
}

.ys-stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary-text);
    line-height: 1;
    margin-bottom: 6px;
}

.ys-stat-label {
    font-size: 12px;
    color: var(--secondary-text);
    font-weight: 500;
}

.ys-card {
    background: var(--background-medium);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    margin-bottom: 24px;
    overflow: hidden;
}

.ys-card-danger {
    border-color: rgba(220, 53, 69, 0.3);
}

.ys-card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
}

.ys-card-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--primary-text);
    margin: 0;
}

.ys-badge {
    background: rgba(22, 231, 145, 0.1);
    color: var(--accent-color);
    padding: 4px 12px;
    border-radius: var(--radius);
    font-size: 12px;
    font-weight: 600;
}

.ys-card-body {
    padding: 24px;
}

.ys-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.ys-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: var(--radius);
    font-size: 14px;
    font-weight: 600;
    font-family: 'Lato', sans-serif;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 100%;
}

.ys-btn:hover {
    transform: translateY(-1px);
}

.ys-btn-primary {
    background: var(--accent-color);
    color: var(--background-dark);
}

.ys-btn-primary:hover {
    background: #14d182;
    box-shadow: 0 4px 12px rgba(22, 231, 145, 0.3);
}

.ys-btn-secondary {
    background: var(--background-light);
    color: var(--primary-text);
    border: 1px solid var(--border-color);
}

.ys-btn-secondary:hover {
    border-color: var(--border-light);
}

.ys-btn-danger {
    background: rgba(220, 53, 69, 0.1);
    color: var(--danger-color);
    border: 1px solid rgba(220, 53, 69, 0.3);
}

.ys-btn-danger:hover {
    background: var(--danger-color);
    color: white;
}

.ys-table-container {
    overflow-x: auto;
    padding: 0;
}

.ys-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.ys-table th {
    background: var(--background-light);
    color: var(--primary-text);
    font-weight: 700;
    font-size: 12px;
    text-transform: uppercase;
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}

.ys-table td {
    padding: 12px 16px;
    color: var(--secondary-text);
    font-size: 12px;
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}

.ys-table tr:hover td {
    background: var(--background-light);
    color: var(--primary-text);
}

.ys-table tr:last-child td {
    border-bottom: none;
}

/* Column-specific styling for communication channels */
.ys-table th:nth-child(6),
/* E-Mail Consent */
.ys-table th:nth-child(7),
/* Phone/SMS Consent */
.ys-table th:nth-child(8)

/* Mail Consent */
    {
    width: 120px;
    text-align: center;
}

.ys-table td:nth-child(6),
/* E-Mail Consent */
.ys-table td:nth-child(7),
/* Phone/SMS Consent */
.ys-table td:nth-child(8)

/* Mail Consent */
    {
    text-align: center;
    font-weight: 600;
}

/* Consent status styling */
.ys-consent-yes {
    color: var(--success-color) !important;
    background-color: rgba(40, 167, 69, 0.1) !important;
}

.ys-consent-no {
    color: var(--warning-color) !important;
    background-color: rgba(255, 193, 7, 0.1) !important;
}

/* Terms Accepted styling */
.ys-table td:nth-child(9) {
    /* Terms Accepted column */
    text-align: center;
    font-weight: 600;
}

.ys-danger-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    flex-wrap: wrap;
}

.ys-danger-content strong {
    color: var(--danger-color);
    font-size: 16px;
    display: block;
    margin-bottom: 4px;
}

.ys-danger-content p {
    color: var(--secondary-text);
    margin: 0;
    font-size: 14px;
}

.ys-empty-state {
    text-align: center;
    padding: 64px 32px;
    background: var(--background-medium);
    border: 1px solid var(--border-color);
    border-radius: var(--radius);
    margin-bottom: 24px;
}

.ys-empty-state h3 {
    color: var(--primary-text);
    font-size: 20px;
    font-weight: 700;
    margin: 24px 0 8px 0;
}

.ys-empty-state p {
    color: var(--secondary-text);
    font-size: 14px;
    margin: 0;
}

.ys-footer {
    text-align: center;
    padding: 32px 0;
    color: var(--secondary-text);
    font-size: 13px;
    border-top: 1px solid var(--border-color);
    margin-top: 32px;
}

.ys-footer p {
    margin: 0;
}

@media (max-width: 768px) {
    .ys-container {
        padding: 16px;
    }

    .ys-header {
        padding: 20px;
    }

    .ys-header-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .ys-grid {
        grid-template-columns: 1fr;
    }

    .ys-stat-value {
        font-size: 20px;
    }

    .ys-stat-label {
        font-size: 11px;
    }

    .ys-actions-grid {
        grid-template-columns: 1fr;
    }

    .ys-danger-content {
        flex-direction: column;
        align-items: flex-start;
    }

    .ys-table th,
    .ys-table td {
        padding: 8px 12px;
        font-size: 11px;
    }

    .ys-table th:nth-child(6),
    .ys-table th:nth-child(7),
    .ys-table th:nth-child(8),
    .ys-table td:nth-child(6),
    .ys-table td:nth-child(7),
    .ys-table td:nth-child(8) {
        width: 80px;
    }
}
</style>