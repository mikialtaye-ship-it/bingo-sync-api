.nav-link.active, .nav-link:hover { background-color: #1e293b; color: #fff; }
        .nav-link.logout { margin-top: auto; color: #f87171; }
        .main { flex: 1; padding: 40px; display: flex; justify-content: center; align-items: flex-start; }
        .card { width: 100%; max-width: 450px; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        h2 { font-size: 20px; margin-bottom: 20px; text-align: center; }
        label { font-size: 13px; font-weight: 600; color: #475569; display: block; margin-top: 15px; }
        input { width: 100%; padding: 12px; margin-top: 6px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; }
        button { width: 100%; padding: 14px; margin-top: 20px; background: var(--primary); color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; }
        .msg { padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; }
        .msg.success { background: #dcfce7; color: #15803d; }
        .msg.error { background: #fee2e2; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="brand"><i class="fa-solid fa-bolt"></i> ATD Control</div>
        <a href="dashboard.php" class="nav-link"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
        <a href="generate_balance.php" class="nav-link active"><i class="fa-solid fa-paper-plane"></i> Transfer Balance</a>
        <a href="logout.php" class="nav-link logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
    <div class="main">
        <div class="card">
            <h2>Transfer Balance to Client</h2>
            <?php if (!empty($message)): ?>
                <div class="msg <?= $statusClass ?>"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>
            <form method="POST">
                <label>የተጠቃሚ ስም (Client Username)</label>
                <input type="text" name="topup_username" value="adis" placeholder="Username" required>
                
                <label>የገንዘብ መጠን (Amount)</label>
                <input type="number" name="amount" value="500" step="0.01" min="1" placeholder="Amount in ETB" required>
                
                <button type="submit"><i class="fa-solid fa-paper-plane"></i> አስተላልፍ (Transfer)</button>
            </form>
        </div>
    </div>
</body>
</html>
