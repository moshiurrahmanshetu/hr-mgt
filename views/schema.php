<?php
/**
 * Database Schema Visualizer View
 * Developed by Senior PHP Software Architect
 * 
 * Renders the full relational DB architecture, documenting columns, keys,
 * indexes, and engines, along with an integrated code viewer for schema.sql.
 */

require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Enforce System Admin or HR Manager only access
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

$page_title = 'Database Structure';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';

// Load SQL code safely
$sql_file_path = __DIR__ . '/../database/schema.sql';
$sql_content = file_exists($sql_file_path) ? file_get_contents($sql_file_path) : '-- schema.sql not found';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../layouts/topbar.php'; ?>

    <main class="content-body" data-aos="fade-up">
        
        <!-- Page Header -->
        <div class="mb-4">
            <h2 class="fw-bold tracking-tight mb-1">Database Architecture</h2>
            <p class="text-muted small m-0">Inspect the relational MySQL blueprint, table creation order, primary/foreign key connections, and performance indexes.</p>
        </div>

        <div class="row">
            <!-- Table Inspector list (Interactive Accordion) -->
            <div class="col-12 col-xl-6">
                <div class="custom-card" style="min-height: 520px;">
                    <h5 class="fw-semibold mb-3" style="font-size: 0.95rem;">Relational Table Definitions</h5>
                    
                    <div class="accordion" id="schemaAccordion" style="--bs-accordion-bg: var(--bg-primary); --bs-accordion-border-color: var(--border-color); --bs-accordion-btn-color: var(--text-primary); --bs-accordion-active-bg: var(--bg-tertiary); --bs-accordion-active-color: var(--accent-primary);">
                        
                        <!-- Users Table -->
                        <div class="accordion-item border rounded mb-2 overflow-hidden" style="border-color: var(--border-color) !important;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#colUsers">
                                    <i class="bi bi-people-fill me-2 text-primary"></i> users
                                    <span class="custom-badge badge-primary ms-2">Auth & Roles</span>
                                </button>
                            </h2>
                            <div id="colUsers" class="accordion-collapse collapse" data-bs-parent="#schemaAccordion">
                                <div class="accordion-body p-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm text-start font-mono text-xs text-muted">
                                            <thead>
                                                <tr class="text-secondary">
                                                    <th>Column</th>
                                                    <th>Type</th>
                                                    <th>Constraints</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td class="text-primary fw-medium">id</td><td>INT UNSIGNED</td><td>PK, AUTO_INC</td></tr>
                                                <tr><td class="text-primary fw-medium">username</td><td>VARCHAR(50)</td><td>UNIQUE, NOT NULL</td></tr>
                                                <tr><td class="text-primary fw-medium">email</td><td>VARCHAR(100)</td><td>UNIQUE, NOT NULL</td></tr>
                                                <tr><td class="text-primary fw-medium">password_hash</td><td>VARCHAR(255)</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-primary fw-medium">role</td><td>ENUM</td><td>('Admin', 'HR Manager', 'Line Manager', 'Employee')</td></tr>
                                                <tr><td class="text-primary fw-medium">status</td><td>ENUM</td><td>('Active', 'Suspended', 'Pending')</td></tr>
                                                <tr><td class="text-primary fw-medium">remember_token</td><td>VARCHAR(100)</td><td>NULL</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="small mt-2">
                                        <strong class="text-secondary">Performance Indexes:</strong>
                                        <code class="d-block bg-primary p-1 rounded mt-1">idx_users_role (role)</code>
                                        <code class="d-block bg-primary p-1 rounded mt-1">idx_users_status (status)</code>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Departments Table -->
                        <div class="accordion-item border rounded mb-2 overflow-hidden" style="border-color: var(--border-color) !important;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold text-warning" type="button" data-bs-toggle="collapse" data-bs-target="#colDepts">
                                    <i class="bi bi-building me-2 text-warning"></i> departments
                                    <span class="custom-badge badge-warning text-dark ms-2">Structure</span>
                                </button>
                            </h2>
                            <div id="colDepts" class="accordion-collapse collapse" data-bs-parent="#schemaAccordion">
                                <div class="accordion-body p-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm font-mono text-xs text-muted">
                                            <thead>
                                                <tr class="text-secondary">
                                                    <th>Column</th>
                                                    <th>Type</th>
                                                    <th>Constraints</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td class="text-warning fw-medium">id</td><td>INT UNSIGNED</td><td>PK, AUTO_INC</td></tr>
                                                <tr><td class="text-warning fw-medium">name</td><td>VARCHAR(100)</td><td>UNIQUE, NOT NULL</td></tr>
                                                <tr><td class="text-warning fw-medium">code</td><td>VARCHAR(20)</td><td>UNIQUE, NOT NULL</td></tr>
                                                <tr><td class="text-warning fw-medium">description</td><td>TEXT</td><td>NULL</td></tr>
                                                <tr><td class="text-warning fw-medium">manager_id</td><td>INT UNSIGNED</td><td>FK (employees.id), NULL</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Employees Table -->
                        <div class="accordion-item border rounded mb-2 overflow-hidden" style="border-color: var(--border-color) !important;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold text-success" type="button" data-bs-toggle="collapse" data-bs-target="#colEmps">
                                    <i class="bi bi-person-badge-fill me-2 text-success"></i> employees
                                    <span class="custom-badge badge-success ms-2">Profiles</span>
                                </button>
                            </h2>
                            <div id="colEmps" class="accordion-collapse collapse" data-bs-parent="#schemaAccordion">
                                <div class="accordion-body p-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm font-mono text-xs text-muted">
                                            <thead>
                                                <tr class="text-secondary">
                                                    <th>Column</th>
                                                    <th>Type</th>
                                                    <th>Constraints</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td class="text-success fw-medium">id</td><td>INT UNSIGNED</td><td>PK, AUTO_INC</td></tr>
                                                <tr><td class="text-success fw-medium">user_id</td><td>INT UNSIGNED</td><td>FK (users.id), UNIQUE, NULL</td></tr>
                                                <tr><td class="text-success fw-medium">department_id</td><td>INT UNSIGNED</td><td>FK (departments.id), NULL</td></tr>
                                                <tr><td class="text-success fw-medium">employee_code</td><td>VARCHAR(20)</td><td>UNIQUE, NOT NULL</td></tr>
                                                <tr><td class="text-success fw-medium">first_name</td><td>VARCHAR(50)</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-success fw-medium">last_name</td><td>VARCHAR(50)</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-success fw-medium">email</td><td>VARCHAR(100)</td><td>UNIQUE, NOT NULL</td></tr>
                                                <tr><td class="text-success fw-medium">salary</td><td>DECIMAL(12,2)</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-success fw-medium">employment_status</td><td>ENUM</td><td>('Full-Time', 'Part-Time', 'Contract', etc.)</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Attendance Table -->
                        <div class="accordion-item border rounded mb-2 overflow-hidden" style="border-color: var(--border-color) !important;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold text-info" type="button" data-bs-toggle="collapse" data-bs-target="#colAttn">
                                    <i class="bi bi-clock-history me-2 text-info"></i> attendance
                                    <span class="custom-badge badge-primary ms-2">Logs</span>
                                </button>
                            </h2>
                            <div id="colAttn" class="accordion-collapse collapse" data-bs-parent="#schemaAccordion">
                                <div class="accordion-body p-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm font-mono text-xs text-muted">
                                            <thead>
                                                <tr class="text-secondary">
                                                    <th>Column</th>
                                                    <th>Type</th>
                                                    <th>Constraints</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td class="text-info fw-medium">id</td><td>BIGINT UNSIGNED</td><td>PK, AUTO_INC</td></tr>
                                                <tr><td class="text-info fw-medium">employee_id</td><td>INT UNSIGNED</td><td>FK (employees.id), CASCADE</td></tr>
                                                <tr><td class="text-info fw-medium">date</td><td>DATE</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-info fw-medium">clock_in</td><td>TIME</td><td>NULL</td></tr>
                                                <tr><td class="text-info fw-medium">clock_out</td><td>TIME</td><td>NULL</td></tr>
                                                <tr><td class="text-info fw-medium">status</td><td>ENUM</td><td>('Present', 'Late', 'Half Day', 'Absent', etc.)</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leave Requests Table -->
                        <div class="accordion-item border rounded mb-2 overflow-hidden" style="border-color: var(--border-color) !important;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold text-danger" type="button" data-bs-toggle="collapse" data-bs-target="#colLeaves">
                                    <i class="bi bi-calendar2-range me-2 text-danger"></i> leave_requests
                                    <span class="custom-badge badge-danger ms-2">Workflows</span>
                                </button>
                            </h2>
                            <div id="colLeaves" class="accordion-collapse collapse" data-bs-parent="#schemaAccordion">
                                <div class="accordion-body p-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm font-mono text-xs text-muted">
                                            <thead>
                                                <tr class="text-secondary">
                                                    <th>Column</th>
                                                    <th>Type</th>
                                                    <th>Constraints</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td class="text-danger fw-medium">id</td><td>INT UNSIGNED</td><td>PK, AUTO_INC</td></tr>
                                                <tr><td class="text-danger fw-medium">employee_id</td><td>INT UNSIGNED</td><td>FK (employees.id), CASCADE</td></tr>
                                                <tr><td class="text-danger fw-medium">leave_type</td><td>ENUM</td><td>('Annual', 'Sick', 'Maternity', etc.)</td></tr>
                                                <tr><td class="text-danger fw-medium">start_date</td><td>DATE</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-danger fw-medium">end_date</td><td>DATE</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-danger fw-medium">status</td><td>ENUM</td><td>('Pending', 'Approved', 'Rejected')</td></tr>
                                                <tr><td class="text-danger fw-medium">approved_by</td><td>INT UNSIGNED</td><td>FK (users.id)</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payroll Table -->
                        <div class="accordion-item border rounded mb-2 overflow-hidden" style="border-color: var(--border-color) !important;">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed fw-semibold text-success" type="button" data-bs-toggle="collapse" data-bs-target="#colPayroll">
                                    <i class="bi bi-wallet2 me-2 text-success"></i> payroll
                                    <span class="custom-badge badge-success ms-2">Ledger</span>
                                </button>
                            </h2>
                            <div id="colPayroll" class="accordion-collapse collapse" data-bs-parent="#schemaAccordion">
                                <div class="accordion-body p-3">
                                    <div class="table-responsive">
                                        <table class="table table-sm font-mono text-xs text-muted">
                                            <thead>
                                                <tr class="text-secondary">
                                                    <th>Column</th>
                                                    <th>Type</th>
                                                    <th>Constraints</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr><td class="text-success fw-medium">id</td><td>INT UNSIGNED</td><td>PK, AUTO_INC</td></tr>
                                                <tr><td class="text-success fw-medium">employee_id</td><td>INT UNSIGNED</td><td>FK (employees.id), CASCADE</td></tr>
                                                <tr><td class="text-success fw-medium">basic_salary</td><td>DECIMAL(12,2)</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-success fw-medium">allowances</td><td>DECIMAL(12,2)</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-success fw-medium">deductions</td><td>DECIMAL(12,2)</td><td>NOT NULL</td></tr>
                                                <tr><td class="text-success fw-medium">net_salary</td><td>DECIMAL(12,2)</td><td>GENERATED STORED (basic+allow-deduct)</td></tr>
                                                <tr><td class="text-success fw-medium">status</td><td>ENUM</td><td>('Draft', 'Approved', 'Paid', 'On Hold')</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- SQL Schema Code Viewer -->
            <div class="col-12 col-xl-6">
                <div class="custom-card" style="min-height: 520px; display: flex; flex-direction: column;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-semibold m-0" style="font-size: 0.95rem;">SQL Blueprint Code (<span class="font-mono" style="font-size: 0.8rem;">database/schema.sql</span>)</h5>
                        <button class="btn btn-sm btn-outline-primary py-1 d-flex align-items-center gap-1" id="copy-sql-btn" onclick="copySqlCode()">
                            <i class="bi bi-clipboard"></i>
                            <span>Copy SQL</span>
                        </button>
                    </div>

                    <pre class="bg-primary p-3 rounded border font-mono text-xs text-muted mb-0 flex-grow-1" style="overflow: auto; max-height: 400px; border-color: var(--border-color) !important; background-color: var(--bg-primary) !important;"><code id="sql-code-block"><?php echo sanitize($sql_content); ?></code></pre>
                </div>
            </div>
        </div>

    </main>
</div>

<script>
    function copySqlCode() {
        const text = document.getElementById('sql-code-block').innerText;
        navigator.clipboard.writeText(text).then(() => {
            const btn = document.getElementById('copy-sql-btn');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg text-success"></i> <span class="text-success">Copied!</span>';
            btn.classList.add('border-success');
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.classList.remove('border-success');
            }, 2000);
        });
    }
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
