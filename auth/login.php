    <?php if (!empty($error)) : ?>
        <small class="login-error" role="alert" style="display:block; margin-top:6px; color:#C62828; font-size:11px; line-height:1.5;">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </small>
    <?php endif; ?>

</div>

            <!-- CAPTCHA ditambahkan tanpa mengubah struktur fitur login yang sudah ada -->
            <div class="form-group">
                <label for="captcha">Verifikasi Keamanan</label>
                <p style="margin:6px 0; font-weight:bold;">
                    <?php echo htmlspecialchars($_SESSION['captcha_login_pertanyaan'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <input
                    id="captcha"
                    type="number"
                    name="captcha"
                    placeholder="Masukkan hasil penjumlahan"
                    inputmode="numeric"
                    required
                >
            </div>

            <button type="submit" name="login" class="btn-login-submit">
                Login
            </button>
           <a href="../index.php" class="btn-login-submit btn-back-home">
                Kembali ke Beranda</a>

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
            <path
                d="M3 3l18 18"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />

            <path
                d="M10.6 10.6a2 2 0 0 0 2.8 2.8"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
            />

            <path
                d="M9.9 4.2A10.8 10.8 0 0 1 12 4c7 0 10 8 10 8a17.4 17.4 0 0 1-3.1 4.3"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <path
                d="M6.2 6.2C3.5 8.1 2 12 2 12s3 8 10 8c2 0 3.7-.6 5.2-1.5"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />
        `;

    } else {

        password.type = 'password';

        icon.innerHTML = `
            <path
                d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
            />

            <circle
                cx="12"
                cy="12"
                r="3"
                stroke="currentColor"
                stroke-width="1.8"
            />
        `;
    }
}
</script>

</body>
</html>
