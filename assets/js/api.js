const API = {
    baseURL: window.location.pathname.includes('/public/') ? '../' : '',

    async request(endpoint, options = {}) {
        try {
            const response = await fetch(this.baseURL + endpoint, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error('Network error');
            }

            return await response.json();
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    },

    async checkSession() {
        return await this.request('api/auth.php?action=check_session');
    },

    async login(login, password) {
        const result = await this.request('api/auth.php?action=login', {
            method: 'POST',
            body: JSON.stringify({ login, password })
        });
        if (result.status === 'ok' && result.user) {
            const session = await this.checkSession();
            if (session.status === 'ok' && session.logged_in) {
                currentUser = session.user;
                csrfToken = session.csrf_token || '';
                window.CSRF_TOKEN = csrfToken;
                window.currentUser = currentUser;
            }
        }
        return result;
    },

    async register(data) {
        return await this.request('api/auth.php?action=register', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async logout() {
        const result = await this.request('api/auth.php?action=logout', {
            method: 'POST'
        });
        if (result.status === 'ok') {
            currentUser = null;
            csrfToken = '';
            window.CSRF_TOKEN = '';
            window.currentUser = null;
        }
        return result;
    },

    async getCases() {
        return await this.request('api/cases.php?action=get_cases');
    },

    async getStats() {
        return await this.request('api/cases.php?action=get_stats');
    },

    async getRecentCases() {
        return await this.request('api/cases.php?action=get_recent_cases');
    },

    async addCase(data) {
        return await this.request('api/cases.php?action=add_case', {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },

    async deleteCase(caseId, csrfToken) {
        return await this.request('api/cases.php?action=delete_case', {
            method: 'POST',
            body: JSON.stringify({ case_id: caseId, csrf_token: csrfToken })
        });
    },

    async donate(caseId, csrfToken) {
        // Updated: Now uses api/cases.php?action=donate instead of donate_action.php
        return await this.request('api/cases.php?action=donate', {
            method: 'POST',
            body: JSON.stringify({ case_id: caseId, csrf_token: csrfToken })
        });
    },

    async getUserInfo() {
        return await this.request('api/account.php?action=get_user_info');
    },

    async getNotifications() {
        return await this.request('api/account.php?action=get_notifications');
    },

    async markNotificationRead(notifId, csrfToken) {
        return await this.request('api/account.php?action=mark_read', {
            method: 'POST',
            body: JSON.stringify({ notification_id: notifId, csrf_token: csrfToken })
        });
    },

    async setDeliveryMethod(notifId, deliveryMethod, csrfToken) {
        return await this.request('api/account.php?action=set_delivery_method', {
            method: 'POST',
            body: JSON.stringify({ notification_id: notifId, delivery_method: deliveryMethod, csrf_token: csrfToken })
        });
    }
};

let currentUser = null;
let csrfToken = '';

async function initAuth() {
    try {
        const session = await API.checkSession();
        if (session.status === 'ok' && session.logged_in) {
            currentUser = session.user;
            csrfToken = session.csrf_token || '';
            window.CSRF_TOKEN = csrfToken;
            window.currentUser = currentUser;
            return true;
        }
        currentUser = null;
        csrfToken = '';
        window.CSRF_TOKEN = '';
        window.currentUser = null;
        return false;
    } catch (error) {
        console.error('Auth init error:', error);
        currentUser = null;
        csrfToken = '';
        window.CSRF_TOKEN = '';
        window.currentUser = null;
        return false;
    }
}

function requireAuth(redirectTo = 'login.html') {
    if (!currentUser) {
        window.location.href = redirectTo;
        return false;
    }
    return true;
}

function requireRole(role, redirectTo = 'index.html') {
    if (!currentUser || currentUser.role !== role) {
        window.location.href = redirectTo;
        return false;
    }
    return true;
}

function updateNavigation() {
    const nav = document.querySelector('.main-nav');
    if (!nav) return;

    if (currentUser) {
        const userInfo = nav.querySelector('.user-info');
        if (userInfo) {
            userInfo.textContent = `مرحباً، ${currentUser.name}`;
            userInfo.style.display = 'inline-block';
        }

        const addCaseLink = nav.querySelector('a[href="add_case.html"]');
        const accountLink = nav.querySelector('a[href="account.html"]');
        const logoutLink = nav.querySelector('a[href="logout.html"]');
        const loginLink = nav.querySelector('a[href="login.html"]');
        const registerLink = nav.querySelector('a[href="register.html"]');

        if (addCaseLink) {
            if (currentUser.role === 'needy') {
                addCaseLink.style.display = 'inline-block';
            } else {
                addCaseLink.style.display = 'none';
            }
        }
        if (accountLink) accountLink.style.display = 'inline-block';
        if (logoutLink) logoutLink.style.display = 'inline-block';
        if (loginLink) loginLink.style.display = 'none';
        if (registerLink) registerLink.style.display = 'none';
    } else {
        const addCaseLink = nav.querySelector('a[href="add_case.html"]');
        const accountLink = nav.querySelector('a[href="account.html"]');
        const logoutLink = nav.querySelector('a[href="logout.html"]');
        const userInfo = nav.querySelector('.user-info');
        const loginLink = nav.querySelector('a[href="login.html"]');
        const registerLink = nav.querySelector('a[href="register.html"]');

        if (addCaseLink) addCaseLink.style.display = 'none';
        if (accountLink) accountLink.style.display = 'none';
        if (logoutLink) logoutLink.style.display = 'none';
        if (userInfo) userInfo.style.display = 'none';
        if (loginLink) loginLink.style.display = 'inline-block';
        if (registerLink) registerLink.style.display = 'inline-block';
    }
}

document.addEventListener('DOMContentLoaded', async function () {
    await initAuth();
    updateNavigation();
});

