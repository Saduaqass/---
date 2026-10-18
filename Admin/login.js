const API_URL = window.ADMIN_API_URL || 'http://localhost:8000/admin-api.php';
const form = document.getElementById('loginForm');
const notice = document.getElementById('loginNotice');
let csrf = '';

function setNotice(message, isError = false) {
    notice.textContent = message;
    notice.classList.toggle('error', isError);
}

async function apiRequest(payload) {
    const response = await fetch(API_URL, {
        method: payload ? 'POST' : 'GET',
        credentials: 'include',
        headers: payload ? {'Content-Type': 'application/json'} : {},
        body: payload ? JSON.stringify(payload) : undefined,
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Сұрауды орындау мүмкін болмады.');
    return data;
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    setNotice('Тексерілуде…');
    try {
        if (!csrf) csrf = (await apiRequest()).csrf;
        await apiRequest({action: 'login', csrf, password: new FormData(form).get('password')});
        window.location.replace('index.php');
    } catch (error) {
        setNotice(error.message, true);
        if (error instanceof TypeError) setNotice('API серверіне қосыла алмады. localhost:8000 серверінің қосулы екенін тексеріңіз.', true);
    } finally {
        button.disabled = false;
    }
});

apiRequest().then(data => {
    csrf = data.csrf;
    if (data.authenticated) window.location.replace('index.php');
}).catch(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.has('error')) setNotice('API серверіне қосыла алмады. localhost:8000 серверінің қосулы екенін тексеріңіз.', true);
});
