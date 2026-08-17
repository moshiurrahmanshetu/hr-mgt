<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? sanitize($page_title) . ' | HRM Portal' : 'Human Resource Management System'; ?></title>
    
    <!-- 1. Google Web Fonts (Inter and JetBrains Mono) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- 2. Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- 3. Bootstrap Icons (SVG based font) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- 4. AOS (Animate on Scroll) CSS -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
    
    <!-- 5. Primary CSS Styles & Theme Variables (Engineered for Dark/Light Contrast) -->
    <style>
        :root, [data-theme="dark"] {
            --bg-primary: #0b0f19;
            --bg-secondary: #121824;
            --bg-tertiary: #1b2438;
            --border-color: #212d45;
            --text-primary: #f3f4f6;
            --text-secondary: #9ca3af;
            --text-muted: #6b7280;
            --accent-primary: #3b82f6; /* Blue */
            --accent-primary-hover: #2563eb;
            --accent-success: #10b981; /* Green */
            --accent-warning: #f59e0b; /* Yellow */
            --accent-danger: #ef4444; /* Red */
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5), 0 2px 4px -1px rgba(0, 0, 0, 0.5);
        }

        [data-theme="light"] {
            --bg-primary: #f9fafb;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f3f4f6;
            --border-color: #e5e7eb;
            --text-primary: #111827;
            --text-secondary: #4b5563;
            --text-muted: #9ca3af;
            --accent-primary: #2563eb;
            --accent-primary-hover: #1d4ed8;
            --accent-success: #059669;
            --accent-warning: #d97706;
            --accent-danger: #dc2626;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            overflow-x: hidden;
        }

        code, pre, .font-mono {
            font-family: 'JetBrains Mono', monospace !important;
        }

        /* Standard custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--bg-primary);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--border-color);
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: var(--accent-primary);
        }

        /* Sidebar Styling */
        .sidebar {
            width: 260px;
            background-color: var(--bg-secondary);
            border-right: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-primary);
            text-decoration: none;
            font-weight: 700;
            letter-spacing: -0.025em;
        }

        .sidebar-menu {
            padding: 1rem;
            flex-grow: 1;
            overflow-y: auto;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-secondary);
            border-radius: 0.375rem;
            text-decoration: none;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-link:hover, .sidebar-link.active {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .sidebar-link.active {
            border-left: 3px solid var(--accent-primary);
            border-top-left-radius: 0;
            border-bottom-left-radius: 0;
        }

        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: center;
        }

        /* Topbar Styling */
        .topbar {
            height: 70px;
            background-color: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            position: fixed;
            top: 0;
            right: 0;
            left: 260px;
            z-index: 90;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 1.5rem;
            transition: all 0.3s ease;
        }

        /* Main Content Wrapper */
        .main-content {
            margin-left: 260px;
            padding-top: 70px; /* offset topbar height */
            min-height: 100vh;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .content-body {
            padding: 2rem;
            flex-grow: 1;
        }

        /* Card Custom Styling */
        .custom-card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .custom-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        /* Custom Badges */
        .custom-badge {
            font-weight: 500;
            font-size: 0.75rem;
            padding: 0.25rem 0.6rem;
            border-radius: 50rem;
        }

        .badge-success { background-color: rgba(16, 185, 129, 0.15); color: var(--accent-success); }
        .badge-warning { background-color: rgba(245, 158, 11, 0.15); color: var(--accent-warning); }
        .badge-danger { background-color: rgba(239, 68, 68, 0.15); color: var(--accent-danger); }
        .badge-primary { background-color: rgba(59, 130, 246, 0.15); color: var(--accent-primary); }

        /* Responsive Layouts */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-260px);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .topbar {
                left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>

    <!-- 6. Non-blocking Theme Applier (Crucial Optimization) -->
    <script>
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>
</head>
<body>
    <div class="d-flex">
