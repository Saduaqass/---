<!DOCTYPE html>
<html lang="kk">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f4f5f0">
    <title>Әкімшіге кіру — Азық-түлік дүкені</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <main class="shell">
        <section class="login-panel">
            <div class="login-art">
                <a class="brand light-brand" href="http://localhost:8000/"><span class="brand-mark">✿</span><span>Азық-түлік<small>БАСҚАРУ ОРТАЛЫҒЫ</small></span></a>
                <div class="login-art-copy"><span class="art-tag">STORE OPERATIONS</span><h2>Дүкеніңізді бір жерден басқарыңыз.</h2><p>Тауарлар, қойма және тапсырыстар — барлығы осында.</p></div>
                <div class="art-decoration" aria-hidden="true"><span>✳</span><span>✿</span><span>✳</span></div>
                <span class="login-art-footer">КҮН САЙЫН БАЛҒЫН · 2026</span>
            </div>
            <div class="login-card">
                <p class="eyebrow">Қош келдіңіз</p>
                <h1>Әкімшіге кіру</h1>
                <p class="muted">Жалғастыру үшін құпиясөзіңізді енгізіңіз.</p>
                <form id="loginForm">
                    <label for="loginPassword">Құпиясөз</label>
                    <input id="loginPassword" name="password" type="password" autocomplete="current-password" placeholder="Құпиясөзді енгізіңіз" required autofocus>
                    <button class="button primary" type="submit">Панельге кіру <span aria-hidden="true">→</span></button>
                </form>
                <p class="notice" id="loginNotice" role="alert" aria-live="polite"></p>
                <a class="back-to-store" href="http://localhost:8000/">← Дүкенге қайту</a>
            </div>
        </section>
    </main>
    <script src="login.js"></script>
</body>
</html>
