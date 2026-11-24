            padding: 8px 10px;
            color: #C62828;
            background: #FFF1F1;
            border: 1px solid #F3C4C4;
            border-radius: 7px;
            font-size: 11px;
            line-height: 1.5;
        }
    </style>
</head>

<body>
<div class="login-container">
    <div class="login-box">

        <div class="login-logo">
            <img src="../assets/images/logo-tps3r.png" alt="Logo TPS 3R">
        </div>

        <h2>Selamat Datang!</h2>

        <p class="login-description">
            Silakan login untuk masuk ke Bank Sampah Digital.
        </p>

        <?php if (!empty($error)) : ?>
            <div class="login-error" role="alert">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Masukkan username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    autocomplete="username"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>

                <div class="password-wrapper">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Masukkan password"
                        autocomplete="current-password"
                        required
                    >

                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword()"
                        aria-label="Tampilkan password"
                    >
                        <svg id="eyeIcon" width="20" height="20" viewBox="0 0 24 24" fill="none">
                            <path
                                d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" name="login" class="btn-login-submit">
                Login
            </button>

            <a href="../index.php" class="btn-login-submit btn-back-home">
                Kembali ke Beranda
            </a>
        </form>

        <p class="register-link">
            Belum punya akun?
            <a href="register.php">Daftar sekarang</a>
        </p>
    </div>
</div>

<script>
function togglePassword() {
    const password = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');

    if (password.type === 'password') {
        password.type = 'text';

        icon.innerHTML = `
            <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M9.9 4.2A10.8 10.8 0 0 1 12 4c7 0 10 8 10 8a17.4 17.4 0 0 1-3.1 4.3"
                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M6.2 6.2C3.5 8.1 2 12 2 12s3 8 10 8c2 0 3.7-.6 5.2-1.5"
                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        `;
    } else {
        password.type = 'password';

        icon.innerHTML = `
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
        `;
    }
}
</script>
</body>
</html>