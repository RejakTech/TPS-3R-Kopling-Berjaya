            case 'nama_lengkap':
                if (!value) {
                    message = 'Nama lengkap wajib diisi.';
                } else if (!/^[\p{L}\s.'-]+$/u.test(value)) {
                    message = 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, atau tanda hubung.';
                } else if (value.length > 150) {
                    message = 'Nama lengkap maksimal 150 karakter.';
                }
                break;

            case 'username':
                if (!value) {
                    message = 'Username wajib diisi.';
                } else if (value.length < 3 || value.length > 30) {
                    message = 'Username harus terdiri dari 3–30 karakter.';
                } else if (!/^[A-Za-z0-9_.]+$/.test(value)) {
                    message = 'Username hanya boleh berisi huruf, angka, titik, dan garis bawah (_).';
                }
                break;

            case 'email':
                if (value && (!input.validity.valid || value.length > 150)) {
                    message = 'Format email tidak valid atau terlalu panjang.';
                }
                break;

            case 'no_hp':
                if (value) {
                    const digits = value.replace(/\D/g, '').length;
                    if (!/^[0-9+\-\s()]+$/.test(value) || digits < 8 || digits > 15 || value.length > 20) {
                        message = 'Nomor HP harus berisi 8–15 angka.';
                    }
                }
                break;

            case 'alamat':
                if (value.length > 255) {
                    message = 'Alamat maksimal 255 karakter.';
                }
                break;

            case 'password':
                if (!input.value) {
                    message = 'Password wajib diisi.';
                } else if (input.value.length < 8) {
                    message = 'Password minimal 8 karakter.';
                }
                break;

            case 'konfirmasi_password':
                if (!input.value) {
                    message = 'Konfirmasi password wajib diisi.';
                } else if (input.value !== fields.password.value) {
                    message = 'Konfirmasi password tidak sama dengan password.';
                }
                break;
        }

        const errorElement = getOrCreateError(input);
        errorElement.textContent = message;
        input.classList.toggle('input-invalid', message !== '');
        input.setAttribute('aria-invalid', message !== 'true' && message !== '' ? 'true' : 'false');

        return message === '';
    }

    Object.values(fields).forEach(function (input) {
        input.addEventListener('blur', function () {
            validateField(input);
        });

        input.addEventListener('input', function () {
            if (input.classList.contains('input-invalid')) {
                validateField(input);
            }

            if (input.name === 'password' && fields.konfirmasi_password.value !== '') {
                validateField(fields.konfirmasi_password);
            }
        });
    });

    form.addEventListener('submit', function (event) {
        let valid = true;

        Object.values(fields).forEach(function (input) {
            if (!validateField(input)) {
                valid = false;
            }
        });

        if (!valid) {
            event.preventDefault();

            const firstInvalid = form.querySelector('.input-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }
        }
    });
});
</body>
</html>