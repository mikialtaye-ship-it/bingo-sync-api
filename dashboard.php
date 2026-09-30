th { background: #f8fafc; color: #64748b; font-weight: 600; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 12px; font-weight: 600; background: #dcfce7; color: #15803d; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-gamepad"></i> ATD Control</div>
        <a href="dashboard.php" class="nav-link active"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a href="generate_balance.php" class="nav-link"><i class="fa-solid fa-paper-plane"></i> Transfer Balance</a>
        <a href="logout.php" class="nav-link logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
    <div class="main">
        <h2 style="margin-bottom: 20px;">System Overview</h2>
        <div class="metrics-grid">
            <div class="metric-card">
                <h4>Total Transferred</h4>
                <p>ETB <?= number_format($totalTopup, 2) ?></p>
            </div>
            <div class="metric-card">
                <h4>Pending Transfers</h4>
                <p><?= (int)$activeTransfers ?></p>
            </div>
        </div>

        <div class="table-card">
            <div class="card-header">Recent Transfer Activities</div>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Target Client</th>
                        <th>Status</th>
                        <th>Details</th>
                        <th>Amount</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentRecords)): ?>
                        <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 24px;">ምንም የተላለፈ መረጃ የለም።</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentRecords as $r): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($r['id']) ?></td>
                                <td><strong><?= htmlspecialchars($r['alias_name'] ?? '-') ?></strong></td>
                                <td><span class="badge">Success</span></td>
                                <td><?= htmlspecialchars($r['action_details']) ?></td>
                                <td>ETB <?= number_format((float)($r['topup_amount'] ?? 0), 2) ?></td>
                                <td><?= htmlspecialchars($r['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
