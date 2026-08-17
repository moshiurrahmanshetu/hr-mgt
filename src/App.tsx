import React, { useState, useEffect } from 'react';
import { 
  Folder, 
  FileCode, 
  Terminal, 
  Database as DbIcon, 
  Eye, 
  EyeOff, 
  ArrowRight, 
  LogOut, 
  Sun, 
  Moon, 
  Grid, 
  ChevronRight, 
  Clipboard, 
  Check, 
  HelpCircle, 
  AlertCircle, 
  Play, 
  BookOpen, 
  Server, 
  Download,
  Building,
  Users,
  Calendar,
  DollarSign
} from 'lucide-react';
import { PHP_CODEBASE, PHPFile } from './phpFilesData';

// Custom Mock Database for SQL Console Simulator
const MOCK_DB: Record<string, any[]> = {
  users: [
    { id: 1, username: 'admin', email: 'admin@hrmsystem.com', role: 'Admin', status: 'Active', created_at: '2026-06-01' },
    { id: 2, username: 'johndoe', email: 'john.doe@hrmsystem.com', role: 'Employee', status: 'Active', created_at: '2026-07-02' },
    { id: 3, username: 'sarahj', email: 'sarah.jenkins@hrmsystem.com', role: 'HR Manager', status: 'Active', created_at: '2026-07-02' },
    { id: 4, username: 'mikeross', email: 'mike.ross@hrmsystem.com', role: 'Line Manager', status: 'Active', created_at: '2026-07-02' }
  ],
  employees: [
    { id: 1, first_name: 'Sarah', last_name: 'Jenkins', employee_code: 'EMP-10001', email: 'sarah.jenkins@hrmsystem.com', job_title: 'HR Manager', department_id: 1, salary: '$75,000.00', status: 'Full-Time' },
    { id: 2, first_name: 'Mike', last_name: 'Ross', employee_code: 'EMP-10002', email: 'mike.ross@hrmsystem.com', job_title: 'Senior Software Engineer', department_id: 2, salary: '$98,000.00', status: 'Full-Time' },
    { id: 3, first_name: 'John', last_name: 'Doe', employee_code: 'EMP-10003', email: 'john.doe@hrmsystem.com', job_title: 'Software Developer', department_id: 2, salary: '$65,000.00', status: 'Full-Time' }
  ],
  departments: [
    { id: 1, name: 'Human Resources', code: 'HR', manager: 'Sarah Jenkins' },
    { id: 2, name: 'Engineering', code: 'ENG', manager: 'Mike Ross' },
    { id: 3, name: 'Finance', code: 'FIN', manager: 'NULL' },
    { id: 4, name: 'Marketing & Sales', code: 'MKT', manager: 'NULL' }
  ],
  attendance: [
    { id: 1, employee: 'Sarah Jenkins', date: '2026-07-02', clock_in: '08:30:00', clock_out: '17:00:00', status: 'Present' },
    { id: 2, employee: 'Mike Ross', date: '2026-07-02', clock_in: '09:05:00', clock_out: '17:30:00', status: 'Late' },
    { id: 3, employee: 'John Doe', date: '2026-07-02', clock_in: '08:55:00', clock_out: '17:00:00', status: 'Present' }
  ]
};

