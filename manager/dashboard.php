<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// /manager/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/permissions.php';

if (!isAuthenticated()) {
    header('Location: /login');
    exit;
}
$profile = getProfile();
if ($profile['role'] !== 'manager') {
    header('Location: /dashboard');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get manager's team members
$stmt = $pdo->prepare("
    SELECT u.id, p.name, p.email 
    FROM users u 
    JOIN profiles p ON u.id = p.user_id 
    JOIN team_members tm ON u.id = tm.user_id 
    JOIN teams t ON tm.team_id = t.id 
    WHERE t.manager_id = ?
");
$stmt->execute([$user_id]);
$teamMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalTeamMembers = count($teamMembers);

// Get KPIs and recent applications for the manager's team
$totalTeamApplications = 0;
$totalTeamLoanAmount = 0;
$applications = [];
$teamMemberIds = array_column($teamMembers, 'id');

if (!empty($teamMemberIds)) {
    $placeholders = implode(',', array_fill(0, count($teamMemberIds), '?'));

    // Get KPIs
    $stmt = $pdo->prepare("SELECT COUNT(id) as total_applications, SUM(loan_amount) as total_loan_amount FROM applications WHERE user_id IN ($placeholders)");
    $stmt->execute($teamMemberIds);
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalTeamApplications = $kpis['total_applications'] ?? 0;
    $totalTeamLoanAmount = $kpis['total_loan_amount'] ?? 0;

    // Get RC and CIBIL counts for the current month
    $stmt = $pdo->prepare("
        SELECT action, COUNT(id) as count 
        FROM action_logs 
        WHERE user_id IN ($placeholders) 
        AND action IN ('rc_lookup', 'cibil_lookup') 
        AND MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
        GROUP BY action
    ");
    $stmt->execute($teamMemberIds);
    $actionCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $rcLookups = $actionCounts['rc_lookup'] ?? 0;
    $cibilLookups = $actionCounts['cibil_lookup'] ?? 0;

    // Get recent applications
    $stmt = $pdo->prepare("SELECT a.*, p.name as employee_name FROM applications a JOIN profiles p ON a.user_id = p.user_id WHERE a.user_id IN ($placeholders) ORDER BY a.created_at DESC LIMIT 10");
    $stmt->execute($teamMemberIds);
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// This function is specific to the new design and its CSS
function renderStatusBadge($status) {
    $status = strtolower($status);
    $badges = [
        'approved' => '<span class="status-badge status-approved">Approved</span>',
        'pending' => '<span class="status-badge status-pending">Pending</span>',
        'under_review' => '<span class="status-badge status-review">Under Review</span>',
        'rejected' => '<span class="status-badge status-rejected">Rejected</span>'
    ];
    return $badges[$status] ?? '<span class="status-badge status-default">' . ucfirst($status) . '</span>';
}
require_once __DIR__ . '/../includes/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard - Professional</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #1a202c;
            line-height: 1.6;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
            min-height: 100vh;
        }

        /* Header Styles */
        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dashboard-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .dashboard-subtitle {
            font-size: 1.1rem;
            color: #718096;
            font-weight: 400;
        }

        .manager-name {
            font-weight: 600;
            color: #4a5568;
        }

        /* KPI Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        .kpi-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 2rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--accent-color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .kpi-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .kpi-card:hover::before {
            transform: scaleX(1);
        }

        .kpi-content h3 {
            font-size: 0.875rem;
            font-weight: 500;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .kpi-content p {
            font-size: 2.25rem;
            font-weight: 700;
            color: #2d3748;
        }

        .kpi-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        /* KPI Icon Colors */
        .kpi-card:nth-child(1) { --accent-color: #667eea; }
        .kpi-card:nth-child(1) .kpi-icon { background: linear-gradient(135deg, #667eea, #764ba2); }

        .kpi-card:nth-child(2) { --accent-color: #48bb78; }
        .kpi-card:nth-child(2) .kpi-icon { background: linear-gradient(135deg, #48bb78, #38a169); }

        .kpi-card:nth-child(3) { --accent-color: #ed8936; }
        .kpi-card:nth-child(3) .kpi-icon { background: linear-gradient(135deg, #ed8936, #dd6b20); }

        .kpi-card:nth-child(4) { --accent-color: #e53e3e; }
        .kpi-card:nth-child(4) .kpi-icon { background: linear-gradient(135deg, #e53e3e, #c53030); }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 2rem;
        }

        /* Widget Styles */
        .widget {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .widget:hover {
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }

        .widget-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }

        .widget-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3748;
        }

        .widget-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid transparent;
        }

        .widget-link:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: rgba(102, 126, 234, 0.2);
            transform: translateY(-1px);
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(247, 250, 252, 0.8);
            border-bottom: 2px solid rgba(226, 232, 240, 0.8);
        }

        .data-table tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        }

        .data-table tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.03), rgba(118, 75, 162, 0.03));
            transform: scale(1.005);
        }

        .data-table tbody td {
            padding: 1rem 1.5rem;
            color: #2d3748;
            font-size: 0.875rem;
        }

        /* Status Badges */
        .status-badge {
            padding: 0.375rem 0.875rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .status-approved {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
        }

        .status-pending {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            color: white;
        }

        .status-review {
            background: linear-gradient(135deg, #4299e1, #3182ce);
            color: white;
        }

        .status-rejected {
            background: linear-gradient(135deg, #e53e3e, #c53030);
            color: white;
        }
        
        .status-default {
            background: linear-gradient(135deg, #a0aec0, #718096);
            color: white;
        }

        /* View All Button */
        .view-all-btn {
            width: 100%;
            padding: 1rem;
            background: rgba(102, 126, 234, 0.05);
            border: none;
            color: #667eea;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border-top: 1px solid rgba(226, 232, 240, 0.5);
        }

        .view-all-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #5a67d8;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #718096;
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.125rem;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            font-size: 0.875rem;
        }

        /* Hidden Class */
        .hidden {
            display: none;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-container {
                padding: 1rem;
            }
            
            .dashboard-header {
                padding: 1.5rem;
            }
            
            .dashboard-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .kpi-grid {
                grid-template-columns: 1fr;
            }
            
            .kpi-card {
                padding: 1.5rem;
            }
            
            .kpi-content p {
                font-size: 1.875rem;
            }
            
            .widget-header {
                padding: 1rem 1.5rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .data-table thead th,
            .data-table tbody td {
                padding: 0.75rem 1rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 480px) {
            .dashboard-container {
                padding: 0.5rem;
            }
            
            .dashboard-header {
                padding: 1rem;
            }
            
            .dashboard-title {
                font-size: 1.5rem;
            }
            
            .kpi-card {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
        }

        /* Loading Animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dashboard-container > * {
            animation: fadeInUp 0.6s ease forwards;
        }

        .kpi-card:nth-child(2) { animation-delay: 0.1s; }
        .kpi-card:nth-child(3) { animation-delay: 0.2s; }
        .kpi-card:nth-child(4) { animation-delay: 0.3s; }
        .content-grid { animation-delay: 0.4s; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1 class="dashboard-title">Manager Dashboard</h1>
            <p class="dashboard-subtitle">
                Welcome back, <span class="manager-name"><?= htmlspecialchars($profile['name']) ?></span>. Here's an overview of your team's performance.
            </p>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid">
            <!-- Total Team Members -->
            <div class="kpi-card">
                <div class="kpi-content">
                    <h3>Total Team Members</h3>
                    <p><?= $totalTeamMembers ?></p>
                </div>
                <div class="kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
            </div>

            <!-- Total Applications -->
            <div class="kpi-card">
                <div class="kpi-content">
                    <h3>Total Applications</h3>
                    <p><?= $totalTeamApplications ?></p>
                </div>
                <div class="kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14,2 14,8 20,8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10,9 9,9 8,9"/>
                    </svg>
                </div>
            </div>

            <!-- Total Loan Amount -->
            <div class="kpi-card">
                <div class="kpi-content">
                    <h3>Total Loan Amount</h3>
                    <p>₹<?= number_format($totalTeamLoanAmount / 100000, 1) ?>L</p>
                </div>
                <div class="kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="12" y1="1" x2="12" y2="23"/>
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                    </svg>
                </div>
            </div>

            <!-- Total Lookups -->
            <div class="kpi-card">
                <div class="kpi-content">
                    <h3>Monthly Lookups</h3>
                    <p><?= ($rcLookups + $cibilLookups) ?></p>
                </div>
                <div class="kpi-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="M21 21l-4.35-4.35"/>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <!-- Team Members Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h2 class="widget-title">My Team</h2>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($teamMembers)): ?>
                                <tr>
                                    <td colspan="2">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.653-.121-1.28-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.653.121-1.28.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </div>
                                            <h3>No Team Members</h3>
                                            <p>No team members have been assigned yet.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach (array_slice($teamMembers, 0, 5) as $member): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($member['name']) ?></td>
                                        <td><?= htmlspecialchars($member['email']) ?></td>
                                    </tr>
                                <?php endforeach; ?>

                                <?php if (count($teamMembers) > 5): ?>
                                    <?php foreach (array_slice($teamMembers, 5) as $member): ?>
                                        <tr class="hidden team-member-hidden">
                                            <td><?= htmlspecialchars($member['name']) ?></td>
                                            <td><?= htmlspecialchars($member['email']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <?php if (count($teamMembers) > 5): ?>
                        <button id="team-view-all-btn" class="view-all-btn">
                            View All Members (<?= count($teamMembers) ?>)
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Applications Widget -->
            <div class="widget">
                <div class="widget-header">
                    <h2 class="widget-title">Recent Team Applications</h2>
                    <a href="/pages/manager_applications.php" class="widget-link">View All</a>
                </div>
                <div class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Customer</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($applications)): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                                </svg>
                                            </div>
                                            <h3>No Recent Applications</h3>
                                            <p>Your team has not submitted any applications recently.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($applications as $app): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($app['employee_name']) ?></td>
                                        <td><?= htmlspecialchars($app['customer_name']) ?></td>
                                        <td>₹<?= number_format($app['loan_amount']) ?></td>
                                        <td><?= renderStatusBadge($app['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Team members view all functionality
            const viewAllBtn = document.getElementById('team-view-all-btn');
            if (viewAllBtn) {
                viewAllBtn.addEventListener('click', function() {
                    const hiddenMembers = document.querySelectorAll('.team-member-hidden');
                    const isExpanded = this.textContent.includes('View Less');
                    
                    hiddenMembers.forEach(member => {
                        member.classList.toggle('hidden');
                    });

                    if (isExpanded) {
                        this.textContent = 'View All Members (<?= count($teamMembers) ?>)';
                    } else {
                        this.textContent = 'View Less';
                    }
                });
            }

            // Add smooth scrolling for any anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>
