<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>@yield('page-title', 'Dashboard') — SMP Absensi RFID</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}" />
    @stack('styles')
</head>

<body>
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    <div class="app-layout">
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
        <aside class="sidebar" id="sidebar">
            <a class="sidebar-brand" href="{{ route('dashboard') }}">
                <span class="brand-mark" aria-hidden="true">
                    <img src="{{ asset('images/logo-smp.jpg') }}" alt="" />
                </span>
                <span class="brand-copy">
                    <span class="brand-name">Absensi SMP</span>
                    <span class="brand-sub">Sistem Presensi RFID</span>
                </span>
            </a>

            <nav class="sidebar-nav" aria-label="Navigasi utama">
                <div class="sidebar-label">Menu utama</div>
                <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" @if(request()->routeIs('dashboard')) aria-current="page" @endif>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1V10Z" /></svg>
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('students.index') }}" class="sidebar-link {{ request()->routeIs('students.*') ? 'active' : '' }}" @if(request()->routeIs('students.*')) aria-current="page" @endif>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 5.5a3 3 0 0 1 0 5.8M17 14a5 5 0 0 1 4.5 5"/></svg>
                    <span>Data Siswa</span>
                </a>
                <a href="{{ route('attendances.index') }}" class="sidebar-link {{ request()->routeIs('attendances.*') ? 'active' : '' }}" @if(request()->routeIs('attendances.*')) aria-current="page" @endif>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M8 2v4m8-4v4M3 9h18m-13 5 2 2 4-4"/></svg>
                    <span>Presensi Harian</span>
                </a>
                <a href="{{ route('reports.index') }}" class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" @if(request()->routeIs('reports.*')) aria-current="page" @endif>
                    <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3h10l4 4v14H5z"/><path d="M14 3v5h5M8 12h8m-8 4h8m-8 4h5"/></svg>
                    <span>Rekap Absensi</span>
                </a>

                @if(auth()->user()->role === 'ADMIN')
                    <div class="sidebar-label">Administrasi</div>
                    <a href="{{ route('school-classes.index') }}" class="sidebar-link {{ request()->routeIs('school-classes.*') ? 'active' : '' }}" @if(request()->routeIs('school-classes.*')) aria-current="page" @endif>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V5l7-3 7 3v16M9 9h1m4 0h1m-6 4h1m4 0h1m-4 8v-4h4v4"/></svg>
                        <span>Data Kelas</span>
                    </a>
                    <a href="{{ route('users.index') }}" class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}" @if(request()->routeIs('users.*')) aria-current="page" @endif>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0m3-11v6m-3-3h6"/></svg>
                        <span>Manajemen User</span>
                    </a>

                    <div class="sidebar-label">Sistem perangkat</div>
                    <a href="{{ route('devices.index') }}" class="sidebar-link {{ request()->routeIs('devices.*') ? 'active' : '' }}" @if(request()->routeIs('devices.*')) aria-current="page" @endif>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="4" width="14" height="16" rx="2"/><path d="M9 8h6v6H9zM9 17h.01M12 17h.01M15 17h.01"/></svg>
                        <span>Perangkat ESP32</span>
                    </a>
                    <a href="{{ route('unknown-rfids.index') }}" class="sidebar-link {{ request()->routeIs('unknown-rfids.*') ? 'active' : '' }}" @if(request()->routeIs('unknown-rfids.*')) aria-current="page" @endif>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.6 2.6 0 1 1 4.4 1.9c-1.2 1-1.9 1.4-1.9 3m0 3h.01"/></svg>
                        <span>RFID Tidak Dikenal</span>
                    </a>

                    <div class="sidebar-label">Pengaturan</div>
                    <a href="{{ route('academic-years.index') }}" class="sidebar-link {{ request()->routeIs('academic-years.*') ? 'active' : '' }}" @if(request()->routeIs('academic-years.*')) aria-current="page" @endif>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18m-13 4h3m-3 3h3m3-3h3"/></svg>
                        <span>Tahun Ajaran</span>
                    </a>
                    <a href="{{ route('promotions.index') }}" class="sidebar-link {{ request()->routeIs('promotions.*') ? 'active' : '' }}" @if(request()->routeIs('promotions.*')) aria-current="page" @endif>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 19V5m-6 6 6-6 6 6M5 20h14"/></svg>
                        <span>Naik Kelas</span>
                    </a>
                    <a href="{{ route('school-calendars.index') }}" class="sidebar-link {{ request()->routeIs('school-calendars.*') ? 'active' : '' }}" @if(request()->routeIs('school-calendars.*')) aria-current="page" @endif>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4m8-4v4M3 10h18m3 5h.01M7 15h.01m4 0h.01m4 0h.01M7 18h.01m4 0h.01"/></svg>
                        <span>Kalender Libur</span>
                    </a>
                    <a href="{{ route('attendance-settings.index') }}" class="sidebar-link {{ request()->routeIs('attendance-settings.*') ? 'active' : '' }}" @if(request()->routeIs('attendance-settings.*')) aria-current="page" @endif>
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2m4-9 2-2"/></svg>
                        <span>Jam Absensi</span>
                    </a>
                @endif
            </nav>

            <div class="sidebar-account">
                <div class="account-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                <div class="account-copy">
                    <div class="user-name">{{ auth()->user()->name }}</div>
                    <div class="user-role">{{ auth()->user()->role === 'ADMIN' ? 'Administrator' : 'Wali Kelas' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn-logout" title="Keluar" aria-label="Keluar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 17l5-5-5-5m5 5H3m9-9h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg>
                    </button>
                </form>
            </div>
        </aside>

        <div class="main-wrapper">
            <header class="app-header">
                <button type="button" class="nav-toggle" id="sidebarToggle" aria-label="Buka menu" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="header-context">
                    <span class="header-kicker">SISTEM ABSENSI SEKOLAH</span>
                    <span class="header-date">{{ now(config('app.school_timezone'))->locale('id')->isoFormat('dddd, D MMMM Y') }}</span>
                </div>
                <div class="header-role">
                    <span class="status-dot"></span>
                    {{ auth()->user()->role === 'ADMIN' ? 'Administrator' : 'Wali Kelas' }}
                </div>
            </header>

            <main class="page-content" id="main-content" tabindex="-1">
                <div class="page-heading">
                    <div>
                        <div class="page-eyebrow">{{ auth()->user()->role === 'ADMIN' ? 'Panel administrator' : 'Panel wali kelas' }}</div>
                        <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
                    </div>
                    @hasSection('page-actions')
                        <div class="page-actions">@yield('page-actions')</div>
                    @endif
                </div>

                @if(session('success'))
                    <div class="alert alert-success" role="status" aria-live="polite"><span class="alert-mark" aria-hidden="true">✓</span><span>{{ session('success') }}</span></div>
                @endif
                @if(session('error') || $errors->has('error'))
                    <div class="alert alert-error" role="alert"><span class="alert-mark" aria-hidden="true">!</span><span>{{ session('error') ?? $errors->first('error') }}</span></div>
                @endif
                @if($errors->any() && !$errors->has('error'))
                    <div class="alert alert-error validation-summary" role="alert" aria-live="assertive" tabindex="-1">
                        <span class="alert-mark" aria-hidden="true">!</span>
                        <div>
                            <strong>Periksa kembali isian berikut:</strong>
                            <ul class="alert-list">
                                @foreach($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @yield('content')
            </main>
            {{-- <footer class="app-footer">SMP Absensi RFID <span>•</span> Sistem pencatatan kehadiran siswa</footer> --}}
        </div>
    </div>

    <script>
        (() => {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');
            const backdrop = document.getElementById('sidebarBackdrop');
            const sidebarScrollKey = 'absensi-sidebar-scroll';
            const pageScrollPrefix = 'absensi-page-scroll:';
            const restoreScrollKey = 'absensi-restore-scroll';
            const readStorage = key => {
                try { return window.sessionStorage.getItem(key); } catch { return null; }
            };
            const writeStorage = (key, value) => {
                try { window.sessionStorage.setItem(key, value); } catch {}
            };
            const removeStorage = key => {
                try { window.sessionStorage.removeItem(key); } catch {}
            };
            const pageKey = href => {
                const url = new URL(href, window.location.href);
                const query = [...url.searchParams.entries()]
                    .sort(([keyA, valueA], [keyB, valueB]) => keyA.localeCompare(keyB) || valueA.localeCompare(valueB));
                const search = new URLSearchParams(query).toString();
                return `${url.pathname}${search ? `?${search}` : ''}`;
            };
            const savePageScroll = () => {
                writeStorage(`${pageScrollPrefix}${pageKey(window.location.href)}`, String(window.scrollY));
            };

            const savedSidebarScroll = Number(readStorage(sidebarScrollKey));
            if (Number.isFinite(savedSidebarScroll)) sidebar.scrollTop = savedSidebarScroll;
            sidebar.addEventListener('scroll', () => writeStorage(sidebarScrollKey, String(sidebar.scrollTop)), { passive: true });

            const restoreTarget = readStorage(restoreScrollKey);
            if (restoreTarget === pageKey(window.location.href)) {
                const storedPageScroll = readStorage(`${pageScrollPrefix}${restoreTarget}`);
                const savedPageScroll = Number(storedPageScroll);
                removeStorage(restoreScrollKey);
                if (storedPageScroll !== null && Number.isFinite(savedPageScroll)) {
                    document.documentElement.classList.add('is-restoring-scroll');
                    requestAnimationFrame(() => {
                        window.scrollTo(0, savedPageScroll);
                        requestAnimationFrame(() => document.documentElement.classList.remove('is-restoring-scroll'));
                    });
                }
            }

            window.addEventListener('pagehide', savePageScroll);
            document.addEventListener('click', event => {
                const link = event.target.closest('a[href]');
                if (!link) return;

                savePageScroll();
                if (link.dataset.restoreScroll !== undefined && link.origin === window.location.origin &&
                    !link.target && event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey) {
                    writeStorage(restoreScrollKey, pageKey(link.href));
                }
            });

            const closeMenu = () => {
                sidebar.classList.remove('open');
                backdrop.classList.remove('visible');
                toggle.setAttribute('aria-expanded', 'false');
            };

            toggle.addEventListener('click', () => {
                const open = sidebar.classList.toggle('open');
                backdrop.classList.toggle('visible', open);
                toggle.setAttribute('aria-expanded', String(open));
            });
            backdrop.addEventListener('click', closeMenu);
            sidebar.querySelectorAll('.sidebar-link').forEach(link => link.addEventListener('click', closeMenu));
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeMenu();
            });

            const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            let lastModalTrigger = null;

            const openModal = (modal, trigger = document.activeElement) => {
                if (!modal) return;
                lastModalTrigger = trigger;
                modal.style.display = 'flex';
                document.body.classList.add('modal-open');
                const firstFocusable = modal.querySelector('[data-modal-autofocus]') || modal.querySelector(focusableSelector);
                (firstFocusable || modal).focus();
            };

            const closeModal = modal => {
                if (!modal) return;
                modal.style.display = 'none';
                const hasOpenModal = [...document.querySelectorAll('.app-modal')].some(item => item.style.display === 'flex');
                if (!hasOpenModal) {
                    document.body.classList.remove('modal-open');
                }
                if (lastModalTrigger?.isConnected) {
                    lastModalTrigger.setAttribute('aria-expanded', 'false');
                    lastModalTrigger.focus();
                }
            };

            document.querySelectorAll('.app-modal').forEach(modal => {
                modal.setAttribute('role', 'dialog');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('tabindex', '-1');
                const title = modal.querySelector('h1, h2, h3, h4');
                if (title) {
                    if (!title.id) title.id = `${modal.id}-title`;
                    modal.setAttribute('aria-labelledby', title.id);
                }
                modal.addEventListener('click', event => {
                    if (event.target === modal && modal.dataset.modalBackdropClose !== 'false') closeModal(modal);
                });
                modal.addEventListener('keydown', event => {
                    if (event.key !== 'Tab') return;
                    const focusable = [...modal.querySelectorAll(focusableSelector)].filter(item => item.offsetParent !== null);
                    if (!focusable.length) {
                        event.preventDefault();
                        modal.focus();
                        return;
                    }
                    const first = focusable[0];
                    const last = focusable[focusable.length - 1];
                    if (event.shiftKey && document.activeElement === first) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && document.activeElement === last) {
                        event.preventDefault();
                        first.focus();
                    }
                });
            });

            document.querySelectorAll('[data-modal-open]').forEach(button => {
                button.setAttribute('aria-haspopup', 'dialog');
                button.setAttribute('aria-controls', button.dataset.modalOpen);
                button.setAttribute('aria-expanded', 'false');
            });

            document.addEventListener('click', event => {
                const opener = event.target.closest('[data-modal-open]');
                if (opener) {
                    opener.setAttribute('aria-expanded', 'true');
                    openModal(document.getElementById(opener.dataset.modalOpen), opener);
                    return;
                }

                const closer = event.target.closest('[data-modal-close]');
                if (closer) closeModal(closer.closest('.app-modal'));
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') {
                    const openModalElement = [...document.querySelectorAll('.app-modal')].find(modal => modal.style.display === 'flex');
                    if (openModalElement) closeModal(openModalElement);
                }
            });

            window.openAppModal = (modalId, trigger = document.activeElement) => {
                const modal = document.getElementById(modalId);
                if (trigger instanceof HTMLElement && trigger.matches('button, a, [role="button"]')) {
                    trigger.setAttribute('aria-haspopup', 'dialog');
                    trigger.setAttribute('aria-controls', modalId);
                    trigger.setAttribute('aria-expanded', 'true');
                }
                openModal(modal, trigger);
            };
            window.closeAppModal = modalId => closeModal(document.getElementById(modalId));

            const validationSummary = document.querySelector('.validation-summary');
            if (validationSummary) validationSummary.focus();

            document.addEventListener('click', async event => {
                const copyButton = event.target.closest('[data-copy-text]');
                if (!copyButton) return;

                const textToCopy = copyButton.dataset.copyText;
                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(textToCopy);
                    } else {
                        const temporaryInput = document.createElement('textarea');
                        temporaryInput.value = textToCopy;
                        temporaryInput.setAttribute('readonly', '');
                        temporaryInput.style.position = 'fixed';
                        temporaryInput.style.opacity = '0';
                        document.body.appendChild(temporaryInput);
                        temporaryInput.select();
                        const copied = document.execCommand('copy');
                        temporaryInput.remove();
                        if (!copied) throw new Error('Clipboard copy failed');
                    }

                    const originalLabel = copyButton.dataset.originalLabel || copyButton.textContent.trim();
                    copyButton.dataset.originalLabel = originalLabel;
                    copyButton.textContent = 'Tersalin';
                    copyButton.setAttribute('aria-label', 'API Key berhasil disalin');
                    window.setTimeout(() => {
                        copyButton.textContent = originalLabel;
                        copyButton.setAttribute('aria-label', 'Salin API Key');
                    }, 1800);
                } catch {
                    copyButton.textContent = 'Gagal menyalin';
                    window.setTimeout(() => { copyButton.textContent = copyButton.dataset.originalLabel || 'Salin'; }, 1800);
                }
            });
        })();
    </script>
    @stack('scripts')
</body>

</html>
