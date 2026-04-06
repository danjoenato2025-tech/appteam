
<!DOCTYPE html>
<html lang="en">
<<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management – Sneat</title>
   <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <link rel="stylesheet" href="css/style.css">
    <style>

    </style>
    </head>

    <body>

<?php include('partials/sidebar.php'); ?>
        <!-- ══════════════════════════════════════════
     SIDEBAR



     PAGE WRAPPER
══════════════════════════════════════════ -->
        <div class="page-wrapper">

            <!-- Topbar -->
            <header class="topbar">
                <div class="topbar-search">
                    <i class="fa fa-search" style="color:var(--text-light);font-size:13px;"></i>
                    <input type="text" placeholder="Search (CTRL + K)">
                </div>
                <div class="topbar-spacer"></div>
                <div style="display:flex;align-items:center;gap:6px;">
                    <div class="topbar-icon"><i class="fa fa-globe"></i></div>
                    <div class="topbar-icon"><i class="fa fa-sun"></i></div>
                    <div class="topbar-icon"><i class="fa-solid fa-table-cells"></i></div>
                    <div class="topbar-icon"><i class="fa fa-bell"></i></div>
                    <div class="avatar-top">JD</div>
                </div>
            </header>

            <main class="main">

                <!-- ════════════════════════════════════════
         PAGE: USER LIST
    ════════════════════════════════════════ -->
                <div class="page-section active" id="page-list">
                    <div class="breadcrumb">
                        <a href="#">Home</a>
                        <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                        <a href="#">User Management</a>
                        <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                        <span style="color:var(--text-mid);">User List</span>
                    </div>

                    <!-- Stat Cards -->
                    <div class="stat-grid">
                        <div class="stat-card">
                            <div>
                                <div class="stat-label">Session</div>
                                <div class="stat-value">21,459 <span class="stat-change up">(+29%)</span></div>
                                <div class="stat-sub">Total Users</div>
                            </div>
                            <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <div class="stat-label">Paid Users</div>
                                <div class="stat-value">4,567 <span class="stat-change up">(+18%)</span></div>
                                <div class="stat-sub">Last week analytics</div>
                            </div>
                            <div class="stat-icon pink"><i class="fa-solid fa-user-plus"></i></div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <div class="stat-label">Active Users</div>
                                <div class="stat-value">19,860 <span class="stat-change down">(-14%)</span></div>
                                <div class="stat-sub">Last week analytics</div>
                            </div>
                            <div class="stat-icon green"><i class="fa-solid fa-user-check"></i></div>
                        </div>
                        <div class="stat-card">
                            <div>
                                <div class="stat-label">Pending Users</div>
                                <div class="stat-value">237 <span class="stat-change up">(+42%)</span></div>
                                <div class="stat-sub">Last week analytics</div>
                            </div>
                            <div class="stat-icon orange"><i class="fa-solid fa-user-clock"></i></div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="filter-card">
                        <div class="filter-title">Search Filters</div>
                        <div class="filter-row">
                            <div class="filter-select">Select Role <i class="fa fa-chevron-down"
                                    style="font-size:11px;"></i></div>
                            <div class="filter-select">Select Plan <i class="fa fa-chevron-down"
                                    style="font-size:11px;"></i></div>
                            <div class="filter-select">Select Status <i class="fa fa-chevron-down"
                                    style="font-size:11px;"></i></div>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-card">
                        <div class="table-toolbar">
                            <div class="rows-select">10 <i class="fa fa-chevron-down" style="font-size:10px;"></i></div>
                            <div class="toolbar-spacer"></div>
                            <div class="search-input-wrap">
                                <i class="fa fa-search" style="color:var(--text-light);font-size:12px;"></i>
                                <input type="text" placeholder="Search User" style="width:180px;">
                            </div>
                            <button class="btn btn-outline"><i class="fa fa-download"></i> Export</button>
                            <button class="btn btn-primary"><i class="fa fa-plus"></i> Add New User</button>
                        </div>
                        <table>
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="cb"></th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Plan</th>
                                    <th>Billing</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="userTableBody"></tbody>
                        </table>
                        <div class="pagination-row">
                            <div class="page-info">Showing 1 to 10 of 50 entries</div>
                            <div class="page-btns">
                                <button class="page-btn disabled"><i class="fa fa-angles-left"></i></button>
                                <button class="page-btn disabled"><i class="fa fa-angle-left"></i></button>
                                <button class="page-btn active">1</button>
                                <button class="page-btn">2</button>
                                <button class="page-btn">3</button>
                                <button class="page-btn">4</button>
                                <button class="page-btn">5</button>
                                <button class="page-btn"><i class="fa fa-angle-right"></i></button>
                                <button class="page-btn"><i class="fa fa-angles-right"></i></button>
                            </div>
                        </div>
                    </div>
                </div><!-- /page-list -->

                <!-- ════════════════════════════════════════
         PAGE: USER VIEW
    ════════════════════════════════════════ -->
                <div class="page-section" id="page-view">
                    <div class="breadcrumb">
                        <a href="#" onclick="showPage('list');return false;">Home</a>
                        <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                        <a href="#" onclick="showPage('list');return false;">User Management</a>
                        <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                        <a href="#" onclick="showPage('list');return false;">Users</a>
                        <span><i class="fa fa-chevron-right" style="font-size:9px;"></i></span>
                        <span style="color:var(--text-mid);">View</span>
                    </div>

                    <div class="view-grid">
                        <!-- LEFT -->
                        <div>
                            <div class="profile-card">
                                <div class="profile-photo-placeholder">VM</div>
                                <div class="profile-name">Violet Mendoza</div>
                                <div class="profile-role-badge">Author</div>
                                <div class="profile-stats">
                                    <div class="stat-box">
                                        <div class="stat-box-icon purple"><i class="fa-solid fa-check"></i></div>
                                        <div class="stat-box-val">1.23k</div>
                                        <div class="stat-box-lbl">Task Done</div>
                                    </div>
                                    <div class="stat-box">
                                        <div class="stat-box-icon blue"><i class="fa-solid fa-diagram-project"></i>
                                        </div>
                                        <div class="stat-box-val">568</div>
                                        <div class="stat-box-lbl">Project Done</div>
                                    </div>
                                </div>
                            </div>

                            <div class="details-card">
                                <div class="details-title">Details</div>
                                <div class="detail-row"><span class="detail-label">Username:</span><span
                                        class="detail-value">@violet.dev</span></div>
                                <div class="detail-row"><span class="detail-label">Email:</span><span
                                        class="detail-value">vafgot@vultukir.org</span></div>
                                <div class="detail-row"><span class="detail-label">Status:</span><span
                                        class="badge-status">Active</span></div>
                                <div class="detail-row"><span class="detail-label">Role:</span><span
                                        class="detail-value">Author</span></div>
                                <div class="detail-row"><span class="detail-label">Tax id:</span><span
                                        class="detail-value">Tax-8965</span></div>
                                <div class="detail-row"><span class="detail-label">Contact:</span><span
                                        class="detail-value">(123) 456-7890</span></div>
                                <div class="detail-row"><span class="detail-label">Languages:</span><span
                                        class="detail-value">French</span></div>
                                <div class="detail-row"><span class="detail-label">Country:</span><span
                                        class="detail-value">England</span></div>
                                <div class="btn-row">
                                    <button class="btn btn-primary">Edit</button>
                                    <button class="btn btn-danger-outline">Suspend</button>
                                </div>
                            </div>

                            <div class="plan-card">
                                <div class="plan-badge">Standard</div>
                                <div class="plan-price"><sup>$</sup>99<span>/month</span></div>
                                <ul class="plan-features">
                                    <li>10 Users</li>
                                    <li>Up to 10 GB storage</li>
                                    <li>Basic Support</li>
                                </ul>
                                <div class="plan-days-label"><span>Days</span><span>26 of 30 Days</span></div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width:87%;"></div>
                                </div>
                                <div class="plan-days-remaining">4 days remaining</div>
                                <button class="btn-upgrade">Upgrade Plan</button>
                            </div>
                        </div>

                        <!-- RIGHT -->
                        <div class="right-col">
                            <div class="tabs-card">
                                <div class="tabs-header">
                                    <button class="tab-btn active" id="tab-account" onclick="switchTab('account')"><i
                                            class="fa-solid fa-user"></i> Account</button>
                                    <button class="tab-btn" id="tab-security" onclick="switchTab('security')"><i
                                            class="fa-solid fa-lock"></i> Security</button>
                                    <button class="tab-btn" id="tab-billing" onclick="switchTab('billing')"><i
                                            class="fa-regular fa-credit-card"></i> Billing &amp; Plans</button>
                                    <button class="tab-btn" id="tab-notifications"
                                        onclick="switchTab('notifications')"><i class="fa-regular fa-bell"></i>
                                        Notifications</button>
                                    <button class="tab-btn" id="tab-connections" onclick="switchTab('connections')"><i
                                            class="fa-solid fa-link"></i> Connections</button>
                                </div>
                                <div class="tab-content">

                                    <!-- ACCOUNT -->
                                    <div class="tab-pane active" id="pane-account">
                                        <div class="section-title">Projects List</div>
                                        <div class="table-toolbar-inner">
                                            <div class="rows-select">7 <i class="fa fa-chevron-down"
                                                    style="font-size:10px;"></i></div>
                                            <div class="toolbar-spacer"></div>
                                            <div class="search-input-wrap">
                                                <i class="fa fa-search"
                                                    style="color:var(--text-light);font-size:12px;"></i>
                                                <input type="text" placeholder="Search Project">
                                            </div>
                                        </div>
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th><input type="checkbox" class="cb"></th>
                                                    <th>Project</th>
                                                    <th>Leader</th>
                                                    <th>Team</th>
                                                    <th>Progress</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="projectsBody"></tbody>
                                        </table>
                                        <div class="pagination-row">
                                            <div class="page-info">Showing 1 to 7 of 10 entries</div>
                                            <div class="page-btns">
                                                <button class="page-btn disabled"><i
                                                        class="fa fa-angles-left"></i></button>
                                                <button class="page-btn disabled"><i
                                                        class="fa fa-angle-left"></i></button>
                                                <button class="page-btn active">1</button>
                                                <button class="page-btn">2</button>
                                                <button class="page-btn"><i class="fa fa-angle-right"></i></button>
                                                <button class="page-btn"><i class="fa fa-angles-right"></i></button>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- SECURITY -->
                                    <div class="tab-pane" id="pane-security">
                                        <div class="security-section">
                                            <div class="security-subtitle">Change Password</div>
                                            <div class="security-desc">To change your password, please fill in the
                                                fields below.</div>
                                            <div class="form-group"><label class="form-label">Current
                                                    Password</label><input class="form-input" type="password"
                                                    placeholder="············"></div>
                                            <div class="form-grid-2">
                                                <div class="form-group"><label class="form-label">New
                                                        Password</label><input class="form-input" type="password"
                                                        placeholder="············"></div>
                                                <div class="form-group"><label class="form-label">Confirm New
                                                        Password</label><input class="form-input" type="password"
                                                        placeholder="············"></div>
                                            </div>
                                            <button class="btn btn-primary" style="width:auto;padding:10px 24px;">Change
                                                Password</button>
                                        </div>
                                        <div class="security-section">
                                            <div class="security-subtitle">Two-step verification</div>
                                            <div class="security-desc">Keep your account secure with authentication
                                                step.</div>
                                            <div class="two-fa-row">
                                                <div>
                                                    <div style="font-size:13.5px;font-weight:500;">SMS</div>
                                                    <div style="font-size:12.5px;color:var(--text-light);">+1 (234)
                                                        567-8900</div>
                                                </div>
                                                <label class="toggle"><input type="checkbox" checked><span
                                                        class="toggle-slider"></span></label>
                                            </div>
                                            <div class="two-fa-row" style="margin-top:10px;">
                                                <div>
                                                    <div style="font-size:13.5px;font-weight:500;">Authenticator App
                                                    </div>
                                                    <div style="font-size:12.5px;color:var(--text-light);">Google
                                                        Authenticator</div>
                                                </div>
                                                <label class="toggle"><input type="checkbox"><span
                                                        class="toggle-slider"></span></label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- BILLING -->
                                    <div class="tab-pane" id="pane-billing">
                                        <div class="section-title">Current Plan</div>
                                        <div class="billing-plan-box">
                                            <div class="billing-plan-header">
                                                <div>
                                                    <div class="billing-plan-name">Standard Plan</div>
                                                    <div style="font-size:12.5px;color:var(--text-light);">A simple
                                                        start for everyone</div>
                                                </div>
                                                <div class="billing-plan-price">$99<span
                                                        style="font-size:14px;font-weight:400;color:var(--text-light);">/mo</span>
                                                </div>
                                            </div>
                                            <div class="billing-features">
                                                <div class="billing-feature"><i class="fa fa-check"></i> 10 Users</div>
                                                <div class="billing-feature"><i class="fa fa-check"></i> 10 GB Storage
                                                </div>
                                                <div class="billing-feature"><i class="fa fa-check"></i> Basic Support
                                                </div>
                                            </div>
                                            <div style="display:flex;gap:10px;">
                                                <button class="btn btn-primary btn-sm">Upgrade Plan</button>
                                                <button class="btn btn-danger-outline btn-sm">Cancel
                                                    Subscription</button>
                                            </div>
                                        </div>
                                        <div class="section-title" style="margin-top:22px;">Payment Methods</div>
                                        <div class="payment-method" style="margin-bottom:10px;">
                                            <div class="card-info">
                                                <div class="card-icon"><i class="fa-brands fa-cc-visa"></i></div>
                                                <div>
                                                    <div style="font-weight:500;font-size:13.5px;">**** **** **** 4291
                                                    </div>
                                                    <div style="font-size:12px;color:var(--text-light);">Expires 08/2026
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="display:flex;gap:8px;">
                                                <button class="btn btn-outline btn-sm">Edit</button>
                                                <button class="btn btn-danger-outline btn-sm">Delete</button>
                                            </div>
                                        </div>
                                        <button class="btn btn-outline" style="margin-top:12px;"><i
                                                class="fa fa-plus"></i> Add Payment Method</button>
                                    </div>

                                    <!-- NOTIFICATIONS -->
                                    <div class="tab-pane" id="pane-notifications">
                                        <div class="section-title">Notifications</div>
                                        <div class="notif-group">
                                            <div class="notif-group-title">Activity</div>
                                            <div class="notif-row"><span class="notif-label">Comments on your
                                                    posts</span>
                                                <div class="notif-checks"><label class="notif-check"><input
                                                            type="checkbox" checked> Email</label><label
                                                        class="notif-check"><input type="checkbox" checked>
                                                        Browser</label><label class="notif-check"><input
                                                            type="checkbox"> App</label></div>
                                            </div>
                                            <div class="notif-row"><span class="notif-label">Answers on your forum
                                                    questions</span>
                                                <div class="notif-checks"><label class="notif-check"><input
                                                            type="checkbox"> Email</label><label
                                                        class="notif-check"><input type="checkbox" checked>
                                                        Browser</label><label class="notif-check"><input type="checkbox"
                                                            checked> App</label></div>
                                            </div>
                                            <div class="notif-row"><span class="notif-label">New features</span>
                                                <div class="notif-checks"><label class="notif-check"><input
                                                            type="checkbox" checked> Email</label><label
                                                        class="notif-check"><input type="checkbox">
                                                        Browser</label><label class="notif-check"><input
                                                            type="checkbox"> App</label></div>
                                            </div>
                                        </div>
                                        <div class="notif-group">
                                            <div class="notif-group-title">Application</div>
                                            <div class="notif-row"><span class="notif-label">News and
                                                    announcements</span>
                                                <div class="notif-checks"><label class="notif-check"><input
                                                            type="checkbox" checked> Email</label><label
                                                        class="notif-check"><input type="checkbox" checked>
                                                        Browser</label><label class="notif-check"><input type="checkbox"
                                                            checked> App</label></div>
                                            </div>
                                            <div class="notif-row"><span class="notif-label">Weekly product
                                                    updates</span>
                                                <div class="notif-checks"><label class="notif-check"><input
                                                            type="checkbox"> Email</label><label
                                                        class="notif-check"><input type="checkbox" checked>
                                                        Browser</label><label class="notif-check"><input
                                                            type="checkbox"> App</label></div>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary" style="width:auto;padding:10px 24px;">Save
                                            Changes</button>
                                    </div>

                                    <!-- CONNECTIONS -->
                                    <div class="tab-pane" id="pane-connections">
                                        <div class="section-title">Connected Accounts</div>
                                        <div class="connection-row">
                                            <div class="connection-info">
                                                <div class="connection-icon" style="background:#e8f0fe;color:#4285f4;">
                                                    <i class="fa-brands fa-google"></i>
                                                </div>
                                                <div>
                                                    <div class="connection-name">Google</div>
                                                    <div class="connection-handle">violet.mendoza@gmail.com</div>
                                                </div>
                                            </div><button class="btn-connect btn-connected"><i class="fa fa-check"></i>
                                                Connected</button>
                                        </div>
                                        <div class="connection-row">
                                            <div class="connection-info">
                                                <div class="connection-icon" style="background:#e8f4fd;color:#1da1f2;">
                                                    <i class="fa-brands fa-twitter"></i>
                                                </div>
                                                <div>
                                                    <div class="connection-name">Twitter</div>
                                                    <div class="connection-handle">@violet_dev</div>
                                                </div>
                                            </div><button class="btn-connect btn-connected"><i class="fa fa-check"></i>
                                                Connected</button>
                                        </div>
                                        <div class="connection-row">
                                            <div class="connection-info">
                                                <div class="connection-icon" style="background:#f0f0f0;color:#333;"><i
                                                        class="fa-brands fa-github"></i></div>
                                                <div>
                                                    <div class="connection-name">GitHub</div>
                                                    <div class="connection-handle">Not Connected</div>
                                                </div>
                                            </div><button class="btn-connect"><i class="fa fa-plus"></i>
                                                Connect</button>
                                        </div>
                                        <div class="connection-row">
                                            <div class="connection-info">
                                                <div class="connection-icon" style="background:#e8f5e9;color:#0a66c2;">
                                                    <i class="fa-brands fa-linkedin"></i>
                                                </div>
                                                <div>
                                                    <div class="connection-name">LinkedIn</div>
                                                    <div class="connection-handle">Not Connected</div>
                                                </div>
                                            </div><button class="btn-connect"><i class="fa fa-plus"></i>
                                                Connect</button>
                                        </div>
                                        <div class="connection-row">
                                            <div class="connection-info">
                                                <div class="connection-icon" style="background:#fce4ec;color:#e1306c;">
                                                    <i class="fa-brands fa-instagram"></i>
                                                </div>
                                                <div>
                                                    <div class="connection-name">Instagram</div>
                                                    <div class="connection-handle">@violet.dev</div>
                                                </div>
                                            </div><button class="btn-connect btn-connected"><i class="fa fa-check"></i>
                                                Connected</button>
                                        </div>
                                    </div>

                                </div>
                            </div>

                            <!-- Timeline -->
                            <div class="timeline-card">
                                <div class="section-title">User Activity Timeline</div>
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <div class="timeline-dot blue"></div>
                                        <div class="timeline-header">
                                            <div class="timeline-title">12 Invoices have been paid</div>
                                            <div class="timeline-time">12 min ago</div>
                                        </div>
                                        <div class="timeline-desc">Invoices have been paid to the company</div>
                                        <a href="#" class="timeline-attachment"><i class="fa fa-file-pdf"
                                                style="color:var(--red);"></i> invoices.pdf</a>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot green"></div>
                                        <div class="timeline-header">
                                            <div class="timeline-title">Client Meeting</div>
                                            <div class="timeline-time">45 min ago</div>
                                        </div>
                                        <div class="timeline-desc">Project meeting with john @10:15am</div>
                                        <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                                            <div
                                                style="width:32px;height:32px;border-radius:50%;background:#a8b9f8;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;color:#fff;">
                                                LM</div>
                                            <div>
                                                <div style="font-weight:500;font-size:13px;">Lester McCarthy (Client)
                                                </div>
                                                <div style="font-size:12px;color:var(--text-light);">CEO of
                                                    ThemeSelection</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="timeline-item">
                                        <div class="timeline-dot teal"></div>
                                        <div class="timeline-header">
                                            <div class="timeline-title">Create a new project for client</div>
                                            <div class="timeline-time">2 Day Ago</div>
                                        </div>
                                        <div class="timeline-desc">6 team members in a project</div>
                                        <div style="display:flex;align-items:center;margin-top:8px;">
                                            <div
                                                style="width:30px;height:30px;border-radius:50%;background:#696cff;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#fff;border:2px solid #fff;">
                                                A</div>
                                            <div
                                                style="width:30px;height:30px;border-radius:50%;background:#28c76f;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#fff;border:2px solid #fff;margin-left:-6px;">
                                                B</div>
                                            <div
                                                style="width:30px;height:30px;border-radius:50%;background:#ff9f43;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:#fff;border:2px solid #fff;margin-left:-6px;">
                                                C</div>
                                            <div class="member-more">+3</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div><!-- /page-view -->

            </main>
        </div>

        <script>
            /* ── DATA ─────────────────────────────────────────── */
            const users = [
                { name: 'Zsazsa McCleverty', email: 'zmcclevertye@soundcloud.com', role: 'Maintainer', roleIcon: 'fa-user-shield', plan: 'Enterprise', billing: 'Auto Debit', status: 'active', color: '#a8b9f8' },
                { name: 'Yoko Pottie', email: 'ypottiec@privacy.gov.au', role: 'Subscriber', roleIcon: 'fa-bookmark', plan: 'Basic', billing: 'Auto Debit', status: 'inactive', color: '#ffb347' },
                { name: 'Wesley Burland', email: 'wburlandj@uiuc.edu', role: 'Editor', roleIcon: 'fa-pen', plan: 'Team', billing: 'Auto Debit', status: 'inactive', color: '#f4a2a2' },
                { name: 'Vladamir Koschek', email: 'vkoschek17@abc.net.au', role: 'Author', roleIcon: 'fa-feather', plan: 'Team', billing: 'Manual – Paypal', status: 'active', color: '#6ecf8b' },
                { name: 'Tyne Widmore', email: 'twidmore12@bravesites.com', role: 'Subscriber', roleIcon: 'fa-bookmark', plan: 'Team', billing: 'Manual – Cash', status: 'pending', color: '#c2c2c2' },
                { name: 'Travus Bruntjen', email: 'lbruntjeni@sitemeter.com', role: 'Admin', roleIcon: 'fa-terminal', plan: 'Enterprise', billing: 'Manual – Cash', status: 'active', color: '#f4a2a2' },
                { name: 'Stu Delamaine', email: 'sdelamainek@who.int', role: 'Author', roleIcon: 'fa-feather', plan: 'Basic', billing: 'Auto Debit', status: 'pending', color: '#a8b9f8' },
                { name: 'Saunder Offner', email: 'soffner19@mac.com', role: 'Maintainer', roleIcon: 'fa-user-shield', plan: 'Enterprise', billing: 'Auto Debit', status: 'pending', color: '#ffb347' },
                { name: 'Stephen MacGilfoyle', email: 'smacgilfoyley@bigcartel.com', role: 'Maintainer', roleIcon: 'fa-user-shield', plan: 'Company', billing: 'Manual – Paypal', status: 'pending', color: '#c2c2c2' },
                { name: 'Skip Hebblethwaite', email: 'shebblethwaite10@arizona.edu', role: 'Admin', roleIcon: 'fa-terminal', plan: 'Company', billing: 'Manual – Cash', status: 'inactive', color: '#f4a2a2' },
            ];

            const statusBadge = {
                active: '<span class="badge active">Active</span>',
                inactive: '<span class="badge inactive">Inactive</span>',
                pending: '<span class="badge pending">Pending</span>',
            };

            const tbody = document.getElementById('userTableBody');
            users.forEach(u => {
                const initials = u.name.split(' ').map(n => n[0]).join('').slice(0, 2);
                tbody.innerHTML += `
  <tr>
    <td><input type="checkbox" class="cb"></td>
    <td>
      <div class="user-cell">
        <div class="user-avatar" style="background:${u.color}">${initials}</div>
        <div>
          <div class="user-name" onclick="openUserView()">${u.name}</div>
          <div class="user-email">${u.email}</div>
        </div>
      </div>
    </td>
    <td><div class="role-cell"><i class="fa-solid ${u.roleIcon}" style="font-size:13px;"></i> ${u.role}</div></td>
    <td style="color:var(--text-mid);font-size:13.5px;">${u.plan}</td>
    <td style="color:var(--text-mid);font-size:13.5px;">${u.billing}</td>
    <td>${statusBadge[u.status]}</td>
    <td>
      <div class="action-cell">
        <button class="action-btn" title="Delete"><i class="fa fa-trash"></i></button>
        <button class="action-btn" title="View" onclick="openUserView()"><i class="fa fa-eye"></i></button>
        <button class="action-btn" title="More"><i class="fa fa-ellipsis-vertical"></i></button>
      </div>
    </td>
  </tr>`;
            });

            const projects = [
                { icon: '🔍', bg: '#e8f0fe', name: 'Website SEO', date: '10 May 2021', leader: 'Eileen', pct: 38, team: ['#696cff', '#28c76f', '#ff9f43'], extra: 4 },
                { icon: '🌐', bg: '#e8f5e9', name: 'Social Banners', date: '03 Jan 2021', leader: 'Owen', pct: 45, team: ['#a8b9f8', '#f4a2a2', '#6ecf8b'], extra: 2 },
                { icon: '💎', bg: '#fff8e1', name: 'Logo Designs', date: '12 Aug 2021', leader: 'Keith', pct: 92, team: ['#696cff', '#ff9f43', '#28c76f'], extra: 1 },
                { icon: '📱', bg: '#fce4ec', name: 'IOS App Design', date: '19 Apr 2021', leader: 'Merline', pct: 56, team: ['#a8b9f8', '#696cff', '#f4a2a2'], extra: 1 },
                { icon: '🎨', bg: '#f3e5f5', name: 'Figma Dashboards', date: '08 Apr 2021', leader: 'Harmonia', pct: 25, team: ['#28c76f', '#ff9f43', '#696cff'], extra: 0 },
                { icon: '⛓', bg: '#e8f0fe', name: 'Crypto Admin', date: '29 Sept 2021', leader: 'Allyson', pct: 36, team: ['#a8b9f8', '#f4a2a2', '#6ecf8b'], extra: 1 },
                { icon: '🌍', bg: '#e8f5e9', name: 'Create Website', date: '20 Mar 2021', leader: 'Georgie', pct: 72, team: ['#696cff', '#ff9f43', '#28c76f'], extra: 3 },
            ];

            const pb = document.getElementById('projectsBody');
            projects.forEach(p => {
                const teamHTML = p.team.map(c => `<div style="width:26px;height:26px;border-radius:50%;background:${c};border:2px solid #fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;color:#fff;margin-left:-6px;"></div>`).join('');
                const extra = p.extra > 0 ? `<div style="width:26px;height:26px;border-radius:50%;background:#e8e7ea;display:inline-flex;align-items:center;justify-content:center;font-size:10px;color:var(--text-mid);margin-left:-6px;">+${p.extra}</div>` : '';
                pb.innerHTML += `
  <tr>
    <td><input type="checkbox" class="cb"></td>
    <td><div class="project-cell"><div class="project-icon" style="background:${p.bg}">${p.icon}</div><div><div class="project-name">${p.name}</div><div class="project-date">${p.date}</div></div></div></td>
    <td style="font-size:13.5px;color:var(--text-mid);">${p.leader}</td>
    <td><div style="display:flex;align-items:center;">${teamHTML}${extra}</div></td>
    <td><div class="progress-cell"><div class="progress-bar-sm"><div class="progress-fill-sm" style="width:${p.pct}%;"></div></div><span class="progress-pct">${p.pct}%</span></div></td>
    <td><button class="action-btn"><i class="fa fa-ellipsis-vertical"></i></button></td>
  </tr>`;
            });

            /* ── NAVIGATION ───────────────────────────────────── */
            function showPage(page) {
                document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
                document.getElementById('page-' + page).classList.add('active');

                // Sidebar active states
                document.querySelectorAll('.nav-sub-link').forEach(l => l.classList.remove('active'));
                if (page === 'list') {
                    document.getElementById('nav-list').classList.add('active');
                    // dim View label
                    document.getElementById('view-dot').style.background = '#c9c8cd';
                    document.getElementById('view-label').style.color = '';
                    document.getElementById('view-label').style.fontWeight = '';
                }
            }

            function openUserView() {
                document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
                document.getElementById('page-view').classList.add('active');
                // Open view accordion
                document.getElementById('viewAcc').classList.add('open');
                // Activate Account tab by default
                showViewTab('account');
            }

            function showViewTab(name) {
                // Ensure view page is shown
                if (!document.getElementById('page-view').classList.contains('active')) {
                    document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
                    document.getElementById('page-view').classList.add('active');
                }
                // Tab buttons
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.getElementById('tab-' + name).classList.add('active');
                // Tab panes
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                document.getElementById('pane-' + name).classList.add('active');
                // Sidebar nav sub-links
                document.querySelectorAll('#viewAcc .nav-sub-link').forEach(l => l.classList.remove('active'));
                const map = { account: 'nav-account', security: 'nav-security', billing: 'nav-billing', notifications: 'nav-notifications', connections: 'nav-connections' };
                if (map[name]) document.getElementById(map[name]).classList.add('active');
                // Highlight View parent
                document.getElementById('view-dot').style.background = 'var(--primary)';
                document.getElementById('view-label').style.color = 'var(--primary)';
                document.getElementById('view-label').style.fontWeight = '500';
                // Deactivate List
                document.getElementById('nav-list').classList.remove('active');
                // Open view accordion
                document.getElementById('viewAcc').classList.add('open');
            }

            function switchTab(name) { showViewTab(name); }

            function toggleAcc(id) {
                document.getElementById(id).classList.toggle('open');
            }

            // Pagination buttons
            document.querySelectorAll('.page-btn:not(.disabled)').forEach(btn => {
                btn.addEventListener('click', function () {
                    if (!this.querySelector('i')) {
                        this.closest('.page-btns').querySelectorAll('.page-btn').forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                    }
                });
            });
        </script>
    </body>

</html>