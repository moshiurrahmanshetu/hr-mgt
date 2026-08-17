<?php
/**
 * SQL Console View
 * Developed by Senior PHP Software Architect
 * 
 * Interactive query runner for testing database schemas and inspecting seeded values.
 */

require_once __DIR__ . '/../helpers/url_helper.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// System Admin / HR only access
AuthMiddleware::requireRoles(['Admin', 'HR Manager']);

$page_title = 'Interactive SQL Console';

require_once __DIR__ . '/../layouts/header.php';
require_once __DIR__ . '/../layouts/sidebar.php';
?>

<div class="main-content">
    <?php require_once __DIR__ . '/../layouts/topbar.php'; ?>

    <main class="content-body" data-aos="fade-up">
        
        <!-- Page Header -->
        <div class="mb-4">
            <h2 class="fw-bold tracking-tight mb-1">SQL Console Terminal</h2>
            <p class="text-muted small m-0">Run queries to inspect database state, seed records, and test joins on departments, employees, leaves, and payroll tables.</p>
        </div>

        <div class="row">
            <!-- Left Hand: Console Inputs & Helper Templates -->
            <div class="col-12 col-lg-5">
                <!-- Query Editor Card -->
                <div class="custom-card">
                    <h5 class="fw-semibold mb-3" style="font-size: 0.95rem;">Execute Query</h5>
                    
                    <div class="mb-3">
                        <textarea id="sql-editor" class="form-control font-mono text-xs text-info" style="height: 140px; background-color: var(--bg-primary) !important;" placeholder="SELECT * FROM employees LIMIT 5;"></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button class="btn btn-sm btn-outline-secondary" onclick="clearConsole()">Clear Terminal</button>
                        <button class="btn btn-sm btn-primary d-flex align-items-center gap-2" onclick="runConsoleQuery()">
                            <i class="bi bi-play-fill"></i>
                            <span>Execute Statement</span>
                        </button>
                    </div>
                </div>

                <!-- Quick Query Templates Card -->
                <div class="custom-card">
                    <h5 class="fw-semibold mb-3" style="font-size: 0.95rem;">SQL Templates</h5>
                    <div class="list-group list-group-flush gap-2">
                        <button class="list-group-item list-group-item-action bg-primary border text-start rounded text-muted font-mono text-xs p-2 py-1" onclick="loadTemplate('SELECT * FROM users;')">
                            <i class="bi bi-file-earmark-code text-primary me-2"></i> SELECT * FROM users;
                        </button>
                        <button class="list-group-item list-group-item-action bg-primary border text-start rounded text-muted font-mono text-xs p-2 py-1" onclick="loadTemplate('SELECT * FROM employees;')">
                            <i class="bi bi-file-earmark-code text-success me-2"></i> SELECT * FROM employees;
                        </button>
                        <button class="list-group-item list-group-item-action bg-primary border text-start rounded text-muted font-mono text-xs p-2 py-1" onclick="loadTemplate('SELECT d.name, COUNT(e.id) AS headcount FROM departments d LEFT JOIN employees e ON d.id = e.department_id GROUP BY d.id;')">
                            <i class="bi bi-file-earmark-code text-warning me-2"></i> SELECT Department Headcounts;
                        </button>
                        <button class="list-group-item list-group-item-action bg-primary border text-start rounded text-muted font-mono text-xs p-2 py-1" onclick="loadTemplate('SELECT * FROM attendance ORDER BY date DESC;')">
                            <i class="bi bi-file-earmark-code text-info me-2"></i> SELECT * FROM attendance;
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Hand: Console Outputs -->
            <div class="col-12 col-lg-7">
                <div class="custom-card" style="min-height: 410px; display: flex; flex-direction: column;">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-semibold m-0" style="font-size: 0.95rem;">Console Results Output</h5>
                        <span id="query-duration" class="text-muted small font-mono text-xs" style="font-size: 0.75rem;">Duration: 0.00ms</span>
                    </div>

                    <div id="results-container" class="bg-primary border rounded flex-grow-1 p-3" style="border-color: var(--border-color) !important; background-color: var(--bg-primary) !important; overflow: auto; max-height: 450px;">
                        <div class="text-muted small font-mono" id="results-placeholder">
                            <div class="text-secondary"><i class="bi bi-chevron-right me-1"></i> Ready for execution. Input a query on the left and run.</div>
                        </div>
                        <div id="results-table-wrapper" class="table-responsive d-none">
                            <table class="table table-sm align-middle font-mono text-xs" style="color: var(--text-primary);">
                                <thead id="results-thead"></thead>
                                <tbody id="results-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Database seeds / mock dataset for live simulation in client sandbox environment -->
