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
                } else if (!/^[A-Za-z0-9]+$/.test(value)) {
                    message = 'Username hanya boleh berisi huruf dan angka, tanpa simbol atau spasi.';
                }
                break;

            case 'email':
                if (value && (!input.validity.valid || value.length > 150)) {
                    message = 'Format email tidak valid atau terlalu panjang.';
                }
                break;

            case 'no_hp':
                if (!value) {
                    message = 'Nomor WhatsApp wajib diisi.';
                } else if (!/^(0[0-9]{9,14}|62[0-9]{8,13})$/.test(value)) {
                    message = 'Nomor WhatsApp harus 10–15 angka tanpa simbol/spasi, diawali 0 atau 62.';
                }
                break;

            case 'alamat':
                if (!value) {
                    message = 'Alamat lengkap wajib diisi.';
                } else if (value.length < 10 || value.length > 255) {
                    message = 'Alamat harus terdiri dari 10–255 karakter.';
                } else if (!/^[\p{L}0-9 ,./-]+$/u.test(value) || !/[\p{L}]{2}/u.test(value)) {
                    message = 'Masukkan alamat lengkap seperti format Google Maps, tanpa karakter khusus.';
                }
                break;

            case 'password':
                if (!input.value) {
                    message = 'Password wajib diisi.';
                } else if ([...input.value].length < 8) {
                    message = 'Password wajib minimal 8 karakter.';
                } else if (!/^[A-Z]/.test(input.value)) {
                    message = 'Karakter pertama password wajib huruf kapital (A–Z).';
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