export default function App() {
  // Core Tabs: 'simulator' (Live running application) | 'code' (Workspace explorer) | 'guide' (XAMPP installation manual)
  const [activeTab, setActiveTab] = useState<'simulator' | 'code' | 'guide'>('simulator');
  
  // Theme state: dark | light (Applied globally across simulator & explorer)
  const [theme, setTheme] = useState<'dark' | 'light'>('dark');

  // --- EXPLORER STATES ---
  const [selectedFolder, setSelectedFolder] = useState<string>("root");
  const [selectedFile, setSelectedFile] = useState<PHPFile>(PHP_CODEBASE["root"].files[0]);
  const [copiedPath, setCopiedPath] = useState<string | null>(null);
  const [copiedCode, setCopiedCode] = useState<boolean>(false);

  // --- SIMULATOR STATES ---
  const [simRoute, setSimRoute] = useState<'login' | 'dashboard' | 'schema' | 'sql_console' | 'forgot-password' | 'reset-password' | 'employees' | 'employees-create' | 'employees-show' | 'employees-edit'>('login');
  const [simAuth, setSimAuth] = useState<boolean>(false);
  const [simUsername, setSimUsername] = useState<string>('admin');
  const [simPassword, setSimPassword] = useState<string>('Admin@HRM2026!');
  const [showPassword, setShowPassword] = useState<boolean>(false);
  const [simUserRole, setSimUserRole] = useState<'Admin' | 'HR Manager' | 'Employee'>('Admin');
  const [flash, setFlash] = useState<{ type: 'success' | 'danger'; text: string } | null>({
    type: 'success',
    text: 'Sprint 03 Workforce & Employee Management module initialized. PDO transactions active.'
  });

  // Employees mock list state
  const [employeesList, setEmployeesList] = useState<any[]>(MOCK_DB.employees);
  const [selectedEmp, setSelectedEmp] = useState<any>(MOCK_DB.employees[0]);
  
  // Search & Filter state
  const [empSearch, setEmpSearch] = useState<string>('');
  const [empFilterDept, setEmpFilterDept] = useState<string>('all');
  const [empFilterStatus, setEmpFilterStatus] = useState<string>('all');
  
  // Form input states
  const [formFirstName, setFormFirstName] = useState('');
  const [formLastName, setFormLastName] = useState('');
  const [formCode, setFormCode] = useState('');
  const [formEmail, setFormEmail] = useState('');
  const [formPhone, setFormPhone] = useState('');
  const [formHireDate, setFormHireDate] = useState('');
  const [formJobTitle, setFormJobTitle] = useState('');
  const [formSalary, setFormSalary] = useState('');
  const [formStatus, setFormStatus] = useState('Full-Time');
  const [formGender, setFormGender] = useState('Prefer Not to Say');
  const [formDOB, setFormDOB] = useState('');
  const [formAddress, setFormAddress] = useState('');
  const [formBankName, setFormBankName] = useState('');
  const [formBankAccountName, setFormBankAccountName] = useState('');
  const [formBankAccountNumber, setFormBankAccountNumber] = useState('');
  const [formBankRoutingNumber, setFormBankRoutingNumber] = useState('');
  const [formEmergencyName, setFormEmergencyName] = useState('');
  const [formEmergencyPhone, setFormEmergencyPhone] = useState('');
  
  // SQL Terminal
  const [sqlQuery, setSqlQuery] = useState<string>('SELECT * FROM employees;');
  const [queryOutput, setQueryOutput] = useState<{ cols: string[]; rows: any[]; message: string; status: 'success' | 'danger' | 'warning' } | null>(null);
  const [queryDuration, setQueryDuration] = useState<number>(0);

  // Sync theme to root HTML element to update styling variables
  useEffect(() => {
    document.documentElement.setAttribute('data-theme', theme);
  }, [theme]);

  // Handle simulated login submit
  const handleSimLogin = (e: React.FormEvent) => {
    e.preventDefault();
    if (simUsername.trim() === 'admin' && simPassword === 'Admin@HRM2026!') {
      setSimAuth(true);
      setSimUserRole('Admin');
      setFlash({
        type: 'success',
        text: 'Welcome back, System Administrator! You have successfully authenticated via secure PDO session verification.'
      });
      setSimRoute('dashboard');
    } else if (simUsername.trim() === 'hr' && simPassword === 'DemoUser123!') {
      setSimAuth(true);
      setSimUserRole('HR Manager');
      setFlash({
        type: 'success',
        text: 'Welcome back, HR Manager! Access granted.'
      });
      setSimRoute('dashboard');
    } else {
      setFlash({
        type: 'danger',
        text: 'Invalid authentication credentials provided. Hint: Use admin / Admin@HRM2026!'
      });
    }
  };

  // Handle simulated logout
  const handleSimLogout = () => {
    setSimAuth(false);
    setFlash({
      type: 'success',
      text: 'You have been safely disconnected. All active session variables are terminated.'
    });
    setSimRoute('login');
  };

  // Run Simulated SQL query
  const runSimQuery = () => {
    const raw = sqlQuery.trim();
    const clean = raw.toLowerCase().replace(/;/g, '');
    const start = performance.now();

    if (!clean) {
      setQueryOutput({
        cols: [],
        rows: [],
        message: 'Syntax Error: Please enter a valid SQL statement.',
        status: 'danger'
      });
      setQueryDuration(0);
      return;
    }

    if (!clean.startsWith('select')) {
      setQueryOutput({
        cols: [],
        rows: [],
        message: 'Security Alert: DDL and destructive statements (INSERT/UPDATE/DELETE/DROP) are locked in the console sandbox. Only SELECT operations are allowed on local database schemas.',
        status: 'warning'
      });
      setQueryDuration(Number((performance.now() - start).toFixed(2)));
      return;
    }

    let targetTable = '';
    if (clean.includes('users')) targetTable = 'users';
    else if (clean.includes('employees')) targetTable = 'employees';
    else if (clean.includes('departments')) targetTable = 'departments';
    else if (clean.includes('attendance')) targetTable = 'attendance';

    if (targetTable && MOCK_DB[targetTable]) {
      const data = MOCK_DB[targetTable];
      const cols = Object.keys(data[0]);
      setQueryOutput({
        cols,
        rows: data,
        message: `Query OK. Fetched ${data.length} records. Reading from simulated InnoDB active connection pool.`,
        status: 'success'
      });
    } else if (clean.includes('count') && clean.includes('headcount')) {
      // Custom join query template
      setQueryOutput({
        cols: ['Department', 'Code', 'Headcount'],
        rows: [
          { Department: 'Engineering', Code: 'ENG', Headcount: 2 },
          { Department: 'Human Resources', Code: 'HR', Headcount: 1 },
          { Department: 'Finance', Code: 'FIN', Headcount: 0 },
          { Department: 'Marketing & Sales', Code: 'MKT', Headcount: 0 }
        ],
        message: 'Query OK. Join aggregate query processed successfully.',
        status: 'success'
      });
    } else {
      setQueryOutput({
        cols: [],
        rows: [],
        message: 'Query executed successfully with empty results. Did you mean: SELECT * FROM employees; ?',
        status: 'warning'
      });
    }

    setQueryDuration(Number((performance.now() - start).toFixed(2)));
  };

  // Copy helper
  const handleCopyCode = (code: string) => {
    navigator.clipboard.writeText(code);
    setCopiedCode(true);
    setTimeout(() => setCopiedCode(false), 2000);
  };

  return (
    <div className={`min-h-screen bg-[var(--bg-primary)] text-[var(--text-primary)] transition-colors duration-300 font-sans`}>
      
      {/* 1. Header Toolbar (Developer Hub Switcher) */}
      <header className="sticky top-0 z-50 border-b border-[var(--border-color)] bg-[var(--bg-secondary)] px-4 py-3 shadow-md flex flex-wrap justify-between items-center gap-4">
        <div className="flex items-center gap-3">
          <div className="bg-blue-600/10 p-2 rounded border border-blue-500/30">
            <Server className="w-5 h-5 text-blue-500" />
          </div>
          <div>
            <h1 className="text-sm font-bold tracking-tight">HRM PHP Architecture Portal</h1>
            <p className="text-[10px] text-gray-500 font-mono">Senior PHP Architect Workspace • Sprint 01 Build</p>
          </div>
        </div>

        {/* Global Navigation Tabs */}
        <div className="flex items-center gap-1 bg-[var(--bg-primary)] p-1 rounded-lg border border-[var(--border-color)]">
          <button 
            onClick={() => setActiveTab('simulator')}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-all ${
              activeTab === 'simulator' 
                ? 'bg-blue-600 text-white shadow-sm' 
                : 'text-gray-400 hover:text-[var(--text-primary)]'
            }`}
          >
            <Play className="w-3.5 h-3.5" />
            <span>Interactive Live App</span>
          </button>
          
          <button 
            onClick={() => {
              setActiveTab('code');
              // Default select config folder file
              setSelectedFolder('config');
              setSelectedFile(PHP_CODEBASE['config'].files[0]);
            }}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-all ${
              activeTab === 'code' 
                ? 'bg-blue-600 text-white shadow-sm' 
                : 'text-gray-400 hover:text-[var(--text-primary)]'
            }`}
          >
            <FileCode className="w-3.5 h-3.5" />
            <span>PHP Code Explorer</span>
          </button>

          <button 
            onClick={() => setActiveTab('guide')}
            className={`px-3 py-1.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-all ${
              activeTab === 'guide' 
                ? 'bg-blue-600 text-white shadow-sm' 
                : 'text-gray-400 hover:text-[var(--text-primary)]'
            }`}
          >
            <BookOpen className="w-3.5 h-3.5" />
            <span>Installation Guide</span>
          </button>
        </div>

        {/* Theme and Profile utilities */}
        <div className="flex items-center gap-3">
          <button 
            onClick={() => setTheme(theme === 'dark' ? 'light' : 'dark')}
            className="p-1.5 rounded border border-[var(--border-color)] hover:bg-[var(--bg-tertiary)] text-gray-400 transition"
            title="Toggle Light/Dark Theme"
          >
            {theme === 'dark' ? <Sun className="w-4 h-4 text-amber-500" /> : <Moon className="w-4 h-4 text-indigo-500" />}
          </button>
          <div className="hidden sm:flex flex-col text-right font-mono text-[10px]">
            <span className="text-green-500 font-bold flex items-center justify-end gap-1">
              <span className="w-1.5 h-1.5 rounded-full bg-green-500 animate-ping"></span>
              PORT 3000
            </span>
            <span className="text-gray-500">Core PHP 8.2 & MySQL</span>
          </div>
        </div>
      </header>

      {/* 2. Main Workspace Layout */}
      <div className="max-w-7xl mx-auto p-4 sm:p-6">
        
        {/* TAB 1: INTERACTIVE LIVE PHP SIMULATOR */}
        {activeTab === 'simulator' && (
          <div className="space-y-4 animate-fadeIn">
            {/* Context Alert banner */}
            <div className="bg-blue-950/40 border border-blue-900/60 p-3.5 rounded-lg flex items-start gap-3">
              <HelpCircle className="w-5 h-5 text-blue-400 shrink-0 mt-0.5" />
              <div className="text-xs">
                <span className="font-semibold text-blue-400">Sandbox Emulator:</span> This tab provides a high-fidelity visual simulation of the running PHP application rendered with <span className="font-semibold text-blue-400">Bootstrap 5</span>, <span className="font-semibold text-blue-400 font-mono">Chart.js</span>, and <span className="font-semibold text-blue-400">AOS</span>. Test authentication, inspect database records dynamically, and toggle system-wide themes.
              </div>
            </div>

            {/* Simulated Frame */}
            <div className="border border-[var(--border-color)] rounded-xl shadow-xl bg-[var(--bg-secondary)] overflow-hidden min-h-[560px] flex flex-col">
              
              {/* Simulator Alert Flashes */}
              {flash && (
                <div className={`p-3 text-xs flex justify-between items-center gap-2 border-b ${
                  flash.type === 'success' 
                    ? 'bg-emerald-950/50 border-emerald-900/60 text-emerald-400' 
                    : 'bg-rose-950/50 border-rose-900/60 text-rose-400'
                }`}>
                  <div className="flex items-center gap-2">
                    <span className="w-2 h-2 rounded-full bg-current animate-pulse"></span>
                    <span>{flash.text}</span>
                  </div>
                  <button onClick={() => setFlash(null)} className="hover:opacity-75 font-bold">×</button>
                </div>
              )}

              {/* SIMULATED VIEW CONTROLLER */}
              <div className="flex-grow flex flex-col">
                
                {/* VIEW A: LOGIN PAGE */}
                {simRoute === 'login' && (
                  <div className="flex-grow flex items-center justify-center p-6 bg-[var(--bg-primary)]">
                    <div className="w-full max-w-sm border border-[var(--border-color)] bg-[var(--bg-secondary)] p-6 sm:p-8 rounded-lg shadow-lg">
                      <div className="text-center mb-6">
                        <div className="inline-flex bg-blue-600/10 border border-blue-500/20 p-3 rounded-full mb-3">
                          <DbIcon className="w-6 h-6 text-blue-500" />
                        </div>
                        <h3 className="font-bold text-lg">Sign in to HRM Portal</h3>
                        <p className="text-xs text-gray-500 mt-1">Sprint 01 Authentication Foundation</p>
                      </div>

                      <form onSubmit={handleSimLogin} className="space-y-4">
                        <div>
                          <label className="block text-xs font-medium text-gray-400 mb-1.5">Username or Email</label>
                          <input 
                            type="text" 
                            className="w-full text-xs p-2.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:border-blue-500 focus:outline-none" 
                            value={simUsername}
                            onChange={(e) => setSimUsername(e.target.value)}
                            placeholder="admin"
                            required
                          />
                        </div>

                        <div>
                          <div className="flex justify-between items-center mb-1.5">
                            <label className="block text-xs font-medium text-gray-400">Password</label>
                            <span className="text-[10px] text-blue-500 font-mono">Master key: Admin@HRM2026!</span>
                          </div>
                          <div className="relative">
                            <input 
                              type={showPassword ? 'text' : 'password'} 
                              className="w-full text-xs p-2.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:border-blue-500 focus:outline-none pr-10" 
                              value={simPassword}
                              onChange={(e) => setSimPassword(e.target.value)}
                              placeholder="••••••••••••"
                              required
                            />
                            <button 
                              type="button"
                              onClick={() => setShowPassword(!showPassword)}
                              className="absolute right-3 top-3 text-gray-500 hover:text-gray-300"
                            >
                              {showPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                            </button>
                          </div>
                        </div>

                        <div className="flex items-center justify-between text-xs text-gray-400 py-1">
                          <div className="flex items-center gap-2">
                            <input type="checkbox" id="remember-me" defaultChecked className="rounded border-gray-700 bg-gray-900" />
                            <label htmlFor="remember-me" className="cursor-pointer select-none">Remember this session</label>
                          </div>
                          <button 
                            type="button"
                            onClick={() => { setSimRoute('forgot-password'); setFlash(null); }}
                            className="text-blue-500 hover:text-blue-400 hover:underline transition"
                          >
                            Forgot Password?
                          </button>
                        </div>

                        <button 
                          type="submit" 
                          className="w-full bg-blue-600 hover:bg-blue-700 text-white p-2.5 text-xs font-semibold rounded flex items-center justify-center gap-1.5 transition"
                        >
                          <span>Access Dashboard</span>
                          <ArrowRight className="w-4 h-4" />
                        </button>
                      </form>
                    </div>
                  </div>
                )}

                {/* VIEW A.1: FORGOT PASSWORD */}
                {simRoute === 'forgot-password' && (
                  <div className="flex-grow flex items-center justify-center p-6 bg-[var(--bg-primary)]">
                    <div className="w-full max-w-sm border border-[var(--border-color)] bg-[var(--bg-secondary)] p-6 sm:p-8 rounded-lg shadow-lg">
                      <div className="text-center mb-6">
                        <div className="inline-flex bg-amber-600/10 border border-amber-500/20 p-3 rounded-full mb-3">
                          <HelpCircle className="w-6 h-6 text-amber-500" />
                        </div>
                        <h3 className="font-bold text-lg">Recover Password</h3>
                        <p className="text-xs text-gray-500 mt-1">Enter your username or email address below.</p>
                      </div>

                      <form onSubmit={(e) => {
                        e.preventDefault();
                        const simulatedToken = 'HRM-RST-' + Math.random().toString(36).substring(2, 8).toUpperCase();
                        setFlash({
                          type: 'success',
                          text: `Security link dispatched! [SIMULATION] Click to use token: ${simulatedToken} and open password reset.`
                        });
                        setSimRoute('reset-password');
                      }} className="space-y-4">
                        <div>
                          <label className="block text-xs font-medium text-gray-400 mb-1.5">Username or Email Address</label>
                          <input 
                            type="text" 
                            className="w-full text-xs p-2.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:border-blue-500 focus:outline-none" 
                            placeholder="admin@hrmsystem.com"
                            required
                          />
                        </div>

                        <button 
                          type="submit" 
                          className="w-full bg-blue-600 hover:bg-blue-700 text-white p-2.5 text-xs font-semibold rounded flex items-center justify-center gap-1.5 transition"
                        >
                          <span>Request Reset Token</span>
                          <ArrowRight className="w-4 h-4" />
                        </button>

                        <div className="text-center pt-2">
                          <button 
                            type="button" 
                            onClick={() => { setSimRoute('login'); setFlash(null); }}
                            className="text-xs text-gray-400 hover:text-white transition"
                          >
                            &larr; Return to Sign In
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                )}

                {/* VIEW A.2: RESET PASSWORD */}
                {simRoute === 'reset-password' && (
                  <div className="flex-grow flex items-center justify-center p-6 bg-[var(--bg-primary)]">
                    <div className="w-full max-w-sm border border-[var(--border-color)] bg-[var(--bg-secondary)] p-6 sm:p-8 rounded-lg shadow-lg">
                      <div className="text-center mb-6">
                        <div className="inline-flex bg-emerald-600/10 border border-emerald-500/20 p-3 rounded-full mb-3">
                          <DbIcon className="w-6 h-6 text-emerald-500" />
                        </div>
                        <h3 className="font-bold text-lg">Establish New Password</h3>
                        <p className="text-xs text-gray-500 mt-1">Provide your reset token and your new credentials.</p>
                      </div>

                      <form onSubmit={(e) => {
                        e.preventDefault();
                        setFlash({
                          type: 'success',
                          text: 'Simulated password change successfully committed to the database. You can now log in.'
                        });
                        setSimRoute('login');
                      }} className="space-y-4">
                        <div>
                          <label className="block text-xs font-medium text-gray-400 mb-1.5">Reset Token</label>
                          <input 
                            type="text" 
                            className="w-full text-xs p-2.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:border-blue-500 focus:outline-none font-mono" 
                            placeholder="e.g. HRM-RST-XXXXXX"
                            required
                          />
                        </div>

                        <div>
                          <label className="block text-xs font-medium text-gray-400 mb-1.5">New Secure Password</label>
                          <input 
                            type="password" 
                            className="w-full text-xs p-2.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:border-blue-500 focus:outline-none" 
                            placeholder="••••••••••••"
                            required
                          />
                        </div>

                        <div>
                          <label className="block text-xs font-medium text-gray-400 mb-1.5">Confirm New Password</label>
                          <input 
                            type="password" 
                            className="w-full text-xs p-2.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:border-blue-500 focus:outline-none" 
                            placeholder="••••••••••••"
                            required
                          />
                        </div>

                        <button 
                          type="submit" 
                          className="w-full bg-blue-600 hover:bg-blue-700 text-white p-2.5 text-xs font-semibold rounded flex items-center justify-center gap-1.5 transition"
                        >
                          <span>Save Password</span>
                          <ArrowRight className="w-4 h-4" />
                        </button>

                        <div className="text-center pt-2">
                          <button 
                            type="button" 
                            onClick={() => { setSimRoute('login'); setFlash(null); }}
                            className="text-xs text-gray-400 hover:text-white transition"
                          >
                            &larr; Return to Sign In
                          </button>
                        </div>
                      </form>
                    </div>
                  </div>
                )}

                {/* AUTHENTICATED WRAPPER (TOPBAR + SIDEBAR) */}
                {simAuth && (
                  <div className="flex flex-col lg:flex-row flex-grow">
                    
                    {/* Simulated Sidebar */}
                    <nav className="w-full lg:w-56 border-b lg:border-b-0 lg:border-r border-[var(--border-color)] bg-[var(--bg-secondary)] flex flex-row lg:flex-col shrink-0">
                      
                      {/* Logo header inside rail */}
                      <div className="hidden lg:flex p-4 border-b border-[var(--border-color)] items-center gap-2">
                        <DbIcon className="w-5 h-5 text-blue-500" />
                        <span className="font-bold text-sm tracking-tight">HRM Portal</span>
                      </div>

                      {/* Navigation links */}
                      <div className="flex flex-wrap lg:flex-col lg:p-3 gap-1 w-full p-2">
                        <button 
                          onClick={() => { setSimRoute('dashboard'); setFlash(null); }}
                          className={`flex items-center gap-2 px-3 py-2 rounded text-xs font-medium w-full text-left transition ${
                            simRoute === 'dashboard' ? 'bg-[var(--bg-tertiary)] text-blue-500 border-l-2 border-blue-500' : 'text-gray-400 hover:text-white'
                          }`}
                        >
                          <Grid className="w-4 h-4" />
                          <span>Dashboard</span>
                        </button>

                        <button 
                          onClick={() => { setSimRoute('employees'); setFlash(null); }}
                          className={`flex items-center gap-2 px-3 py-2 rounded text-xs font-medium w-full text-left transition ${
                            simRoute === 'employees' || simRoute === 'employees-create' || simRoute === 'employees-show' || simRoute === 'employees-edit' ? 'bg-[var(--bg-tertiary)] text-blue-500 border-l-2 border-blue-500' : 'text-gray-400 hover:text-white'
                          }`}
                        >
                          <Users className="w-4 h-4" />
                          <span>Employee Directory</span>
                        </button>

                        <button 
                          onClick={() => { setSimRoute('schema'); setFlash(null); }}
                          className={`flex items-center gap-2 px-3 py-2 rounded text-xs font-medium w-full text-left transition ${
                            simRoute === 'schema' ? 'bg-[var(--bg-tertiary)] text-blue-500 border-l-2 border-blue-500' : 'text-gray-400 hover:text-white'
                          }`}
                        >
                          <DbIcon className="w-4 h-4" />
                          <span>Database Schema</span>
                        </button>

                        <button 
                          onClick={() => { setSimRoute('sql_console'); setFlash(null); }}
                          className={`flex items-center gap-2 px-3 py-2 rounded text-xs font-medium w-full text-left transition ${
                            simRoute === 'sql_console' ? 'bg-[var(--bg-tertiary)] text-blue-500 border-l-2 border-blue-500' : 'text-gray-400 hover:text-white'
                          }`}
                        >
                          <Terminal className="w-4 h-4" />
                          <span>SQL Console</span>
                        </button>
                      </div>

                      {/* Locked system divisions footer */}
                      <div className="hidden lg:flex flex-col mt-auto p-4 border-t border-[var(--border-color)] text-[10px] text-gray-500 space-y-2 font-mono">
                        <div className="text-gray-400 font-bold uppercase tracking-wider text-[8px]">Future Modules</div>
                        <div className="flex items-center justify-between">
                          <span>Attendance</span>
                          <span className="bg-yellow-950 text-yellow-500 px-1 rounded text-[7px] border border-yellow-800">Locked</span>
                        </div>
                        <div className="flex items-center justify-between">
                          <span>Payroll Hub</span>
                          <span className="bg-yellow-950 text-yellow-500 px-1 rounded text-[7px] border border-yellow-800">Locked</span>
                        </div>
                      </div>
                    </nav>

                    {/* Simulated Content Pane */}
                    <div className="flex-grow flex flex-col bg-[var(--bg-primary)]">
                      
                      {/* Simulated Topbar */}
                      <div className="h-14 border-b border-[var(--border-color)] px-4 flex justify-between items-center shrink-0">
                        <div className="text-xs font-medium text-gray-400 font-mono">
                          {simRoute === 'dashboard' && 'index.php?route=dashboard'}
                          {simRoute === 'schema' && 'index.php?route=schema'}
                          {simRoute === 'sql_console' && 'index.php?route=sql_console'}
                        </div>

                        {/* Profile action widget */}
                        <div className="flex items-center gap-3">
                          <div className="text-right">
                            <div className="text-xs font-bold text-gray-200">System Administrator</div>
                            <div className="text-[10px] text-gray-500">Global Admin role</div>
                          </div>
                          
                          <button 
                            onClick={handleSimLogout}
                            className="p-1.5 rounded border border-rose-900/40 text-rose-500 hover:bg-rose-950/30 transition"
                            title="Sign Out Securely"
                          >
                            <LogOut className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </div>

                      {/* Content details based on active route */}
                      <div className="p-4 sm:p-6 overflow-y-auto max-h-[500px]">
                        
                        {/* VIEW B: PORTAL DASHBOARD */}
                        {simRoute === 'dashboard' && (
                          <div className="space-y-6">
                            {/* Greeting */}
                            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                              <div>
                                <h4 className="text-lg font-bold">HRM Corporate Dashboard</h4>
                                <p className="text-xs text-gray-500">Simulating live, real-time database metric logs and headcount aggregates.</p>
                              </div>
                              <div className="text-xs bg-[var(--bg-secondary)] border border-[var(--border-color)] px-3 py-1.5 rounded font-mono text-gray-400">
                                Thursday, 02 July 2026
                              </div>
                            </div>

                            {/* 4 KPI Summary Metric row */}
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                              {/* Card 1 */}
                              <div className="bg-[var(--bg-secondary)] p-4 rounded-lg border border-[var(--border-color)] flex justify-between items-start shadow-sm">
                                <div className="space-y-1">
                                  <span className="text-[10px] text-gray-500 uppercase font-semibold">Total Personnel</span>
                                  <div className="text-2xl font-bold font-mono">{employeesList.filter(e => e.status !== 'Terminated').length}</div>
                                  <div className="text-[10px] text-emerald-500 flex items-center gap-0.5">Active profiles</div>
                                </div>
                                <div className="p-2 rounded bg-blue-600/10 border border-blue-500/20 text-blue-500">
                                  <Users className="w-4 h-4" />
                                </div>
                              </div>

                              {/* Card 2 */}
                              <div className="bg-[var(--bg-secondary)] p-4 rounded-lg border border-[var(--border-color)] flex justify-between items-start shadow-sm">
                                <div className="space-y-1">
                                  <span className="text-[10px] text-gray-500 uppercase font-semibold">Departments</span>
                                  <div className="text-2xl font-bold font-mono">4</div>
                                  <div className="text-[10px] text-amber-500">Structures divisions</div>
                                </div>
                                <div className="p-2 rounded bg-amber-600/10 border border-amber-500/20 text-amber-500">
                                  <Building className="w-4 h-4" />
                                </div>
                              </div>

                              {/* Card 3 */}
                              <div className="bg-[var(--bg-secondary)] p-4 rounded-lg border border-[var(--border-color)] flex justify-between items-start shadow-sm">
                                <div className="space-y-1">
                                  <span className="text-[10px] text-gray-500 uppercase font-semibold">Pending Leaves</span>
                                  <div className="text-2xl font-bold font-mono">3</div>
                                  <div className="text-[10px] text-rose-500 flex items-center gap-0.5">Requires approval</div>
                                </div>
                                <div className="p-2 rounded bg-rose-600/10 border border-rose-500/20 text-rose-500">
                                  <Calendar className="w-4 h-4" />
                                </div>
                              </div>

                              {/* Card 4 */}
                              <div className="bg-[var(--bg-secondary)] p-4 rounded-lg border border-[var(--border-color)] flex justify-between items-start shadow-sm">
                                <div className="space-y-1">
                                  <span className="text-[10px] text-gray-500 uppercase font-semibold">Payroll Base</span>
                                  <div className="text-2xl font-bold font-mono">$245K</div>
                                  <div className="text-[10px] text-emerald-500">Monthly aggregate</div>
                                </div>
                                <div className="p-2 rounded bg-emerald-600/10 border border-emerald-500/20 text-emerald-500">
                                  <DollarSign className="w-4 h-4" />
                                </div>
                              </div>
                            </div>

                            {/* Charts Visualization Section */}
                            <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">
                              
                              {/* Doughnut Chart: Attendance Status Ratio */}
                              <div className="lg:col-span-5 bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg shadow-sm">
                                <h5 className="text-xs font-bold text-gray-300 mb-4 uppercase tracking-wider">Attendance Status Ratio</h5>
                                
                                <div className="flex flex-col sm:flex-row items-center justify-around gap-4 py-3">
                                  {/* Custom Animated Vector SVG Doughnut */}
                                  <div className="relative w-36 h-36 flex items-center justify-center">
                                    <svg className="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                      {/* Grey Background track */}
                                      <circle cx="50" cy="50" r="40" fill="transparent" stroke={theme === 'dark' ? '#1b2438' : '#e5e7eb'} strokeWidth="10" />
                                      {/* Present: 75% -> dash=188.4, offset=0 */}
                                      <circle cx="50" cy="50" r="40" fill="transparent" stroke="#10b981" strokeWidth="10" strokeDasharray="251.2" strokeDashoffset="62.8" />
                                      {/* Late: 10% -> strokeDasharray="25.1", offset=62.8 */}
                                      <circle cx="50" cy="50" r="40" fill="transparent" stroke="#f59e0b" strokeWidth="10" strokeDasharray="25.1 226.1" strokeDashoffset="-188.4" />
                                      {/* On Leave: 10% -> dash=25.1 */}
                                      <circle cx="50" cy="50" r="40" fill="transparent" stroke="#3b82f6" strokeWidth="10" strokeDasharray="25.1 226.1" strokeDashoffset="-213.5" />
                                      {/* Absent: 5% -> dash=12.5 */}
                                      <circle cx="50" cy="50" r="40" fill="transparent" stroke="#ef4444" strokeWidth="10" strokeDasharray="12.5 238.7" strokeDashoffset="-238.6" />
                                    </svg>
                                    <div className="absolute flex flex-col items-center">
                                      <span className="text-lg font-extrabold font-mono text-gray-200">82%</span>
                                      <span className="text-[9px] text-gray-500 uppercase">On Time</span>
                                    </div>
                                  </div>

                                  {/* Labels column */}
                                  <div className="space-y-1.5 text-[11px] w-full max-w-[140px]">
                                    <div className="flex items-center justify-between">
                                      <span className="flex items-center gap-1.5 text-gray-400"><span className="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>Present</span>
                                      <span className="font-mono font-semibold text-gray-200">26</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                      <span className="flex items-center gap-1.5 text-gray-400"><span className="w-2.5 h-2.5 rounded-full bg-amber-500"></span>Late</span>
                                      <span className="font-mono font-semibold text-gray-200">3</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                      <span className="flex items-center gap-1.5 text-gray-400"><span className="w-2.5 h-2.5 rounded-full bg-blue-500"></span>On Leave</span>
                                      <span className="font-mono font-semibold text-gray-200">2</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                      <span className="flex items-center gap-1.5 text-gray-400"><span className="w-2.5 h-2.5 rounded-full bg-rose-500"></span>Absent</span>
                                      <span className="font-mono font-semibold text-gray-200">1</span>
                                    </div>
                                  </div>
                                </div>
                              </div>

                              {/* Bar Chart: Personnel by Department */}
                              <div className="lg:col-span-7 bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg shadow-sm">
                                <h5 className="text-xs font-bold text-gray-300 mb-4 uppercase tracking-wider">Personnel by Department</h5>
                                
                                <div className="space-y-3.5 py-2">
                                  {/* Bar 1 - Engineering */}
                                  <div className="space-y-1">
                                    <div className="flex justify-between text-xs font-medium">
                                      <span className="text-gray-400">Engineering</span>
                                      <span className="font-mono text-gray-200">15 Employees</span>
                                    </div>
                                    <div className="w-full h-2.5 bg-[var(--bg-primary)] rounded-full overflow-hidden">
                                      <div className="h-full bg-blue-600 rounded-full" style={{ width: '75%' }}></div>
                                    </div>
                                  </div>

                                  {/* Bar 2 - Marketing */}
                                  <div className="space-y-1">
                                    <div className="flex justify-between text-xs font-medium">
                                      <span className="text-gray-400">Marketing & Sales</span>
                                      <span className="font-mono text-gray-200">8 Employees</span>
                                    </div>
                                    <div className="w-full h-2.5 bg-[var(--bg-primary)] rounded-full overflow-hidden">
                                      <div className="h-full bg-indigo-500 rounded-full" style={{ width: '40%' }}></div>
                                    </div>
                                  </div>

                                  {/* Bar 3 - Finance */}
                                  <div className="space-y-1">
                                    <div className="flex justify-between text-xs font-medium">
                                      <span className="text-gray-400">Finance</span>
                                      <span className="font-mono text-gray-200">5 Employees</span>
                                    </div>
                                    <div className="w-full h-2.5 bg-[var(--bg-primary)] rounded-full overflow-hidden">
                                      <div className="h-full bg-amber-500 rounded-full" style={{ width: '25%' }}></div>
                                    </div>
                                  </div>

                                  {/* Bar 4 - HR */}
                                  <div className="space-y-1">
                                    <div className="flex justify-between text-xs font-medium">
                                      <span className="text-gray-400">Human Resources</span>
                                      <span className="font-mono text-gray-200">4 Employees</span>
                                    </div>
                                    <div className="w-full h-2.5 bg-[var(--bg-primary)] rounded-full overflow-hidden">
                                      <div className="h-full bg-emerald-500 rounded-full" style={{ width: '20%' }}></div>
                                    </div>
                                  </div>
                                </div>
                              </div>

                            </div>

                            {/* Data Lists Row */}
                            <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
                              {/* Left column: Recent logs */}
                              <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg shadow-sm">
                                <h5 className="text-xs font-bold text-gray-300 mb-3 uppercase tracking-wider">Attendance Activity Log</h5>
                                <div className="space-y-2">
                                  {MOCK_DB.attendance.map((att, i) => (
                                    <div key={i} className="bg-[var(--bg-primary)] p-2.5 rounded border border-[var(--border-color)] flex justify-between items-center text-xs">
                                      <div className="space-y-0.5">
                                        <div className="font-semibold text-gray-200">{att.employee}</div>
                                        <div className="text-[10px] text-gray-500 font-mono">Date: {att.date}</div>
                                      </div>
                                      <div className="text-right font-mono text-[10px] text-gray-400">
                                        <div>In: {att.clock_in}</div>
                                        <div>Out: {att.clock_out}</div>
                                      </div>
                                      <div>
                                        <span className={`px-2 py-0.5 rounded-full text-[9px] font-semibold ${
                                          att.status === 'Present' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-amber-950 text-amber-400 border border-amber-800'
                                        }`}>
                                          {att.status}
                                        </span>
                                      </div>
                                    </div>
                                  ))}
                                </div>
                              </div>

                              {/* Right column: Pending workflow */}
                              <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg shadow-sm">
                                <h5 className="text-xs font-bold text-gray-300 mb-3 uppercase tracking-wider">Pending Leave Approvals</h5>
                                <div className="space-y-2">
                                  <div className="bg-[var(--bg-primary)] p-2.5 rounded border border-[var(--border-color)] space-y-1.5 text-xs">
                                    <div className="flex justify-between items-start">
                                      <div>
                                        <div className="font-semibold text-gray-200">Sarah Jenkins</div>
                                        <div className="text-[10px] text-gray-500">HR Manager • Annual leave</div>
                                      </div>
                                      <span className="bg-yellow-950 text-yellow-400 px-1.5 py-0.5 rounded text-[9px] border border-yellow-800 font-semibold uppercase">Pending</span>
                                    </div>
                                    <div className="text-[11px] text-gray-400">
                                      <span className="font-mono text-gray-500">Duration:</span> 2026-07-20 to 2026-07-22
                                    </div>
                                    <div className="text-[10px] bg-[var(--bg-secondary)] p-1.5 rounded text-gray-500 italic">
                                      "Family medical checkups and child care services."
                                    </div>
                                    <div className="flex justify-end gap-1.5 pt-1">
                                      <button className="px-2 py-1 bg-red-950/40 text-red-500 border border-red-900/30 text-[10px] rounded hover:bg-red-900/40 transition" disabled>Reject</button>
                                      <button className="px-2 py-1 bg-emerald-950/40 text-emerald-400 border border-emerald-900/30 text-[10px] rounded hover:bg-emerald-900/40 transition" disabled>Approve</button>
                                    </div>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* VIEW C: SCHEMA ACCORDION */}
                        {simRoute === 'schema' && (
                          <div className="space-y-4">
                            <div>
                              <h4 className="text-lg font-bold">Relational Table Schemas</h4>
                              <p className="text-xs text-gray-500">Interactive dictionary of seeded MySQL schemas.</p>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                              {Object.keys(MOCK_DB).map((tbl, idx) => (
                                <div key={idx} className="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg p-4 space-y-3 shadow-sm">
                                  <div className="flex items-center gap-2">
                                    <DbIcon className="w-4.5 h-4.5 text-blue-500" />
                                    <span className="font-mono font-bold text-gray-200">{tbl}</span>
                                    <span className="bg-blue-950 text-blue-400 px-1.5 py-0.5 rounded text-[8px] font-mono border border-blue-900 uppercase">InnoDB</span>
                                  </div>

                                  <div className="border border-[var(--border-color)] rounded overflow-hidden">
                                    <table className="w-full text-left font-mono text-[10px] text-gray-400">
                                      <thead className="bg-[var(--bg-tertiary)] border-b border-[var(--border-color)]">
                                        <tr className="text-gray-300">
                                          <th className="p-1.5">Column</th>
                                          <th className="p-1.5">Mock Row Seed Data</th>
                                        </tr>
                                      </thead>
                                      <tbody>
                                        {Object.entries(MOCK_DB[tbl][0]).map(([col, val], k) => (
                                          <tr key={k} className="border-b border-[var(--border-color)] last:border-b-0 hover:bg-[var(--bg-tertiary)]/20">
                                            <td className="p-1.5 text-blue-400 fw-medium">{col}</td>
                                            <td className="p-1.5 text-gray-500 text-truncate max-w-[150px]">{String(val)}</td>
                                          </tr>
                                        ))}
                                      </tbody>
                                    </table>
                                  </div>
                                </div>
                              ))}
                            </div>
                          </div>
                        )}

                        {/* VIEW D: SQL CONSOLE */}
                        {simRoute === 'sql_console' && (
                          <div className="space-y-4">
                            <div>
                              <h4 className="text-lg font-bold">Simulated SQL Terminal</h4>
                              <p className="text-xs text-gray-500">Run queries against the seeded database layout.</p>
                            </div>

                            <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">
                              {/* SQL Editor Area */}
                              <div className="lg:col-span-5 bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg space-y-3">
                                <h5 className="text-xs font-bold text-gray-300 uppercase">Query Statement</h5>
                                <textarea 
                                  value={sqlQuery}
                                  onChange={(e) => setSqlQuery(e.target.value)}
                                  className="w-full h-32 text-xs p-3 font-mono rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:outline-none focus:border-blue-500 text-blue-400"
                                  placeholder="SELECT * FROM employees;"
                                />
                                
                                <div className="flex justify-between gap-2">
                                  <div className="flex gap-1">
                                    <button 
                                      onClick={() => setSqlQuery('SELECT * FROM employees;')}
                                      className="bg-[var(--bg-tertiary)] text-[10px] text-gray-400 px-2 py-1 rounded border border-[var(--border-color)] hover:text-white"
                                    >
                                      Employees
                                    </button>
                                    <button 
                                      onClick={() => setSqlQuery('SELECT * FROM users;')}
                                      className="bg-[var(--bg-tertiary)] text-[10px] text-gray-400 px-2 py-1 rounded border border-[var(--border-color)] hover:text-white"
                                    >
                                      Users
                                    </button>
                                  </div>
                                  <button 
                                    onClick={runSimQuery}
                                    className="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded font-semibold flex items-center gap-1 transition"
                                  >
                                    <Play className="w-3 h-3" />
                                    <span>Execute</span>
                                  </button>
                                </div>
                              </div>

                              {/* SQL Results Area */}
                              <div className="lg:col-span-7 bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg flex flex-col min-h-[220px]">
                                <div className="flex justify-between items-center mb-3">
                                  <h5 className="text-xs font-bold text-gray-300 uppercase">Terminal Output</h5>
                                  <span className="text-[10px] text-gray-500 font-mono">Duration: {queryDuration}ms</span>
                                </div>

                                <div className="bg-[var(--bg-primary)] border border-[var(--border-color)] p-3 rounded flex-grow overflow-auto max-h-[180px] font-mono text-[10.5px]">
                                  {queryOutput === null ? (
                                    <div className="text-gray-500"><ChevronRight className="inline w-3.5 h-3.5 mr-1" />Ready for query statements. Input SELECT query and hit execute.</div>
                                  ) : (
                                    <div className="space-y-3">
                                      <div className={`${
                                        queryOutput.status === 'success' ? 'text-emerald-500' : queryOutput.status === 'danger' ? 'text-rose-500' : 'text-amber-500'
                                      }`}>
                                        <ChevronRight className="inline w-3.5 h-3.5 mr-1" />{queryOutput.message}
                                      </div>

                                      {queryOutput.rows.length > 0 && (
                                        <div className="border border-[var(--border-color)] rounded overflow-hidden">
                                          <table className="w-full text-left font-mono text-[9.5px]">
                                            <thead className="bg-[var(--bg-tertiary)] border-b border-[var(--border-color)] text-gray-300">
                                              <tr>
                                                {queryOutput.cols.map((c, i) => <th key={i} className="p-1.5">{c}</th>)}
                                              </tr>
                                            </thead>
                                            <tbody className="text-gray-400">
                                              {queryOutput.rows.map((row, idx) => (
                                                <tr key={idx} className="border-b border-[var(--border-color)] last:border-0 hover:bg-[var(--bg-tertiary)]/20">
                                                  {queryOutput.cols.map((col, cIdx) => <td key={cIdx} className="p-1.5">{String(row[col])}</td>)}
                                                </tr>
                                              ))}
                                            </tbody>
                                          </table>
                                        </div>
                                      )}
                                    </div>
                                  )}
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* VIEW E: EMPLOYEE DIRECTORY */}
                        {simRoute === 'employees' && (
                          <div className="space-y-6 animate-fadeIn">
                            <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                              <div>
                                <h4 className="text-md font-bold text-gray-200">Employee Directory</h4>
                                <p className="text-[11px] text-gray-500">Manage real-time corporate personnel registry, compensation structures, and identification papers.</p>
                              </div>
                              <button 
                                onClick={() => {
                                  // Reset form states
                                  setFormFirstName(''); setFormLastName(''); setFormCode('EMP-' + Math.floor(10000 + Math.random() * 90000));
                                  setFormEmail(''); setFormPhone(''); setFormHireDate('2026-07-02'); setFormJobTitle('Software Engineer');
                                  setFormSalary('85000'); setFormStatus('Full-Time'); setFormGender('Male'); setFormDOB('1995-05-15');
                                  setFormAddress('123 Corporate Blvd, Silicon Valley'); setFormBankName('Chase Bank');
                                  setFormBankAccountName('Main Checking'); setFormBankAccountNumber('1234567890'); setFormBankRoutingNumber('987654321');
                                  setFormEmergencyName('Jane Doe'); setFormEmergencyPhone('+1-555-0199');
                                  setSimRoute('employees-create');
                                }}
                                className="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-1.5 rounded font-semibold flex items-center gap-1.5 shadow transition"
                              >
                                <Users className="w-3.5 h-3.5" />
                                <span>Add New Employee</span>
                              </button>
                            </div>

                            {/* Filters Bar */}
                            <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-3 rounded-lg flex flex-wrap gap-3 items-center justify-between">
                              <div className="flex flex-wrap items-center gap-3 flex-grow">
                                <input 
                                  type="text" 
                                  placeholder="Search by name, ID code..." 
                                  value={empSearch}
                                  onChange={(e) => setEmpSearch(e.target.value)}
                                  className="text-xs p-2 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:outline-none focus:border-blue-500 w-full sm:w-44 text-gray-200"
                                />
                                <select 
                                  value={empFilterDept}
                                  onChange={(e) => setEmpFilterDept(e.target.value)}
                                  className="text-xs p-2 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:outline-none text-gray-400"
                                >
                                  <option value="all">All Departments</option>
                                  <option value="1">Human Resources</option>
                                  <option value="2">Engineering</option>
                                  <option value="3">Finance</option>
                                </select>
                                <select 
                                  value={empFilterStatus}
                                  onChange={(e) => setEmpFilterStatus(e.target.value)}
                                  className="text-xs p-2 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] focus:outline-none text-gray-400"
                                >
                                  <option value="all">All Statuses</option>
                                  <option value="Full-Time">Full-Time</option>
                                  <option value="Part-Time">Part-Time</option>
                                  <option value="Contract">Contract</option>
                                  <option value="Terminated">Terminated</option>
                                </select>
                              </div>
                              <div className="text-[10px] text-gray-500 font-mono">
                                Total filtered: {employeesList.filter(e => {
                                  const matchSearch = (e.first_name + ' ' + e.last_name).toLowerCase().includes(empSearch.toLowerCase()) || e.employee_code.toLowerCase().includes(empSearch.toLowerCase());
                                  const matchDept = empFilterDept === 'all' || String(e.department_id) === empFilterDept;
                                  const matchStatus = empFilterStatus === 'all' || e.status === empFilterStatus;
                                  return matchSearch && matchDept && matchStatus;
                                }).length}
                              </div>
                            </div>

                            {/* Directory Table */}
                            <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg overflow-hidden shadow-sm">
                              <div className="overflow-x-auto">
                                <table className="w-full text-left text-xs border-collapse">
                                  <thead>
                                    <tr className="bg-[var(--bg-tertiary)] border-b border-[var(--border-color)] text-gray-300 font-semibold">
                                      <th className="p-2.5">Employee Details</th>
                                      <th className="p-2.5">ID Code</th>
                                      <th className="p-2.5">Corporate Email</th>
                                      <th className="p-2.5">Department</th>
                                      <th className="p-2.5">Base Compensation</th>
                                      <th className="p-2.5">Status</th>
                                      <th className="p-2.5 text-right">Actions</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {employeesList.filter(e => {
                                      const matchSearch = (e.first_name + ' ' + e.last_name).toLowerCase().includes(empSearch.toLowerCase()) || e.employee_code.toLowerCase().includes(empSearch.toLowerCase());
                                      const matchDept = empFilterDept === 'all' || String(e.department_id) === empFilterDept;
                                      const matchStatus = empFilterStatus === 'all' || e.status === empFilterStatus;
                                      return matchSearch && matchDept && matchStatus;
                                    }).map((emp, idx) => (
                                      <tr key={idx} className="border-b border-[var(--border-color)] last:border-0 hover:bg-[var(--bg-tertiary)]/20 transition text-gray-300">
                                        <td className="p-2.5 flex items-center gap-2">
                                          <div className="w-7 h-7 rounded-full bg-blue-600/10 border border-blue-500/20 text-blue-400 font-bold flex items-center justify-center text-[10px]">
                                            {emp.first_name[0]}{emp.last_name[0]}
                                          </div>
                                          <div>
                                            <div className="font-semibold text-gray-200">{emp.first_name} {emp.last_name}</div>
                                            <div className="text-[9px] text-gray-500">{emp.job_title}</div>
                                          </div>
                                        </td>
                                        <td className="p-2.5 font-mono text-[10.5px] text-gray-400">{emp.employee_code}</td>
                                        <td className="p-2.5 text-gray-400">{emp.email}</td>
                                        <td className="p-2.5 text-gray-400">
                                          {emp.department_id === 1 ? 'Human Resources' : emp.department_id === 2 ? 'Engineering' : emp.department_id === 3 ? 'Finance' : 'General'}
                                        </td>
                                        <td className="p-2.5 font-mono text-[10.5px] text-gray-400">
                                          {String(emp.salary).startsWith('$') ? emp.salary : '$' + Number(emp.salary).toLocaleString()}
                                        </td>
                                        <td className="p-2.5">
                                          <span className={`px-2 py-0.5 rounded-full text-[8.5px] font-semibold border ${
                                            emp.status === 'Terminated' 
                                              ? 'bg-rose-950/40 text-rose-400 border-rose-900/60' 
                                              : emp.status === 'Contract' 
                                              ? 'bg-amber-950/40 text-amber-400 border-amber-900/60' 
                                              : 'bg-emerald-950/40 text-emerald-400 border-emerald-900/60'
                                          }`}>
                                            {emp.status}
                                          </span>
                                        </td>
                                        <td className="p-2.5 text-right">
                                          <div className="inline-flex gap-1">
                                            <button 
                                              type="button"
                                              onClick={() => { setSelectedEmp(emp); setSimRoute('employees-show'); }}
                                              className="p-1 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-400 hover:text-white transition"
                                              title="View Profile"
                                            >
                                              <Eye className="w-3.5 h-3.5" />
                                            </button>
                                            {emp.status !== 'Terminated' && (
                                              <>
                                                <button 
                                                  type="button"
                                                  onClick={() => {
                                                    setSelectedEmp(emp);
                                                    setFormFirstName(emp.first_name);
                                                    setFormLastName(emp.last_name);
                                                    setFormCode(emp.employee_code);
                                                    setFormEmail(emp.email);
                                                    setFormPhone(emp.phone || '+1-555-0101');
                                                    setFormHireDate(emp.hire_date || '2026-07-02');
                                                    setFormJobTitle(emp.job_title);
                                                    setFormSalary(String(emp.salary).replace(/[^0-9.]/g, ''));
                                                    setFormStatus(emp.status);
                                                    setFormGender(emp.gender || 'Male');
                                                    setFormDOB(emp.date_of_birth || '1995-05-15');
                                                    setFormAddress(emp.address || '123 Corporate Blvd, Silicon Valley');
                                                    setFormBankName('Chase Bank');
                                                    setFormBankAccountName('Primary Savings');
                                                    setFormBankAccountNumber('123456789');
                                                    setFormBankRoutingNumber('987654321');
                                                    setFormEmergencyName('Jane Doe');
                                                    setFormEmergencyPhone('+1-555-0199');
                                                    setSimRoute('employees-edit');
                                                  }}
                                                  className="p-1 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-400 hover:text-white transition"
                                                  title="Edit Profile"
                                                >
                                                  <FileCode className="w-3.5 h-3.5" />
                                                </button>
                                                <button 
                                                  type="button"
                                                  onClick={() => {
                                                    const confirmed = window.confirm(`Are you sure you want to soft-delete / terminate '${emp.first_name} ${emp.last_name}'? The record status will be updated to Terminated, retaining its audit logs.`);
                                                    if (confirmed) {
                                                      const updated = employeesList.map(item => item.id === emp.id ? { ...item, status: 'Terminated' } : item);
                                                      setEmployeesList(updated);
                                                      setFlash({
                                                        type: 'success',
                                                        text: `Biographical record '${emp.first_name} ${emp.last_name}' status soft-updated to 'Terminated'. Activity logging triggered.`
                                                      });
                                                    }
                                                  }}
                                                  className="p-1 rounded bg-rose-950/20 border border-rose-900/30 text-rose-400 hover:bg-rose-900/20 transition"
                                                  title="Terminate"
                                                >
                                                  <LogOut className="w-3.5 h-3.5" />
                                                </button>
                                              </>
                                            )}
                                          </div>
                                        </td>
                                      </tr>
                                    ))}
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* VIEW F: REGISTER NEW EMPLOYEE */}
                        {simRoute === 'employees-create' && (
                          <div className="space-y-6 animate-fadeIn">
                            <div className="flex justify-between items-center pb-3 border-b border-[var(--border-color)]">
                              <div>
                                <h4 className="text-md font-bold text-gray-200">Register New Employee</h4>
                                <p className="text-[11px] text-gray-500">Inputs populate physical tables. Transactions commit biographical profiles, compensation registries, and emergency points.</p>
                              </div>
                              <button 
                                type="button"
                                onClick={() => setSimRoute('employees')}
                                className="text-xs text-gray-400 hover:text-white font-semibold"
                              >
                                &larr; Return to Directory
                              </button>
                            </div>

                            <form onSubmit={(e) => {
                              e.preventDefault();
                              const newEmp = {
                                id: employeesList.length + 1,
                                first_name: formFirstName,
                                last_name: formLastName,
                                employee_code: formCode,
                                email: formEmail,
                                job_title: formJobTitle,
                                department_id: Number(empFilterDept === 'all' ? '2' : empFilterDept),
                                salary: formSalary,
                                status: formStatus,
                                gender: formGender,
                                phone: formPhone,
                                date_of_birth: formDOB,
                                address: formAddress,
                                hire_date: formHireDate
                              };
                              setEmployeesList([...employeesList, newEmp]);
                              setFlash({
                                type: 'success',
                                text: `Employee profile '${formFirstName} ${formLastName}' created successfully in transactional batch. activity_logs record inserted.`
                              });
                              setSimRoute('employees');
                            }} className="space-y-4">
                              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {/* Bio details */}
                                <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-3 rounded-lg space-y-3 shadow-sm">
                                  <h5 className="text-[11px] font-bold text-gray-300 uppercase tracking-wider border-b border-[var(--border-color)] pb-1.5">1. Personal Information</h5>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">First Name</label>
                                      <input type="text" required value={formFirstName} onChange={e => setFormFirstName(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" placeholder="E.g. Sarah" />
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Last Name</label>
                                      <input type="text" required value={formLastName} onChange={e => setFormLastName(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" placeholder="E.g. Jenkins" />
                                    </div>
                                  </div>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Gender Identification</label>
                                      <select value={formGender} onChange={e => setFormGender(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-400">
                                        <option>Male</option>
                                        <option>Female</option>
                                        <option>Other</option>
                                        <option>Prefer Not to Say</option>
                                      </select>
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Birth Date</label>
                                      <input type="date" value={formDOB} onChange={e => setFormDOB(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-400" />
                                    </div>
                                  </div>
                                </div>

                                {/* Contact Details */}
                                <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-3 rounded-lg space-y-3 shadow-sm">
                                  <h5 className="text-[11px] font-bold text-gray-300 uppercase tracking-wider border-b border-[var(--border-color)] pb-1.5">2. Contact Information</h5>
                                  <div>
                                    <label className="block text-[9px] text-gray-400 mb-1">Corporate Email Address</label>
                                    <input type="email" required value={formEmail} onChange={e => setFormEmail(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" placeholder="sarah.j@company.com" />
                                  </div>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Phone Number</label>
                                      <input type="text" value={formPhone} onChange={e => setFormPhone(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" placeholder="+1-555-0101" />
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Home Address</label>
                                      <input type="text" value={formAddress} onChange={e => setFormAddress(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" placeholder="102 Oak Ave, CA" />
                                    </div>
                                  </div>
                                </div>

                                {/* Employment Details */}
                                <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-3 rounded-lg space-y-3 shadow-sm">
                                  <h5 className="text-[11px] font-bold text-gray-300 uppercase tracking-wider border-b border-[var(--border-color)] pb-1.5">3. Employment Information</h5>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Employee Unique Code</label>
                                      <input type="text" required value={formCode} onChange={e => setFormCode(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200 font-mono" />
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Joining / Hire Date</label>
                                      <input type="date" required value={formHireDate} onChange={e => setFormHireDate(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-400" />
                                    </div>
                                  </div>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Job Designation Title</label>
                                      <input type="text" required value={formJobTitle} onChange={e => setFormJobTitle(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" placeholder="HR Generalist" />
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Status Class</label>
                                      <select value={formStatus} onChange={e => setFormStatus(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-400">
                                        <option>Full-Time</option>
                                        <option>Part-Time</option>
                                        <option>Contract</option>
                                        <option>Intern</option>
                                      </select>
                                    </div>
                                  </div>
                                </div>

                                {/* Salary & Bank */}
                                <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-3 rounded-lg space-y-3 shadow-sm">
                                  <h5 className="text-[11px] font-bold text-gray-300 uppercase tracking-wider border-b border-[var(--border-color)] pb-1.5">4. Compensation & Bank Details</h5>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Basic Base Salary ($)</label>
                                      <input type="number" required value={formSalary} onChange={e => setFormSalary(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200 font-mono" placeholder="75000" />
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Bank Name</label>
                                      <input type="text" value={formBankName} onChange={e => setFormBankName(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" placeholder="Chase Bank" />
                                    </div>
                                  </div>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Account Number</label>
                                      <input type="text" value={formBankAccountNumber} onChange={e => setFormBankAccountNumber(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200 font-mono" placeholder="123456789" />
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Emergency Contact Person</label>
                                      <input type="text" required value={formEmergencyName} onChange={e => setFormEmergencyName(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" placeholder="Jane Doe" />
                                    </div>
                                  </div>
                                </div>
                              </div>

                              <div className="flex justify-end gap-2.5">
                                <button type="button" onClick={() => setSimRoute('employees')} className="px-3.5 py-1.5 border border-[var(--border-color)] rounded text-xs text-gray-400 hover:text-white transition">Cancel</button>
                                <button type="submit" className="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-semibold shadow transition">Commit Transaction</button>
                              </div>
                            </form>
                          </div>
                        )}

                        {/* VIEW G: EMPLOYEE PROFILE PORTFOLIO DETAIL */}
                        {simRoute === 'employees-show' && selectedEmp && (
                          <div className="space-y-5 animate-fadeIn">
                            <div className="flex justify-between items-center pb-3 border-b border-[var(--border-color)]">
                              <button 
                                type="button"
                                onClick={() => setSimRoute('employees')}
                                className="text-xs text-gray-400 hover:text-white font-semibold flex items-center gap-1"
                              >
                                &larr; Return to Directory
                              </button>
                              <div className="text-[10px] text-gray-500 font-mono">Record reference ID: #{selectedEmp.id}</div>
                            </div>

                            {/* Header details block */}
                            <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-5 rounded-lg flex flex-col sm:flex-row items-center gap-4 shadow-sm">
                              <div className="w-16 h-16 rounded-full bg-blue-600/10 border border-blue-500/20 text-blue-400 font-extrabold flex items-center justify-center text-xl">
                                {selectedEmp.first_name[0]}{selectedEmp.last_name[0]}
                              </div>
                              <div className="text-center sm:text-left space-y-1 flex-grow">
                                <div className="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                  <h3 className="text-md font-bold text-gray-100">{selectedEmp.first_name} {selectedEmp.last_name}</h3>
                                  <span className={`px-2 py-0.5 rounded-full text-[8.5px] font-semibold border ${
                                    selectedEmp.status === 'Terminated' 
                                      ? 'bg-rose-950/40 text-rose-400 border-rose-900/60' 
                                      : 'bg-emerald-950/40 text-emerald-400 border-emerald-900/60'
                                  }`}>
                                    {selectedEmp.status}
                                  </span>
                                </div>
                                <p className="text-xs text-gray-400 font-medium">{selectedEmp.job_title}</p>
                                <p className="text-[10px] text-gray-500 font-mono">ID Code: {selectedEmp.employee_code}</p>
                              </div>
                            </div>

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                              {/* Biography */}
                              <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg space-y-3 shadow-sm">
                                <h5 className="text-[11px] font-bold text-gray-300 uppercase tracking-wider border-b border-[var(--border-color)] pb-1.5 flex items-center gap-1.5">
                                  <Users className="w-4 h-4 text-blue-500" />
                                  <span>Biographical & Contacts</span>
                                </h5>
                                <div className="space-y-2 text-xs">
                                  <div className="flex justify-between border-b border-[var(--border-color)] pb-1.5">
                                    <span className="text-gray-500">Corporate Email:</span>
                                    <span className="text-gray-300 font-medium">{selectedEmp.email}</span>
                                  </div>
                                  <div className="flex justify-between border-b border-[var(--border-color)] pb-1.5">
                                    <span className="text-gray-500">Mobile Phone:</span>
                                    <span className="text-gray-300 font-medium">{selectedEmp.phone || '+1-555-0101'}</span>
                                  </div>
                                  <div className="flex justify-between border-b border-[var(--border-color)] pb-1.5">
                                    <span className="text-gray-500">Gender Identity:</span>
                                    <span className="text-gray-300 font-medium">{selectedEmp.gender || 'Male'}</span>
                                  </div>
                                  <div className="flex justify-between">
                                    <span className="text-gray-500">Joining Date:</span>
                                    <span className="text-gray-300 font-medium font-mono">{selectedEmp.hire_date || 'Jul 02, 2026'}</span>
                                  </div>
                                </div>
                              </div>

                              {/* Compensation */}
                              <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg space-y-3 shadow-sm">
                                <h5 className="text-[11px] font-bold text-gray-300 uppercase tracking-wider border-b border-[var(--border-color)] pb-1.5 flex items-center gap-1.5">
                                  <DollarSign className="w-4 h-4 text-emerald-500" />
                                  <span>Salary & Compensation Structure</span>
                                </h5>
                                <div className="space-y-2 text-xs">
                                  <div className="flex justify-between border-b border-[var(--border-color)] pb-1.5">
                                    <span className="text-gray-500">Basic Monthly Base:</span>
                                    <span className="text-emerald-400 font-bold font-mono">
                                      {String(selectedEmp.salary).startsWith('$') ? selectedEmp.salary : '$' + Number(selectedEmp.salary).toLocaleString()}
                                    </span>
                                  </div>
                                  <div className="flex justify-between border-b border-[var(--border-color)] pb-1.5">
                                    <span className="text-gray-500">Bank Partner:</span>
                                    <span className="text-gray-300 font-medium">Chase Bank</span>
                                  </div>
                                  <div className="flex justify-between border-b border-[var(--border-color)] pb-1.5">
                                    <span className="text-gray-500">Account Number:</span>
                                    <span className="text-gray-300 font-mono">1234567890</span>
                                  </div>
                                  <div className="flex justify-between">
                                    <span className="text-gray-500">Emergency Phone:</span>
                                    <span className="text-gray-300 font-medium font-mono">+1-555-0199</span>
                                  </div>
                                </div>
                              </div>
                            </div>
                          </div>
                        )}

                        {/* VIEW H: EDIT EMPLOYEE PROFILE */}
                        {simRoute === 'employees-edit' && selectedEmp && (
                          <div className="space-y-6 animate-fadeIn">
                            <div className="flex justify-between items-center pb-3 border-b border-[var(--border-color)]">
                              <div>
                                <h4 className="text-md font-bold text-gray-200">Modify Personnel Profile</h4>
                                <p className="text-[11px] text-gray-500">Commit edits inside transactional safety boundaries. Database records will update dynamically.</p>
                              </div>
                              <button 
                                type="button"
                                onClick={() => setSimRoute('employees')}
                                className="text-xs text-gray-400 hover:text-white font-semibold"
                              >
                                &larr; Return to Directory
                              </button>
                            </div>

                            <form onSubmit={(e) => {
                              e.preventDefault();
                              const updatedList = employeesList.map(emp => {
                                if (emp.id === selectedEmp.id) {
                                  return {
                                    ...emp,
                                    first_name: formFirstName,
                                    last_name: formLastName,
                                    email: formEmail,
                                    phone: formPhone,
                                    job_title: formJobTitle,
                                    salary: formSalary,
                                    status: formStatus,
                                    gender: formGender,
                                    address: formAddress,
                                    hire_date: formHireDate
                                  };
                                }
                                return emp;
                              });
                              setEmployeesList(updatedList);
                              setFlash({
                                type: 'success',
                                text: `Employee profile for '${formFirstName} ${formLastName}' updated and committed inside database tables.`
                              });
                              setSimRoute('employees');
                            }} className="space-y-4">
                              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-3 rounded-lg space-y-3 shadow-sm">
                                  <h5 className="text-[11px] font-bold text-gray-300 uppercase tracking-wider border-b border-[var(--border-color)] pb-1.5">Modify Personal & Bio</h5>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">First Name</label>
                                      <input type="text" required value={formFirstName} onChange={e => setFormFirstName(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" />
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Last Name</label>
                                      <input type="text" required value={formLastName} onChange={e => setFormLastName(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" />
                                    </div>
                                  </div>
                                  <div>
                                    <label className="block text-[9px] text-gray-400 mb-1">Corporate Email Address</label>
                                    <input type="email" required value={formEmail} onChange={e => setFormEmail(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" />
                                  </div>
                                </div>

                                <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-3 rounded-lg space-y-3 shadow-sm">
                                  <h5 className="text-[11px] font-bold text-gray-300 uppercase tracking-wider border-b border-[var(--border-color)] pb-1.5">Modify Compensation & Status</h5>
                                  <div className="grid grid-cols-2 gap-2">
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Basic Base Salary ($)</label>
                                      <input type="number" required value={formSalary} onChange={e => setFormSalary(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200 font-mono" />
                                    </div>
                                    <div>
                                      <label className="block text-[9px] text-gray-400 mb-1">Status</label>
                                      <select value={formStatus} onChange={e => setFormStatus(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-400">
                                        <option>Full-Time</option>
                                        <option>Part-Time</option>
                                        <option>Contract</option>
                                        <option>Intern</option>
                                      </select>
                                    </div>
                                  </div>
                                  <div>
                                    <label className="block text-[9px] text-gray-400 mb-1">Job Designation Title</label>
                                    <input type="text" required value={formJobTitle} onChange={e => setFormJobTitle(e.target.value)} className="w-full text-xs p-1.5 rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-200" />
                                  </div>
                                </div>
                              </div>

                              <div className="flex justify-end gap-2.5">
                                <button type="button" onClick={() => setSimRoute('employees')} className="px-3.5 py-1.5 border border-[var(--border-color)] rounded text-xs text-gray-400 hover:text-white transition">Cancel</button>
                                <button type="submit" className="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-xs font-semibold shadow transition">Commit Update</button>
                              </div>
                            </form>
                          </div>
                        )}

                      </div>
                    </div>

                  </div>
                )}

              </div>
            </div>
          </div>
        )}

        {/* TAB 2: WORKSPACE FILE EXPLORER (IDE VIEW) */}
        {activeTab === 'code' && (
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 animate-fadeIn">
            
            {/* Sidebar directory list (3/12 cols) */}
            <div className="lg:col-span-3 space-y-4">
              <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] rounded-lg p-4 space-y-3.5 shadow-sm">
                <div className="flex justify-between items-center">
                  <h3 className="text-xs font-bold text-gray-300 uppercase tracking-wider flex items-center gap-1.5">
                    <Folder className="w-4 h-4 text-blue-500" />
                    <span>Project Directories</span>
                  </h3>
                  <span className="text-[9px] font-mono bg-blue-950 text-blue-400 px-1.5 rounded border border-blue-900">CORE</span>
                </div>

                <div className="space-y-1">
                  {Object.entries(PHP_CODEBASE).map(([dirKey, dirObj]) => (
                    <div key={dirKey} className="space-y-1">
                      <div className="flex items-center gap-1.5 text-xs font-bold text-gray-400 px-2 py-1">
                        <Folder className="w-3.5 h-3.5 text-blue-500/80" />
                        <span>{dirObj.name}</span>
                      </div>
                      
                      <div className="pl-4 space-y-0.5">
                        {dirObj.files.map((file, fIdx) => (
                          <button 
                            key={fIdx}
                            onClick={() => {
                              setSelectedFolder(dirKey);
                              setSelectedFile(file);
                            }}
                            className={`w-full text-left text-xs px-2 py-1.5 rounded flex items-center gap-1.5 transition ${
                              selectedFile.path === file.path 
                                ? 'bg-blue-600/10 text-blue-400 border border-blue-500/20 font-medium' 
                                : 'text-gray-500 hover:bg-[var(--bg-tertiary)] hover:text-gray-300'
                            }`}
                          >
                            <FileCode className="w-3.5 h-3.5 text-blue-500/40 shrink-0" />
                            <span className="text-[11.5px] text-truncate">{file.name}</span>
                          </button>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            {/* Editor viewport pane (9/12 cols) */}
            <div className="lg:col-span-9 space-y-4">
              
              {/* File details overview card */}
              <div className="bg-[var(--bg-secondary)] border border-[var(--border-color)] p-4 rounded-lg shadow-sm space-y-3">
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 border-b border-[var(--border-color)] pb-3">
                  <div>
                    <h3 className="text-md font-bold text-gray-200 font-mono">{selectedFile.path}</h3>
                    <p className="text-xs text-gray-400 mt-1">{selectedFile.description}</p>
                  </div>
                  <div className="flex gap-2">
                    <button 
                      onClick={() => {
                        navigator.clipboard.writeText(selectedFile.path);
                        setCopiedPath(selectedFile.path);
                        setTimeout(() => setCopiedPath(null), 1500);
                      }}
                      className="px-2.5 py-1 text-[11px] rounded bg-[var(--bg-primary)] border border-[var(--border-color)] text-gray-400 hover:text-white flex items-center gap-1"
                    >
                      {copiedPath === selectedFile.path ? <Check className="w-3.5 h-3.5 text-green-500" /> : <Clipboard className="w-3.5 h-3.5" />}
                      <span>Copy Path</span>
                    </button>

                    <button 
                      onClick={() => handleCopyCode(selectedFile.code)}
                      className="px-2.5 py-1 text-[11px] rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold flex items-center gap-1 transition"
                    >
                      {copiedCode ? <Check className="w-3.5 h-3.5" /> : <Clipboard className="w-3.5 h-3.5" />}
                      <span>Copy Code</span>
                    </button>
                  </div>
                </div>

                {/* Architectural & Security Decisions */}
                <div className="space-y-1.5">
                  <h4 className="text-[11px] uppercase font-bold text-gray-500 tracking-wider">Architectural Decisions & Security Design</h4>
                  <ul className="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs">
                    {selectedFile.architectureNotes.map((note, index) => (
                      <li key={index} className="flex gap-2 text-gray-400 bg-[var(--bg-primary)] p-2 rounded border border-[var(--border-color)]">
                        <AlertCircle className="w-4 h-4 text-blue-500 shrink-0 mt-0.5" />
                        <span className="leading-relaxed">{note}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              </div>

              {/* Real Code Blocks Frame */}
              <div className="border border-[var(--border-color)] rounded-lg overflow-hidden bg-[#0d1117] shadow-lg">
                <div className="bg-[#161b22] px-4 py-2 border-b border-[var(--border-color)] flex justify-between items-center text-xs text-gray-400 font-mono">
                  <span>Source Viewer</span>
                  <span>PHP</span>
                </div>
                <pre className="p-4 overflow-x-auto text-xs font-mono text-gray-300 leading-relaxed max-h-[480px]">
                  <code>{selectedFile.code}</code>
                </pre>
              </div>

            </div>
          </div>
        )}

        {/* TAB 3: INSTALLATION GUIDE */}
        {activeTab === 'guide' && (
          <div className="max-w-3xl mx-auto bg-[var(--bg-secondary)] border border-[var(--border-color)] p-6 sm:p-8 rounded-xl shadow-md space-y-6 animate-fadeIn">
            <div className="text-center pb-4 border-b border-[var(--border-color)]">
              <BookOpen className="w-8 h-8 text-blue-500 mx-auto mb-2" />
              <h3 className="text-lg font-bold">XAMPP / Laragon Import & Installation Guide</h3>
              <p className="text-xs text-gray-500 mt-1">Get this codebase running on localhost, shared hosting, or dedicated servers in minutes.</p>
            </div>

            <div className="space-y-5">
              
              {/* Step 1 */}
              <div className="flex gap-4">
                <div className="w-6 h-6 rounded-full bg-blue-600/15 border border-blue-500/20 flex items-center justify-center font-mono text-xs font-bold text-blue-400 shrink-0">1</div>
                <div className="text-xs space-y-1">
                  <h4 className="font-bold text-gray-200">Download files and folders</h4>
                  <p className="text-gray-400 leading-relaxed">
                    You can download this complete Sprint 01 project as a ZIP archive by going to the <span className="font-semibold text-gray-300">Settings menu in AI Studio</span> and selecting <span className="font-semibold text-gray-300">Export ZIP</span>. Unzip the archive directly inside your server's web root directory:
                  </p>
                  <ul className="list-disc pl-4 space-y-1 mt-1 font-mono text-[10.5px] text-gray-500">
                    <li>XAMPP (Windows): <code className="text-blue-500">C:/xampp/htdocs/hrm-system/</code></li>
                    <li>Laragon (Windows): <code className="text-blue-500">C:/laragon/www/hrm-system/</code></li>
                    <li>MAMP (Mac): <code className="text-blue-500">/Applications/MAMP/htdocs/hrm-system/</code></li>
                  </ul>
                </div>
              </div>

              {/* Step 2 */}
              <div className="flex gap-4">
                <div className="w-6 h-6 rounded-full bg-blue-600/15 border border-blue-500/20 flex items-center justify-center font-mono text-xs font-bold text-blue-400 shrink-0">2</div>
                <div className="text-xs space-y-1">
                  <h4 className="font-bold text-gray-200">Import the database schema</h4>
                  <p className="text-gray-400 leading-relaxed">
                    Open <span className="font-semibold text-gray-300">phpMyAdmin</span> in your browser (<code className="text-blue-500">http://localhost/phpmyadmin</code>) and:
                  </p>
                  <ol className="list-decimal pl-4 space-y-1.5 mt-1 text-gray-400 leading-relaxed">
                    <li>Create a new database named <span className="font-mono text-blue-400">hrm_database</span> with collation <span className="font-mono text-blue-400">utf8mb4_unicode_ci</span>.</li>
                    <li>Select the newly created database, click on the <span className="font-semibold text-gray-300">Import</span> tab, and browse to select the file <code className="text-blue-500">database/schema.sql</code>.</li>
                    <li>Click <span className="font-semibold text-gray-300">Import / Go</span>. It will import the entire relational structure without any constraint errors.</li>
                  </ol>
                </div>
              </div>

              {/* Step 3 */}
              <div className="flex gap-4">
                <div className="w-6 h-6 rounded-full bg-blue-600/15 border border-blue-500/20 flex items-center justify-center font-mono text-xs font-bold text-blue-400 shrink-0">3</div>
                <div className="text-xs space-y-1">
                  <h4 className="font-bold text-gray-200">Configure credentials</h4>
                  <p className="text-gray-400 leading-relaxed">
                    Open the file <code className="text-blue-500">config/config.php</code> in an editor and check the database connection block. If your local XAMPP MySQL root has a different password, adjust it here:
                  </p>
                  <pre className="bg-[var(--bg-primary)] p-2.5 rounded font-mono text-[10.5px] text-gray-400 border border-[var(--border-color)] mt-1.5">
{`define('DB_HOST', 'localhost');
define('DB_NAME', 'hrm_database');
define('DB_USER', 'root');
define('DB_PASS', ''); // default password`}
                  </pre>
                </div>
              </div>

              {/* Step 4 */}
              <div className="flex gap-4">
                <div className="w-6 h-6 rounded-full bg-blue-600/15 border border-blue-500/20 flex items-center justify-center font-mono text-xs font-bold text-blue-400 shrink-0">4</div>
                <div className="text-xs space-y-1">
                  <h4 className="font-bold text-gray-200">Launch and access the portal</h4>
                  <p className="text-gray-400 leading-relaxed">
                    Open your web browser and navigate to the detected subdirectory route, for instance: <code className="text-blue-500">http://localhost/hrm-system/index.php?route=login</code>. Sign in using the pre-seeded admin master credential:
                  </p>
                  <div className="bg-blue-950/20 border border-blue-900/40 p-2.5 rounded text-[11px] font-mono mt-1.5 text-gray-400 space-y-1">
                    <div><span className="font-bold text-gray-300">Username:</span> admin</div>
                    <div><span className="font-bold text-gray-300">Password:</span> Admin@HRM2026!</div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        )}

      </div>
    </div>
  );
}
