async function provideCase(caseId) {
    if (!confirm('هل أنت متأكد من أنك تستطيع توفير هذه الحالة؟')) {
        return;
    }

    try {
        const result = await API.donate(caseId, csrfToken);
        if (result.status === 'ok') {
            showToast('تم! مكان التوصيل: ' + result.delivery_place, 'success');
            const btn = document.querySelector(`#case-btn-${caseId}`);
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="btn-icon">ت</span><span>تم التوفير</span>';
            }
            const card = document.querySelector(`#case-${caseId}`);
            if (card) {
                card.classList.add('claimed');
            }
        } else {
            showToast('حدث خطأ: ' + result.msg, 'error');
        }
    } catch (error) {
        showToast('حدث خطأ في الاتصال', 'error');
        console.error('Error:', error);
    }
}

async function deleteCase(caseId) {
    if (!confirm('هل أنت متأكد من حذف هذه الحالة؟')) {
        return;
    }

    try {
        const result = await API.deleteCase(caseId, csrfToken);
        if (result.status === 'ok') {
            showToast(result.msg || 'تم حذف الحالة بنجاح', 'success');
            if (window.location.pathname.includes('account.html')) {
                location.reload();
            }
        } else {
            showToast(result.msg || 'حدث خطأ أثناء حذف الحالة', 'error');
        }
    } catch (error) {
        showToast('حدث خطأ في الاتصال', 'error');
        console.error('Error:', error);
    }
}

async function refreshNotifications() {
    try {
        const result = await API.getNotifications();
        if (result.status === 'ok' && result.notifications) {
            updateNotificationsUI(result.notifications);
        }
    } catch (error) {
        console.error('Error refreshing notifications:', error);
    }
}

function updateNotificationsUI(notifications) {
    const container = document.getElementById('notifications-list');
    if (!container) return;

    container.innerHTML = '';

    if (notifications.length === 0) {
        container.innerHTML = '<p style="color: var(--muted); text-align: center;">لا توجد إشعارات</p>';
        return;
    }

    notifications.forEach(notif => {
        const item = document.createElement('div');
        item.className = 'notification-item' + (notif.read_flag == 0 ? ' unread' : '');
        item.innerHTML = `
            <div class="notification-message">${escapeHtml(notif.message)}</div>
            <div class="notification-time">${formatDate(notif.created_at)}</div>
        `;
        container.appendChild(item);
    });
}

async function markNotificationAsRead(notifId) {
    try {
        const result = await API.markNotificationRead(notifId, csrfToken);
        if (result.status === 'ok') {
            refreshNotifications();
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

function showToast(message, type = 'info') {
    const existingToast = document.querySelector('.toast');
    if (existingToast) {
        existingToast.remove();
    }

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;

    if (type === 'success') {
        toast.style.borderColor = 'var(--non-urgent)';
    } else if (type === 'error') {
        toast.style.borderColor = 'var(--urgent)';
    } else {
        toast.style.borderColor = 'var(--accent)';
    }

    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return false;

    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
            input.style.borderColor = 'var(--urgent)';
        } else {
            input.style.borderColor = '';
        }
    });

    return isValid;
}

document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!validateForm(form.id)) {
                e.preventDefault();
                showToast('يرجى ملء جميع الحقول المطلوبة', 'error');
            }
        });
    });

    if (document.getElementById('notifications-list')) {
        refreshNotifications();
        setInterval(refreshNotifications, 10000);
    }
});