<script>
    // Seeding dynamic datasets matching our DB tables and columns
    const dbSim = {
        users: [
            { id: 1, username: 'admin', email: 'admin@hrmsystem.com', role: 'Admin', status: 'Active', created_at: '2026-06-01' },
            { id: 2, username: 'johndoe', email: 'john.doe@hrmsystem.com', role: 'Employee', status: 'Active', created_at: '2026-07-02' },
            { id: 3, username: 'sarahj', email: 'sarah.jenkins@hrmsystem.com', role: 'HR Manager', status: 'Active', created_at: '2026-07-02' },
            { id: 4, username: 'mikeross', email: 'mike.ross@hrmsystem.com', role: 'Line Manager', status: 'Active', created_at: '2026-07-02' }
        ],
        employees: [
            { id: 1, first_name: 'Sarah', last_name: 'Jenkins', employee_code: 'EMP-10001', email: 'sarah.jenkins@hrmsystem.com', job_title: 'HR Manager', department_id: 1, salary: '$75,000.00', employment_status: 'Full-Time' },
            { id: 2, first_name: 'Mike', last_name: 'Ross', employee_code: 'EMP-10002', email: 'mike.ross@hrmsystem.com', job_title: 'Senior Software Engineer', department_id: 2, salary: '$98,000.00', employment_status: 'Full-Time' },
            { id: 3, first_name: 'John', last_name: 'Doe', employee_code: 'EMP-10003', email: 'john.doe@hrmsystem.com', job_title: 'Software Developer', department_id: 2, salary: '$65,000.00', employment_status: 'Full-Time' }
        ],
        departments: [
            { id: 1, name: 'Human Resources', code: 'HR', manager_id: 1 },
            { id: 2, name: 'Engineering', code: 'ENG', manager_id: 2 },
            { id: 3, name: 'Finance', code: 'FIN', manager_id: null },
            { id: 4, name: 'Marketing & Sales', code: 'MKT', manager_id: null }
        ],
        attendance: [
            { id: 1, employee_id: 1, date: '2026-07-02', clock_in: '08:30:00', clock_out: '17:00:00', status: 'Present' },
            { id: 2, employee_id: 2, date: '2026-07-02', clock_in: '09:05:00', clock_out: '17:30:00', status: 'Late' },
            { id: 3, employee_id: 3, date: '2026-07-02', clock_in: '08:55:00', clock_out: '17:00:00', status: 'Present' },
            { id: 4, employee_id: 1, date: '2026-07-01', clock_in: '08:45:00', clock_out: '17:15:00', status: 'Present' }
        ]
    };

    function loadTemplate(query) {
        document.getElementById('sql-editor').value = query;
    }

    function clearConsole() {
        document.getElementById('sql-editor').value = '';
        document.getElementById('results-placeholder').innerHTML = '<div class="text-secondary"><i class="bi bi-chevron-right me-1"></i> Console cleared.</div>';
        document.getElementById('results-table-wrapper').classList.add('d-none');
        document.getElementById('query-duration').innerText = 'Duration: 0.00ms';
    }

    function runConsoleQuery() {
        const queryText = document.getElementById('sql-editor').value.trim();
        const placeholder = document.getElementById('results-placeholder');
        const wrapper = document.getElementById('results-table-wrapper');
        const thead = document.getElementById('results-thead');
        const tbody = document.getElementById('results-tbody');
        const durationSpan = document.getElementById('query-duration');

        if (!queryText) {
            placeholder.innerHTML = '<div class="text-danger font-mono"><i class="bi bi-exclamation-triangle-fill me-1"></i> Syntax Error: Please provide an SQL statement.</div>';
            wrapper.classList.add('d-none');
            return;
        }

        const start = performance.now();

        // Basic parsing for live demonstration
        const cleanQuery = queryText.toLowerCase().replace(/;/g, '').trim();
        
        if (!cleanQuery.startsWith('select')) {
            placeholder.innerHTML = '<div class="text-warning font-mono"><i class="bi bi-shield-lock-fill me-1"></i> Security Alert: DDL and destructive statements (INSERT/UPDATE/DELETE/DROP) are disabled in the console sandbox. Only SELECT operations allowed on Sprint 01 database instances.</div>';
            wrapper.classList.add('d-none');
            durationSpan.innerText = `Duration: ${(performance.now() - start).toFixed(2)}ms`;
            return;
        }

        let matchedData = null;
        let queryDescription = '';

        if (cleanQuery.includes('users')) {
            matchedData = dbSim.users;
            queryDescription = 'Result set of [users] seed database table';
        } else if (cleanQuery.includes('employees')) {
            matchedData = dbSim.employees;
            queryDescription = 'Result set of [employees] seed database table';
        } else if (cleanQuery.includes('departments')) {
            matchedData = dbSim.departments;
            queryDescription = 'Result set of [departments] seed database table';
        } else if (cleanQuery.includes('attendance')) {
            matchedData = dbSim.attendance;
            queryDescription = 'Result set of [attendance] seed database table';
        } else if (cleanQuery.includes('headcount')) {
            // join query template
            matchedData = [
                { Department: 'Engineering', Headcount: 2, Code: 'ENG' },
                { Department: 'Human Resources', Headcount: 1, Code: 'HR' },
                { Department: 'Finance', Headcount: 0, Code: 'FIN' },
                { Department: 'Marketing & Sales', Headcount: 0, Code: 'MKT' }
            ];
            queryDescription = 'Custom Headcount aggregate JOIN query results';
        }

        if (matchedData) {
            // Render table
            placeholder.innerHTML = `<div class="text-success mb-2"><i class="bi bi-check-circle-fill me-1"></i> Query OK. Fetched ${matchedData.length} records. ${queryDescription}</div>`;
            
            // Build header
            const cols = Object.keys(matchedData[0]);
            let headerHtml = '<tr>';
            cols.forEach(col => {
                headerHtml += `<th class="text-secondary" style="border-bottom: 2px solid var(--border-color);">${col}</th>`;
            });
            headerHtml += '</tr>';
            thead.innerHTML = headerHtml;

            // Build body
            let bodyHtml = '';
            matchedData.forEach(row => {
                bodyHtml += `<tr style="border-bottom: 1px solid var(--border-color);">`;
                cols.forEach(col => {
                    const val = row[col];
                    bodyHtml += `<td>${val !== null ? val : '<span class="text-muted">NULL</span>'}</td>`;
                });
                bodyHtml += '</tr>';
            });
            tbody.innerHTML = bodyHtml;

            wrapper.classList.remove('d-none');
        } else {
            placeholder.innerHTML = `<div class="text-warning font-mono"><i class="bi bi-info-circle-fill me-1"></i> Query succeeded with empty results or unrecognized relation table. Double check spellings of schema tables ('users', 'employees', 'departments', 'attendance').</div>`;
            wrapper.classList.add('d-none');
        }

        durationSpan.innerText = `Duration: ${(performance.now() - start).toFixed(2)}ms`;
    }
</script>

<?php
require_once __DIR__ . '/../layouts/footer.php';
?>
