function markNotificationRead(notificationId) {
    fetch('mark_notification_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI to reflect read status
            const notificationElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notificationElement) {
                notificationElement.classList.remove('unread', 'bg-light');
                const newBadge = notificationElement.querySelector('.badge.bg-danger');
                if (newBadge) {
                    newBadge.remove();
                }
                const markReadButton = notificationElement.querySelector('.btn-outline-secondary');
                if (markReadButton) {
                    markReadButton.remove();
                }
            }

            // Update unread count
            updateUnreadCount(data.unread_count);
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

function updateUnreadCount(count) {
    // Update the count in the tab
    const tabBadge = document.querySelector('#pills-notifications-tab .badge');
    if (count > 0) {
        if (tabBadge) {
            tabBadge.textContent = count;
        } else {
            const newBadge = document.createElement('span');
            newBadge.className = 'badge bg-danger';
            newBadge.textContent = count;
            document.querySelector('#pills-notifications-tab').appendChild(newBadge);
        }
    } else if (tabBadge) {
        tabBadge.remove();
    }

    // Update the count in the header
    const headerBadge = document.querySelector('.card-header .badge');
    if (headerBadge) {
        if (count > 0) {
            headerBadge.textContent = `${count} unread`;
        } else {
            headerBadge.remove();
        }
    }
}

// Function to check for new notifications
function checkNewNotifications() {
    fetch('check_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.new_notifications) {
                // Refresh the notifications list
                location.reload();
            }
        })
        .catch(error => {
            console.error('Error checking notifications:', error);
        });
}

// Check for new notifications every 30 seconds
setInterval(checkNewNotifications, 30000); 